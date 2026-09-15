<?php
declare(strict_types=1);

/**
 * Les rappels : ce qui doit partir maintenant, et le faire partir.
 *
 * Deux sortes de rappels :
 *   - avant un évènement, de chacun des délais qu'il porte (quinze minutes par
 *     défaut) —
 *     un évènement « toute la journée » se rappelle à 8 h le jour même, ou
 *     les jours d'avant pour un délai d'un jour ou plus ;
 *   - le matin d'une échéance, à 8 h, pour une sous-tâche pas encore faite ou
 *     une tâche principale qui en a encore.
 *
 * Tout est vérifié chaque minute par l'adresse d'envoi (une tâche planifiée
 * l'appelle), et aussi par la page tant qu'un onglet de l'application est
 * ouvert. Un rappel en retard — ordinateur en veille, tâche manquée — part
 * encore tant que l'évènement n'a pas commencé : mieux vaut tard que jamais,
 * mais pas après coup. Chaque rappel est inscrit avant de partir, et ne part
 * donc qu'une fois.
 */
final class Rappels
{
    /** Les délais proposés dans le formulaire d'un évènement, en minutes. */
    public const DELAIS = [
        0    => 'À l’heure de l’évènement',
        5    => '5 minutes avant',
        10   => '10 minutes avant',
        15   => '15 minutes avant',
        30   => '30 minutes avant',
        60   => '1 heure avant',
        120  => '2 heures avant',
        1440 => '1 jour avant',
        2880 => '2 jours avant',
        10080 => '1 semaine avant',
    ];

    /** Les mêmes, en court, pour les pastilles du formulaire. */
    public const DELAIS_COURTS = [
        0    => 'À l’heure',
        5    => '5 min',
        10   => '10 min',
        15   => '15 min',
        30   => '30 min',
        60   => '1 h',
        120  => '2 h',
        1440 => '1 jour',
        2880 => '2 jours',
        10080 => '1 semaine',
    ];

    /**
     * Les délais d'un évènement, lus depuis la base : « 1440,15 ».
     * Du plus lointain au plus proche, sans doublon ni délai inconnu.
     *
     * @return list<int>
     */
    public static function lire(?string $valeur): array
    {
        return self::depuisFormulaire(explode(',', (string) $valeur));
    }

    /**
     * Les délais cochés dans un formulaire, nettoyés de la même façon.
     *
     * @return list<int>
     */
    public static function depuisFormulaire(mixed $valeurs): array
    {
        $delais = [];
        foreach (is_array($valeurs) ? $valeurs : [] as $valeur) {
            if (is_string($valeur) && preg_match('/^\d+$/', trim($valeur))
                && array_key_exists((int) $valeur, self::DELAIS)) {
                $delais[(int) $valeur] = true;
            }
        }
        $delais = array_keys($delais);
        rsort($delais);

        return $delais;
    }

    /** @param list<int> $delais */
    public static function ecrire(array $delais): string
    {
        return implode(',', $delais);
    }

    /** « 1 jour avant · 15 minutes avant », ou une chaîne vide. */
    public static function dire(?string $valeur): string
    {
        return implode(' · ', array_map(static fn (int $d): string => self::DELAIS[$d], self::lire($valeur)));
    }

    /** L'heure des rappels qui ne tombent pas à une heure précise. */
    private const HEURE_DU_MATIN = 8;

    /**
     * Envoie les rappels dus, pour tous les comptes qui ont un appareil abonné,
     * ou pour un seul.
     *
     * @return array{rappels: int, envoyes: int, echecs: int, retires: int}
     */
    public static function envoyerCeQuiEstDu(?int $seulement = null): array
    {
        $bilan = ['rappels' => 0, 'envoyes' => 0, 'echecs' => 0, 'retires' => 0];

        $comptes = Database::all(
            'SELECT DISTINCT u.id, u.fuseau FROM users u JOIN abonnements_push a ON a.user_id = u.id'
            . ($seulement === null ? '' : ' WHERE u.id = ?'),
            $seulement === null ? [] : [$seulement]
        );

        foreach ($comptes as $compte) {
            $fuseau = Auth::fuseauValide((string) $compte['fuseau']) ? (string) $compte['fuseau'] : Auth::FUSEAU_PAR_DEFAUT;
            $maintenant = new DateTimeImmutable('now', new DateTimeZone($fuseau));

            foreach (self::dus((int) $compte['id'], $maintenant) as $rappel) {
                // Inscrit d'abord : un second passage simultané bute sur la clé unique.
                $inscrit = Database::run(
                    'INSERT IGNORE INTO rappels_envoyes (user_id, nature, objet_id, moment) VALUES (?, ?, ?, ?)',
                    [(int) $compte['id'], $rappel['nature'], $rappel['objet_id'], $rappel['moment']]
                )->rowCount();
                if ($inscrit === 0) {
                    continue;
                }
                $bilan['rappels']++;

                $resultat = self::envoyerAuCompte((int) $compte['id'], $rappel['message']);
                $bilan['envoyes'] += $resultat['envoyes'];
                $bilan['echecs'] += $resultat['echecs'];
                $bilan['retires'] += $resultat['retires'];

                // Inscrit pour de bon : les rappels plus anciens du même évènement n'ont plus lieu d'être.
                if ($resultat['envoyes'] > 0 || $resultat['echecs'] === 0) {
                    foreach ($rappel['aussi'] ?? [] as $avant) {
                        Database::run(
                            'INSERT IGNORE INTO rappels_envoyes (user_id, nature, objet_id, moment) VALUES (?, ?, ?, ?)',
                            [(int) $compte['id'], $rappel['nature'], $rappel['objet_id'], $avant]
                        );
                    }
                }

                // Aucun appareil n'a pu être joint : on réessaiera à la minute suivante.
                if ($resultat['envoyes'] === 0 && $resultat['echecs'] > 0) {
                    Database::run(
                        'DELETE FROM rappels_envoyes WHERE user_id = ? AND nature = ? AND objet_id = ? AND moment = ?',
                        [(int) $compte['id'], $rappel['nature'], $rappel['objet_id'], $rappel['moment']]
                    );
                }
            }
        }

        // Les messages et demandes d'ami restés en file : même tâche, même rythme que les rappels.
        $file = FileNotifications::envoyerEnAttente($seulement);
        $bilan['file_envoyees'] = $file['envoyees'];
        $bilan['file_retenues'] = $file['retenues'];

        return $bilan;
    }

    /**
     * Un message à tous les appareils d'un compte. Un appareil dont le service
     * dit qu'il n'existe plus (404, 410) est retiré.
     *
     * @return array{envoyes: int, echecs: int, retires: int}
     */
    public static function envoyerAuCompte(int $userId, array $message): array
    {
        $bilan = ['envoyes' => 0, 'echecs' => 0, 'retires' => 0];
        $contact = (string) (Config::get('notifications', 'contact') ?: 'https://github.com/Valentine00805/appli');

        foreach (Database::all('SELECT * FROM abonnements_push WHERE user_id = ?', [$userId]) as $abonnement) {
            try {
                $code = WebPush::envoyer($abonnement, $message, $contact);
            } catch (Throwable) {
                $code = 0;
            }

            if ($code >= 200 && $code < 300) {
                $bilan['envoyes']++;
                Database::run('UPDATE abonnements_push SET dernier_envoi = NOW() WHERE id = ?', [(int) $abonnement['id']]);
            } elseif ($code === 404 || $code === 410) {
                $bilan['retires']++;
                Database::run('DELETE FROM abonnements_push WHERE id = ?', [(int) $abonnement['id']]);
            } else {
                $bilan['echecs']++;
            }
        }

        return $bilan;
    }

    /**
     * Les rappels qui doivent partir à cet instant pour un compte.
     *
     * @return list<array{nature: string, objet_id: int, moment: string, aussi?: list<string>, message: array}>
     */
    public static function dus(int $userId, DateTimeImmutable $maintenant): array
    {
        $fuseau = $maintenant->getTimezone();
        $rappels = [];

        // --- Les évènements : d'un jour avant à huit jours après, de quoi couvrir les délais.
        $evenements = Database::all(
            "SELECT id, titre, lieu, debut, fin, journee_entiere, rappels
               FROM evenements
              WHERE user_id = ? AND rappels <> '' AND termine = 0 AND debut BETWEEN ? AND ?",
            [$userId, $maintenant->modify('-1 day')->format('Y-m-d H:i:s'), $maintenant->modify('+8 days')->format('Y-m-d H:i:s')]
        );
        foreach ($evenements as $evt) {
            $debut = new DateTimeImmutable((string) $evt['debut'], $fuseau);

            /*
             * Chaque délai donne un moment ; ceux qui sont passés sans que
             * l'évènement ait commencé sont dus. Un seul part — le plus
             * récent : après une veille, « 1 heure avant » et « 15 minutes
             * avant » ne sonnent pas ensemble. Les plus anciens sont inscrits
             * avec lui, pour ne pas partir à la minute suivante.
             */
            $moments = [];
            foreach (self::lire((string) $evt['rappels']) as $delai) {
                if ((int) $evt['journee_entiere'] === 1) {
                    $matin = $debut->setTime(self::HEURE_DU_MATIN, 0);
                    $moment = $delai >= 1440 ? $matin->modify('-' . intdiv($delai, 1440) . ' days') : $matin;
                    $limite = $debut->setTime(23, 59, 59);
                } else {
                    $moment = $debut->modify('-' . $delai . ' minutes');
                    // Un rappel « à l'heure » garde quelques minutes de grâce ; les autres s'arrêtent au début.
                    $limite = $delai === 0 ? $debut->modify('+5 minutes') : $debut;
                }
                if ($moment <= $maintenant && $maintenant <= $limite) {
                    $moments[$moment->format('Y-m-d H:i:s')] = true;
                }
            }

            if ($moments !== []) {
                $moments = array_keys($moments);
                sort($moments);
                $moment = array_pop($moments);
                $rappels[] = [
                    'nature' => 'evenement', 'objet_id' => (int) $evt['id'],
                    'moment' => $moment,
                    'aussi' => $moments,
                    'message' => [
                        'title' => '📅 ' . $evt['titre'],
                        'body' => self::quand($debut, (int) $evt['journee_entiere'] === 1, $maintenant,
                            $evt['fin'] === null ? null : new DateTimeImmutable((string) $evt['fin'], $fuseau))
                            . ((string) ($evt['lieu'] ?? '') !== '' ? ' · ' . $evt['lieu'] : ''),
                        'url' => url('evenements/' . (int) $evt['id']),
                        'tag' => 'evenement-' . (int) $evt['id'],
                    ],
                ];
            }
        }

        // --- Les échéances du jour, à partir de 8 h.
        $matin = $maintenant->setTime(self::HEURE_DU_MATIN, 0);
        if ($maintenant >= $matin) {
            $aujourdhui = $maintenant->format('Y-m-d');

            foreach (Database::all(
                'SELECT t.id, t.titre, l.nom AS liste_nom, l.icone AS liste_icone, t.liste_id
                   FROM taches t JOIN listes_taches l ON l.id = t.liste_id
                  WHERE t.user_id = ? AND t.faite = 0 AND t.echeance = ?',
                [$userId, $aujourdhui]
            ) as $tache) {
                $rappels[] = [
                    'nature' => 'tache', 'objet_id' => (int) $tache['id'],
                    'moment' => $matin->format('Y-m-d H:i:s'),
                    'message' => [
                        'title' => '✅ À faire aujourd’hui : ' . $tache['titre'],
                        'body' => 'Échéance aujourd’hui · ' . trim(($tache['liste_icone'] ?? '') . ' ' . $tache['liste_nom']),
                        'url' => url('taches', ['liste' => (int) $tache['liste_id']]),
                        'tag' => 'tache-' . (int) $tache['id'],
                    ],
                ];
            }

            foreach (Database::all(
                'SELECT l.id, l.nom, l.icone,
                        (SELECT COUNT(*) FROM taches t WHERE t.liste_id = l.id AND t.faite = 0) AS reste
                   FROM listes_taches l
                  WHERE l.user_id = ? AND l.echeance = ?
                    AND (NOT EXISTS (SELECT 1 FROM taches t WHERE t.liste_id = l.id)
                         OR EXISTS (SELECT 1 FROM taches t WHERE t.liste_id = l.id AND t.faite = 0))',
                [$userId, $aujourdhui]
            ) as $liste) {
                $reste = (int) $liste['reste'];
                $rappels[] = [
                    'nature' => 'liste', 'objet_id' => (int) $liste['id'],
                    'moment' => $matin->format('Y-m-d H:i:s'),
                    'message' => [
                        'title' => trim(($liste['icone'] ?: '📋') . ' Échéance aujourd’hui : ' . $liste['nom']),
                        'body' => $reste === 0 ? 'Tâche principale à terminer aujourd’hui'
                            : $reste . ' sous-tâche' . ($reste > 1 ? 's' : '') . ' encore à faire',
                        'url' => url('taches', ['liste' => (int) $liste['id']]),
                        'tag' => 'liste-' . (int) $liste['id'],
                    ],
                ];
            }
        }

        return $rappels;
    }

    /** « Dans 15 min · 14:00 – 15:00 », « Demain à 9:00 », « Aujourd'hui, toute la journée ». */
    private static function quand(DateTimeImmutable $debut, bool $journee, DateTimeImmutable $maintenant, ?DateTimeImmutable $fin): string
    {
        $jours = (int) $maintenant->setTime(0, 0)->diff($debut->setTime(0, 0))->format('%r%a');
        $jour = match ($jours) {
            0 => 'Aujourd’hui',
            1 => 'Demain',
            2 => 'Après-demain',
            default => ucfirst(date_fr($debut->format('Y-m-d'), false)),
        };

        if ($journee) {
            return $jour . ', toute la journée';
        }

        $horaire = $debut->format('H:i') . ($fin !== null && $fin > $debut && $fin->format('Y-m-d') === $debut->format('Y-m-d')
            ? ' – ' . $fin->format('H:i') : '');
        $minutes = (int) floor(($debut->getTimestamp() - $maintenant->getTimestamp()) / 60);

        if ($minutes <= 0) {
            return 'Maintenant · ' . $horaire;
        }
        if ($minutes < 60) {
            return 'Dans ' . $minutes . ' min · ' . $horaire;
        }
        if ($jours === 0) {
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;
            return 'Dans ' . $h . ' h' . ($m > 0 ? ' ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '') . ' · ' . $horaire;
        }

        return $jour . ' à ' . $horaire;
    }
}
