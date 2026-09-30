<?php
declare(strict_types=1);

/** Gestion des tags : ajout, renommage, fusion, suppression. */
final class TagsController
{
    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $tri = in_array($_GET['tri'] ?? '', ['usage', 'nom'], true) ? (string) $_GET['tri'] : 'nom';

        $tags = Database::all(
            'SELECT t.id, t.nom, COUNT(ct.cours_id) AS nb_cours
             FROM tags t
             LEFT JOIN cours_tag ct ON ct.tag_id = t.id
             WHERE t.user_id = ?
             GROUP BY t.id, t.nom
             ORDER BY ' . ($tri === 'usage' ? 'nb_cours DESC, t.nom' : 't.nom'),
            [$userId]
        );

        Vue::afficher('tags/index', [
            'tags'      => $tags,
            'tri'       => $tri,
            'inutilises' => count(array_filter($tags, static fn (array $t): bool => (int) $t['nb_cours'] === 0)),
        ], t('tags.titre'));
    }

    public function creer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $noms = $this->decouper(post('nom'));
        if ($noms === []) {
            Session::flash('erreur', t('flash.tag_un_nom'));
            redirect('organisation/tags');
        }

        $crees = 0;
        $ignores = [];
        foreach ($noms as $nom) {
            if (Database::valeur('SELECT id FROM tags WHERE user_id = ? AND nom = ?', [$userId, $nom]) !== null) {
                $ignores[] = $nom;
                continue;
            }
            Database::run('INSERT INTO tags (user_id, nom) VALUES (?, ?)', [$userId, $nom]);
            $crees++;
        }

        if ($crees > 0) {
            Session::flash('succes', tn('flash.tags_crees', $crees));
        }
        if ($ignores !== []) {
            Session::flash('info', tn('flash.tags_deja', count($ignores), [
                'liste' => implode(', ', $ignores) . '.',
            ]));
        }
        redirect('organisation/tags');
    }

    public function modifier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        if (Database::valeur('SELECT id FROM tags WHERE id = ? AND user_id = ?', [$id, $userId]) === null) {
            $this->introuvable();
        }

        $nom = $this->nettoyer(post('nom'));
        if ($nom === '') {
            Session::flash('erreur', t('flash.tag_nom_obligatoire'));
            redirect('organisation/tags');
        }

        $doublon = Database::valeur(
            'SELECT id FROM tags WHERE user_id = ? AND nom = ? AND id <> ?',
            [$userId, $nom, $id]
        );
        if ($doublon !== null) {
            Session::flash('erreur', t('flash.tag_autre_deja'));
            redirect('organisation/tags');
        }

        Database::run('UPDATE tags SET nom = ? WHERE id = ? AND user_id = ?', [$nom, $id, $userId]);
        Session::flash('succes', t('flash.tag_renomme', ['nom' => $nom]));
        redirect('organisation/tags');
    }

    public function supprimer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $tag = Database::one('SELECT nom FROM tags WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($tag === null) {
            $this->introuvable();
        }

        $nbCours = (int) Database::valeur('SELECT COUNT(*) FROM cours_tag WHERE tag_id = ?', [$id]);

        // La clé étrangère supprime les associations ; les cours ne sont pas touchés.
        Database::run('DELETE FROM tags WHERE id = ? AND user_id = ?', [$id, $userId]);

        Session::flash('succes', $nbCours === 0
            ? t('flash.tag_supprime', ['nom' => $tag['nom']])
            : tn('flash.tag_supprime_cours', $nbCours, ['nom' => $tag['nom']]));
        redirect('organisation/tags');
    }

    /** Réunit un tag dans un autre : les cours sont reportés, le premier disparaît. */
    public function fusionner(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $source = Database::one('SELECT nom FROM tags WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($source === null) {
            $this->introuvable();
        }

        $cibleId = entier_ou_null($_POST['cible_id'] ?? null);
        if ($cibleId === null || $cibleId === $id) {
            Session::flash('erreur', t('flash.tag_destination'));
            redirect('organisation/tags');
        }

        $cible = Database::one('SELECT nom FROM tags WHERE id = ? AND user_id = ?', [$cibleId, $userId]);
        if ($cible === null) {
            Session::flash('erreur', t('flash.tag_destination_introuvable'));
            redirect('organisation/tags');
        }

        // Report des cours qui n'ont pas déjà le tag de destination.
        Database::run(
            'INSERT IGNORE INTO cours_tag (cours_id, tag_id)
             SELECT ct.cours_id, ? FROM cours_tag ct WHERE ct.tag_id = ?',
            [$cibleId, $id]
        );
        Database::run('DELETE FROM tags WHERE id = ? AND user_id = ?', [$id, $userId]);

        Session::flash('succes', t('flash.tag_fusionne', [
            'de' => $source['nom'], 'vers' => $cible['nom'],
        ]));
        redirect('organisation/tags');
    }

    /** Supprime d'un coup les tags qui ne sont sur aucun cours. */
    public function nettoyer_inutilises(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $nb = (int) Database::valeur(
            'SELECT COUNT(*) FROM tags t
             WHERE t.user_id = ? AND NOT EXISTS (SELECT 1 FROM cours_tag ct WHERE ct.tag_id = t.id)',
            [$userId]
        );
        Database::run(
            'DELETE FROM tags
             WHERE user_id = ? AND NOT EXISTS (SELECT 1 FROM cours_tag ct WHERE ct.tag_id = tags.id)',
            [$userId]
        );

        Session::flash('succes', $nb === 0
            ? t('flash.tags_aucun_inutilise')
            : tn('flash.tags_nettoyes', $nb));
        redirect('organisation/tags');
    }

    /** Tous les tags d'un utilisateur, pour les suggestions du formulaire de cours. */
    public static function nomsPourUtilisateur(int $userId): array
    {
        return array_column(
            Database::all('SELECT nom FROM tags WHERE user_id = ? ORDER BY nom', [$userId]),
            'nom'
        );
    }

    /** Découpe une saisie « a, b, c » en noms de tags valides et distincts. */
    private function decouper(string $saisie): array
    {
        $noms = array_map([$this, 'nettoyer'], explode(',', $saisie));
        return array_values(array_unique(array_filter($noms, static fn (string $n): bool => $n !== '')));
    }

    private function nettoyer(string $nom): string
    {
        // Les espaces multiples sont réduits et le « # » de tête retiré.
        $nom = trim(preg_replace('/\s+/u', ' ', $nom) ?? '');
        return mb_substr(ltrim($nom, '#'), 0, 60);
    }

    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], t('titre.introuvable'));
        exit;
    }
}
