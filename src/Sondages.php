<?php
declare(strict_types=1);

/**
 * Les sondages des discussions, entre amis ou de groupe.
 *
 * Un sondage est un message : sa ligne (table `messages` pour deux amis, `conversation_messages` pour un groupe) porte
 * `sondage_id`, et son texte est la question. Le reste — options, votes — est ici. Qui peut le voir et y répondre ? Qui voit
 * le message : un ami (tant qu'ils le sont), un membre du groupe (arrivé avant le message, qui ne l'a pas caché), et pas
 * quand l'auteur l'a supprimé pour tous. Tout passe par localiser().
 *
 * Voter change `reactions_le` du message : les pages déjà ouvertes relisent ce message et redessinent le sondage (comme pour
 * une réaction).
 */
final class Sondages
{
    public const QUESTION_MAX = 200;
    public const OPTION_MAX = 100;
    public const OPTIONS_MIN = 2;
    public const OPTIONS_MAX = 12;

    /**
     * Vérifie ce que le formulaire envoie. Les options vides sont ignorées (le formulaire en garde toujours une vide au bout).
     *
     * @param list<mixed> $options
     * @return array{0: ?array{question: string, options: list<string>}, 1: ?string} le sondage propre, ou null et la clé du refus
     */
    public static function valider(string $question, array $options): array
    {
        $net = static fn (string $t): string => trim((string) preg_replace('/[\s\p{C}]+/u', ' ', $t));
        $question = $net($question);
        if ($question === '' || mb_strlen($question) > self::QUESTION_MAX) {
            return [null, 'son.err.question'];
        }
        $propres = [];
        $vues = [];
        foreach ($options as $option) {
            if (!is_string($option)) {
                continue;
            }
            $option = $net($option);
            if ($option === '') {
                continue;
            }
            if (mb_strlen($option) > self::OPTION_MAX) {
                return [null, 'son.err.option_longue'];
            }
            $cle = mb_strtolower($option);
            if (isset($vues[$cle])) {
                return [null, 'son.err.doublon'];
            }
            $vues[$cle] = true;
            $propres[] = $option;
        }
        if (count($propres) < self::OPTIONS_MIN) {
            return [null, 'son.err.options_min'];
        }
        if (count($propres) > self::OPTIONS_MAX) {
            return [null, 'son.err.options_max'];
        }

        return [['question' => $question, 'options' => $propres], null];
    }

    /** Un sondage dans une discussion avec un ami. @return array{0: ?int, 1: ?string} le message, ou la raison du refus */
    public static function creerAmis(int $moi, int $autre, array $propre, bool $multiple): array
    {
        if (!Amis::sontAmis($moi, $autre)) {
            return [null, t('msg.amis_seuls')];
        }
        if (self::tropVite('messages', $moi)) {
            return [null, t('msg.trop_vite')];
        }

        return [self::inserer('amis', $moi, null, $autre, $propre, $multiple), null];
    }

    /** Un sondage dans un groupe. @return array{0: ?int, 1: ?string} */
    public static function creerGroupe(int $moi, int $conversation, array $propre, bool $multiple): array
    {
        if (Conversations::membre($conversation, $moi) === null) {
            return [null, t('grp.pas_membre_vous')];
        }
        if (self::tropVite('conversation_messages', $moi)) {
            return [null, t('msg.trop_vite')];
        }

        return [self::inserer('groupes', $moi, $conversation, null, $propre, $multiple), null];
    }

    /** Autant de messages à la minute qu'ailleurs : un sondage en est un. */
    private static function tropVite(string $table, int $moi): bool
    {
        return (int) Database::valeur(
            'SELECT COUNT(*) FROM ' . $table . ' WHERE expediteur_id = ? AND evenement IS NULL AND created_at >= UTC_TIMESTAMP() - INTERVAL 1 MINUTE',
            [$moi]
        ) >= Amis::MESSAGES_PAR_MINUTE;
    }

    private static function inserer(string $canal, int $moi, ?int $conversation, ?int $autre, array $propre, bool $multiple): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'INSERT INTO sondages (canal, conversation_id, createur_id, destinataire_id, question, multiple, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$canal, $conversation, $moi, $autre, $propre['question'], $multiple ? 1 : 0]
            );
            $id = Database::dernierId();
            foreach ($propre['options'] as $rang => $texte) {
                Database::run('INSERT INTO sondage_options (sondage_id, texte, position) VALUES (?, ?, ?)', [$id, $texte, $rang]);
            }
            if ($canal === 'amis') {
                Database::run(
                    'INSERT INTO messages (expediteur_id, destinataire_id, texte, sondage_id, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
                    [$moi, $autre, $propre['question'], $id]
                );
                $message = Database::dernierId();
            } else {
                Database::run(
                    'INSERT INTO conversation_messages (conversation_id, expediteur_id, texte, sondage_id, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
                    [$conversation, $moi, $propre['question'], $id]
                );
                $message = Database::dernierId();
                Database::run(
                    'UPDATE conversation_membres SET lu_jusqua = GREATEST(lu_jusqua, ?) WHERE conversation_id = ? AND user_id = ?',
                    [$message, $conversation, $moi]
                );
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $message;
    }

    /**
     * Le sondage et le message qui le porte, si la personne peut le voir.
     *
     * @return ?array{sondage: array, message_id: int, table: string}
     */
    private static function localiser(int $sondageId, int $moi): ?array
    {
        $sondage = Database::one('SELECT * FROM sondages WHERE id = ?', [$sondageId]);
        if ($sondage === null) {
            return null;
        }
        if ($sondage['canal'] === 'amis') {
            $message = Database::one(
                'SELECT id, expediteur_id, destinataire_id FROM messages
                  WHERE sondage_id = ? AND supprime_le IS NULL AND evenement IS NULL
                    AND ((expediteur_id = ? AND masque_expediteur = 0) OR (destinataire_id = ? AND masque_destinataire = 0))',
                [$sondageId, $moi, $moi]
            );
            if ($message === null) {
                return null;
            }
            $autre = (int) $message['expediteur_id'] === $moi ? (int) $message['destinataire_id'] : (int) $message['expediteur_id'];

            return Amis::sontAmis($moi, $autre) ? ['sondage' => $sondage, 'message_id' => (int) $message['id'], 'table' => 'messages'] : null;
        }
        $message = Database::one(
            'SELECT m.id FROM conversation_messages m
               JOIN conversation_membres mb ON mb.conversation_id = m.conversation_id AND mb.user_id = ?
              WHERE m.sondage_id = ? AND m.supprime_le IS NULL AND m.id > mb.depuis_message
                AND NOT EXISTS (SELECT 1 FROM conversation_masques x WHERE x.message_id = m.id AND x.user_id = ?)',
            [$moi, $sondageId, $moi]
        );

        return $message === null ? null : ['sondage' => $sondage, 'message_id' => (int) $message['id'], 'table' => 'conversation_messages'];
    }

    /**
     * Le sondage tel que la page le montre : sa question, ses options avec leurs votes, et ce qu'a voté la personne. Les
     * pseudos de qui a voté quoi servent d'infobulle ; « vous » pour soi.
     *
     * @return ?array{id: int, question: string, multiple: bool, votants: int, options: list<array>, url: string}
     */
    public static function pourAffichage(int $sondageId, int $moi): ?array
    {
        $sondage = Database::one('SELECT id, question, multiple FROM sondages WHERE id = ?', [$sondageId]);
        if ($sondage === null) {
            return null;
        }
        $options = Database::all('SELECT id, texte FROM sondage_options WHERE sondage_id = ? ORDER BY position, id', [$sondageId]);
        $votes = Database::all('SELECT option_id, user_id FROM sondage_votes WHERE sondage_id = ? ORDER BY created_at, user_id', [$sondageId]);

        $parOption = [];
        $votants = [];
        foreach ($votes as $v) {
            $parOption[(int) $v['option_id']][] = (int) $v['user_id'];
            $votants[(int) $v['user_id']] = true;
        }
        $pseudos = [];
        foreach (array_keys($votants) as $uid) {
            $pseudos[$uid] = $uid === $moi ? t('msg.vous') : (string) (Amis::compte($uid)['pseudo'] ?? t('grpevt.ancien_membre'));
        }

        return [
            'id' => (int) $sondage['id'],
            'question' => (string) $sondage['question'],
            'multiple' => (int) $sondage['multiple'] === 1,
            'votants' => count($votants),
            'options' => array_map(static function (array $o) use ($parOption, $pseudos, $moi): array {
                $qui = $parOption[(int) $o['id']] ?? [];
                // « Vous » en tête, puis les autres dans l'ordre des votes.
                usort($qui, static fn (int $a, int $b): int => (int) ($b === $moi) <=> (int) ($a === $moi));

                return [
                    'id' => (int) $o['id'],
                    'texte' => (string) $o['texte'],
                    'nombre' => count($qui),
                    'moi' => in_array($moi, $qui, true),
                    'qui' => implode(', ', array_map(static fn (int $u): string => $pseudos[$u], $qui)),
                ];
            }, $options),
            'url' => url('sondages/' . (int) $sondage['id'] . '/voter'),
        ];
    }

    /**
     * Les sondages portés par des messages, par identifiant de message — pour redessiner ceux qui ont changé.
     *
     * @param list<int> $messageIds
     * @return array<int, array>
     */
    public static function pourMessages(string $table, array $messageIds, int $moi): array
    {
        if ($messageIds === [] || !in_array($table, ['messages', 'conversation_messages'], true)) {
            return [];
        }
        $lignes = Database::all(
            'SELECT id, sondage_id FROM ' . $table . ' WHERE sondage_id IS NOT NULL AND supprime_le IS NULL AND id IN (' . implode(',', array_fill(0, count($messageIds), '?')) . ')',
            array_values($messageIds)
        );
        $rendu = [];
        foreach ($lignes as $l) {
            $s = self::pourAffichage((int) $l['sondage_id'], $moi);
            if ($s !== null) {
                $rendu[(int) $l['id']] = $s;
            }
        }

        return $rendu;
    }

    /**
     * Enregistre ce que la personne coche : l'ensemble de ses réponses remplace le précédent (vide : elle retire son vote).
     * Un sondage à réponse unique n'en accepte qu'une.
     *
     * @param list<mixed> $optionIds
     * @return array{0: bool, 1: string, 2: ?array} réussi ou non, le message à montrer, et le sondage à jour
     */
    public static function voter(int $moi, int $sondageId, array $optionIds): array
    {
        $trouve = self::localiser($sondageId, $moi);
        if ($trouve === null) {
            return [false, t('son.err.introuvable'), null];
        }
        $valides = array_map('intval', array_column(Database::all('SELECT id FROM sondage_options WHERE sondage_id = ?', [$sondageId]), 'id'));
        $choisies = [];
        foreach ($optionIds as $o) {
            $o = is_scalar($o) ? (int) $o : 0;
            if (!in_array($o, $valides, true)) {
                return [false, t('son.err.option'), null];
            }
            if (!in_array($o, $choisies, true)) {
                $choisies[] = $o;
            }
        }
        if (count($choisies) > 1 && (int) $trouve['sondage']['multiple'] !== 1) {
            return [false, t('son.err.une_reponse'), null];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $avant = array_map('intval', array_column(Database::all('SELECT option_id FROM sondage_votes WHERE sondage_id = ? AND user_id = ?', [$sondageId, $moi]), 'option_id'));
            foreach (array_diff($avant, $choisies) as $retire) {
                Database::run('DELETE FROM sondage_votes WHERE option_id = ? AND user_id = ?', [$retire, $moi]);
            }
            foreach (array_diff($choisies, $avant) as $ajoute) {
                Database::run('INSERT IGNORE INTO sondage_votes (option_id, user_id, sondage_id, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())', [$ajoute, $moi, $sondageId]);
            }
            if (array_diff($avant, $choisies) !== [] || array_diff($choisies, $avant) !== []) {
                Database::run('UPDATE ' . $trouve['table'] . ' SET reactions_le = UTC_TIMESTAMP() WHERE id = ?', [$trouve['message_id']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return [true, '', self::pourAffichage($sondageId, $moi)];
    }

    /** Le sondage d'un message effacé pour tous s'efface aussi (ses options et ses votes avec lui). */
    public static function supprimer(?int $sondageId): void
    {
        if ($sondageId !== null && $sondageId > 0) {
            Database::run('DELETE FROM sondages WHERE id = ?', [$sondageId]);
        }
    }
}
