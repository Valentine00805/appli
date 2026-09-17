<?php
declare(strict_types=1);

/**
 * Partager un cours ou un fichier : la fenêtre « Partager » du propriétaire,
 * la lecture pour un ami, et le lien public pour tout le monde.
 *
 * Dans les adresses, le type s'écrit « cours » ou « fichiers ».
 */
final class PartagesController
{
    /** « cours » ou « fichiers » dans l'adresse ; « cours » ou « fichier » en base. */
    private static function type(string $mot): string
    {
        return match ($mot) {
            'cours' => 'cours',
            'fichiers' => 'fichier',
            'fiches' => 'fiche',
            'dossiers' => 'dossier',
            default => self::introuvable(),
        };
    }

    /** L'onglet « Partagés » : ce qu'on m'a partagé, et ce que je partage. */
    public function index(): void
    {
        Auth::exiger();
        $moi = Auth::id();
        Vue::afficher('partages/index', [
            'recus' => Partages::recus($moi),
            'envoyes' => Partages::envoyes($moi),
        ], 'Partagés avec moi');
    }

    /** La fenêtre « Partager » : les amis d'abord, puis le lien public. */
    public function fenetre(string $mot, int $id): void
    {
        Auth::exiger();
        $moi = Auth::id();
        $type = self::type($mot);
        $cible = Partages::mienne($type, $id, $moi);
        if ($cible === null) {
            self::introuvable();
        }
        $lien = Partages::lien($type, $id);
        $donnees = [
            'type' => $type,
            'mot' => $mot,
            'cible' => $cible,
            'amis' => Amis::liste($moi),
            'groupes' => Conversations::liste($moi),
            'destinataires' => Partages::destinataires($moi, $type, $id),
            'lien' => $lien === null ? null : Partages::adresseLien((string) $lien['jeton']),
            'vues' => $lien === null ? 0 : (int) $lien['vues'],
        ];
        if (Vue::enFenetre()) {
            Vue::fragment('partages/partager', $donnees);
            return;
        }
        Vue::afficher('partages/partager', $donnees, 'Partager');
    }

    public function envoyer(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $type = self::type($mot);
        [$nombre, $refus, $notifications] = Partages::partagerAvecAmis(
            Auth::id(), $type, $id,
            is_array($_POST['amis'] ?? null) ? $_POST['amis'] : [],
            is_array($_POST['groupes'] ?? null) ? $_POST['groupes'] : [],
            (string) ($_POST['texte'] ?? '')
        );
        if ($refus !== null) {
            Session::flash('erreur', $refus);
        } else {
            Session::flash('succes', 'Partagé avec ' . $nombre . ' personne' . ($nombre > 1 ? 's' : '') . ' : une carte est partie dans vos discussions.');
        }
        $this->retourPuisEnvoyer('partager/' . $mot . '/' . $id, $notifications);
    }

    public function retirerAcces(string $mot, int $id, int $destinataire): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $pseudo = (string) (Amis::compte($destinataire)['pseudo'] ?? '');
        if (Partages::retirerAcces(Auth::id(), self::type($mot), $id, $destinataire)) {
            Session::flash('succes', $pseudo . ' n’y a plus accès.');
        } else {
            Session::flash('erreur', 'Ce partage n’existe plus.');
        }
        redirect('partager/' . $mot . '/' . $id);
    }

    public function creerLien(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $lien = Partages::creerLien(Auth::id(), self::type($mot), $id);
        if ($lien === null) {
            self::introuvable();
        }
        Session::flash('succes', 'Lien créé : toute personne qui l’a peut voir et télécharger ce document, sans compte.');
        redirect('partager/' . $mot . '/' . $id);
    }

    public function desactiverLien(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        Session::flash(
            Partages::desactiverLien(Auth::id(), self::type($mot), $id) ? 'succes' : 'info',
            'Lien désactivé : l’adresse ne mène plus à rien, même si elle a circulé.'
        );
        redirect('partager/' . $mot . '/' . $id);
    }

    /** Lire un document partagé par un ami. */
    public function lire(string $mot, int $id): void
    {
        Auth::exiger();
        $moi = Auth::id();
        $type = self::type($mot);
        $cible = Partages::cible($type, $id);
        if ($cible !== null && (int) $cible['user_id'] === $moi) {
            // Le sien : sa vraie page.
            if ($type === 'dossier') {
                redirect('cours', ['dossier' => $id]);
            }
            redirect(match ($type) {
                'cours' => 'cours/' . $id,
                'fiche' => 'revision/' . $id,
                default => 'fichiers/' . $id . (ApercuDocument::possible((string) $cible['nom_origine']) ? '/apercu' : ''),
            });
        }
        if ($cible === null || !Partages::peutVoir($type, $id, $moi)) {
            Session::flash('erreur', 'Ce document n’est pas, ou plus, partagé avec vous.');
            redirect('partages');
        }
        $donnees = [
            'type' => $type,
            'cible' => $cible,
            'public' => false,
            'fichiers' => match ($type) { 'cours' => Partages::fichiersDuCours($id), 'fiche' => Partages::fichiersDeLaFiche($id), default => [] },
            'liens' => $type === 'fiche' ? Partages::liensDeLaFiche($id) : [],
            'groupes' => $type === 'dossier' ? Partages::contenuDuDossier($id, (int) $cible['user_id']) : [],
            'adresseCours' => static fn (int $c): string => url('partages/cours/' . $c),
            'adresseFichier' => static fn (int $f, bool $telecharger = false): string => url('partages/fichiers/' . $f . '/contenu', $telecharger ? ['telecharger' => 1] : []),
            'mesCours' => $type === 'fichier'
                ? Database::all('SELECT id, titre FROM cours WHERE user_id = ? ORDER BY titre', [$moi]) : [],
            'recu' => Database::valeur('SELECT 1 FROM partages_amis WHERE destinataire_id = ? AND cible_type = ? AND cible_id = ?', [$moi, $type, $id]) !== null,
            'mot' => $mot,
        ];
        // Un cours ouvert depuis un dossier partagé : de quoi y revenir.
        if ($type === 'cours') {
            $dossierId = Partages::dossierPartage($id, $moi);
            if ($dossierId !== null) {
                $dossier = Partages::cible('dossier', $dossierId);
                $donnees['retour'] = ['url' => url('partages/dossiers/' . $dossierId), 'texte' => '← ' . (string) ($dossier['titre'] ?? 'Dossier partagé')];
            }
        }
        Vue::afficher('partages/lire', $donnees, (string) $cible['titre']);
    }

    /** Le contenu d'un fichier partagé, pour un ami. */
    public function contenu(int $id): void
    {
        Auth::exiger();
        session_write_close();
        $moi = Auth::id();
        $fichier = Partages::cible('fichier', $id);
        if ($fichier === null || !Partages::peutVoir('fichier', $id, $moi)) {
            self::introuvable();
        }
        Fichiers::envoyer($fichier, isset($_GET['telecharger']));
    }

    public function copier(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $type = self::type($mot);
        [$cours, $refus] = Partages::copier(Auth::id(), $type, $id, entier_ou_null($_POST['cours'] ?? null));
        if ($refus !== null) {
            Session::flash('erreur', $refus);
            redirect('partages/' . $mot . '/' . $id);
        }
        Session::flash('succes', match ($type) {
            'cours' => 'Cours copié dans vos cours : cette copie est à vous, modifiable.',
            'fiche' => 'Fiche copiée : un nouveau cours à vous, dont c’est la fiche de révision.',
            'dossier' => 'Dossier copié dans vos dossiers : ces copies sont à vous, modifiables.',
            default => 'Fichier copié dans votre cours.',
        });
        if ($type === 'dossier') {
            redirect('cours', ['dossier' => $cours]);
        }
        redirect(($type === 'fiche' ? 'revision/' : 'cours/') . $cours);
    }

    public function oublier(string $mot, int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        Partages::oublierRecu(Auth::id(), self::type($mot), $id);
        Session::flash('succes', 'Retiré de vos documents partagés.');
        redirect('partages');
    }

    /** Le lien public : la page du document, pour tout le monde. */
    public function public(string $jeton): void
    {
        $trouve = Partages::parJeton($jeton);
        header('X-Robots-Tag: noindex, nofollow');
        if ($trouve === null) {
            http_response_code(404);
            Vue::afficherPublic('partages/lien_mort', [], 'Lien introuvable');
            return;
        }
        Partages::compterVue((int) $trouve['lien']['id']);
        $type = (string) $trouve['lien']['cible_type'];
        $cible = $trouve['cible'];
        Vue::afficherPublic('partages/lire', [
            'type' => $type,
            'cible' => $cible,
            'public' => true,
            'fichiers' => match ($type) { 'cours' => Partages::fichiersDuCours((int) $cible['id']), 'fiche' => Partages::fichiersDeLaFiche((int) $cible['id']), default => [] },
            'liens' => $type === 'fiche' ? Partages::liensDeLaFiche((int) $cible['id']) : [],
            'groupes' => $type === 'dossier' ? Partages::contenuDuDossier((int) $cible['id'], (int) $cible['user_id']) : [],
            'adresseCours' => static fn (int $c): string => url('p/' . $jeton . '/cours/' . $c),
            'adresseFichier' => static fn (int $f, bool $telecharger = false): string => url('p/' . $jeton . '/fichiers/' . $f, $telecharger ? ['telecharger' => 1] : []),
            'mesCours' => [],
            'recu' => false,
            'mot' => Partages::mot($type),
        ], (string) $cible['titre']);
    }

    /** Un cours d'un dossier partagé par lien public. */
    public function coursPublic(string $jeton, int $id): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        $trouve = Partages::parJeton($jeton);
        $cours = Partages::cible('cours', $id);
        if ($trouve === null || $cours === null
            || $trouve['lien']['cible_type'] !== 'dossier'
            || (int) $cours['user_id'] !== (int) $trouve['lien']['user_id']
            || !Partages::dansLeDossier($id, (int) $trouve['lien']['cible_id'])) {
            http_response_code(404);
            Vue::afficherPublic('partages/lien_mort', [], 'Lien introuvable');
            return;
        }
        Vue::afficherPublic('partages/lire', [
            'type' => 'cours',
            'cible' => $cours,
            'public' => true,
            'fichiers' => Partages::fichiersDuCours($id),
            'liens' => [],
            'groupes' => [],
            'adresseCours' => static fn (int $c): string => url('p/' . $jeton . '/cours/' . $c),
            'adresseFichier' => static fn (int $f, bool $telecharger = false): string => url('p/' . $jeton . '/fichiers/' . $f, $telecharger ? ['telecharger' => 1] : []),
            'mesCours' => [],
            'recu' => false,
            'mot' => 'cours',
            'retour' => ['url' => url('p/' . $jeton), 'texte' => '← ' . (string) ($trouve['cible']['titre'] ?? 'Dossier partagé')],
        ], (string) $cours['titre']);
    }

    /** Un fichier par le lien public : le fichier partagé, ou un fichier joint du cours partagé. */
    public function fichierPublic(string $jeton, int $id): void
    {
        session_write_close();
        header('X-Robots-Tag: noindex, nofollow');
        $trouve = Partages::parJeton($jeton);
        $fichier = Partages::cible('fichier', $id);
        $permis = $trouve !== null && $fichier !== null && (
            ($trouve['lien']['cible_type'] === 'fichier' && (int) $trouve['lien']['cible_id'] === $id)
            || ($trouve['lien']['cible_type'] === 'cours' && (int) $fichier['cours_id'] === (int) $trouve['lien']['cible_id']
                && (int) $fichier['pour_fiche'] === 0)
            || ($trouve['lien']['cible_type'] === 'fiche' && (int) $fichier['cours_id'] === (int) $trouve['lien']['cible_id']
                && (int) $fichier['pour_fiche'] === 1)
            || ($trouve['lien']['cible_type'] === 'dossier' && (int) $fichier['pour_fiche'] === 0
                && (int) $fichier['user_id'] === (int) $trouve['lien']['user_id']
                && Partages::dansLeDossier((int) $fichier['cours_id'], (int) $trouve['lien']['cible_id']))
        );
        if (!$permis) {
            http_response_code(404);
            exit('Fichier introuvable.');
        }
        Fichiers::envoyer($fichier, isset($_GET['telecharger']));
    }

    /** Revient à la fenêtre « Partager », puis envoie les notifications en file. */
    private function retourPuisEnvoyer(string $chemin, array $notifications): never
    {
        $adresse = url($chemin);
        session_write_close();
        ignore_user_abort(true);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        // Envoyé depuis la fenêtre : on y revient, en fragment.
        if (($_POST['fenetre'] ?? '') === '1') {
            $adresse .= '?fenetre=1';
        }
        header('Location: ' . $adresse);
        header('Content-Length: 0');
        header('Connection: close');
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        foreach ($notifications as $n) {
            FileNotifications::envoyer($n);
        }
        exit;
    }

    private static function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], 'Introuvable');
        exit;
    }
}
