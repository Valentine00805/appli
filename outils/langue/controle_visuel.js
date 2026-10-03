/*
 * Le contrôle visuel : chaque page, à une largeur donnée, cherche ce qui déborde.
 *
 * À coller dans la console d'un navigateur connecté à l'application (voir LISEZMOI.md, « Le
 * contrôle visuel » : controle_visuel_prepare.php fournit un compte et une session).
 *
 *     const r = await controleVisuel(375, ['', 'calendrier', 'budget']);
 *     console.table(r.filter(x => x.ox || x.sortent.length || x.coupes.length));
 *
 * Chaque page s'ouvre dans un iframe de la largeur demandée : les media queries répondent à
 * l'iframe, et un seul appel en parcourt des dizaines.
 *
 * Ce que le mesureur trouve :
 *   ox       la page entière déborde en largeur (barre de défilement horizontale) ;
 *   sortent  du texte qui dépasse le bord droit, hors d'un conteneur qui défile ;
 *   coupes   du texte coupé par « overflow: hidden » ou « … » ;
 *   multi    des boutons et des onglets qui passent sur deux lignes ou plus.
 *
 * Ce qu'il ne trouve pas : un chevauchement, une aération qui s'écrase, une hiérarchie qui
 * se brouille. Pour cela, il faut regarder une capture.
 *
 * Deux faux positifs connus :
 *   - l'iframe a une barre de défilement de 15 px que le téléphone n'a pas : à 375 px, une page
 *     qui déborde de moins de 15 px ne déborde pas vraiment — vérifier dans l'onglet lui-même ;
 *   - un panneau fermé, placé hors écran, compte comme du texte qui sort.
 *
 * Pour comparer deux langues, mesurer l'une puis l'autre (changer la langue du compte de test
 * en base entre les deux) et ne regarder que ce qui apparaît dans la seconde.
 */
async function controleVisuel(largeur, pages) {
  const detecteur = function () {
    const de = document.documentElement, vw = de.clientWidth;
    const txt = el => (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 34);
    const sel = el => el.tagName.toLowerCase() + (typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split(/\s+/)[0] : '');
    const visible = el => { const cs = getComputedStyle(el), r = el.getBoundingClientRect(); return cs.display !== 'none' && cs.visibility !== 'hidden' && r.width > 0 && r.height > 0; };
    const defile = el => { for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) { const o = getComputedStyle(p).overflowX; if ((o === 'auto' || o === 'scroll') && p.scrollWidth > p.clientWidth) return true; } return false; };
    const lien = document.querySelector('link[rel="manifest"]');
    const racine = lien ? new URL(lien.href).pathname.replace(/manifeste\.webmanifest$/, '') : '/';
    const out = { p: '/' + location.pathname.slice(racine.length) + location.search, l: de.lang, vw, sw: de.scrollWidth, ox: de.scrollWidth > vw + 1, sortent: [], coupes: [], multi: [] };
    document.querySelectorAll('body *').forEach(el => {
      // Les textes réservés aux lecteurs d'écran sont faits pour tenir sur un pixel.
      if (!visible(el) || el.closest('[hidden]') || el.closest('.sr-only') || el.matches('.sr-only')) return;
      const cs = getComputedStyle(el), r = el.getBoundingClientRect();
      if (r.right > vw + 1 && !defile(el) && el.children.length === 0 && txt(el)) out.sortent.push(sel(el) + ' « ' + txt(el) + ' » +' + Math.round(r.right - vw));
      if ((cs.overflowX === 'hidden' || cs.textOverflow === 'ellipsis') && el.scrollWidth > el.clientWidth + 1 && txt(el) && el.children.length < 12) out.coupes.push(sel(el) + ' « ' + txt(el) + ' » ' + el.scrollWidth + '>' + el.clientWidth);
      if (el.matches('button, .bouton, .onglets a, summary') && el.children.length < 4 && txt(el)) {
        const lh = parseFloat(cs.lineHeight) || parseFloat(cs.fontSize) * 1.4;
        const l = Math.round((r.height - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom)) / lh);
        if (l >= 2) out.multi.push(l + 'l ' + sel(el) + ' « ' + txt(el) + ' »');
      }
    });
    ['sortent', 'coupes', 'multi'].forEach(k => { out[k] = [...new Set(out[k])].slice(0, 8); });
    return out;
  };

  // Le dossier de l'application, d'après le lien du manifeste : il n'a pas besoin d'être connu d'avance.
  const base = document.querySelector('link[rel="manifest"]').href.replace(/manifeste\.webmanifest.*$/, '');
  const f = document.createElement('iframe');
  f.style.cssText = 'position:fixed;left:0;top:0;width:' + largeur + 'px;height:900px;border:0;visibility:hidden';
  document.body.appendChild(f);
  const res = [];
  for (const p of pages) {
    await new Promise(ok => { f.onload = ok; f.src = base + p; setTimeout(ok, 9000); });
    await new Promise(r => setTimeout(r, 300));
    try { res.push(f.contentWindow.eval('(' + detecteur.toString() + ')()')); } catch (e) { res.push({ p, erreur: String(e) }); }
  }
  f.remove();
  return res;
}
