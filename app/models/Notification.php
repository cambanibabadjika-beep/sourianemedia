<?php
/**
 * SOURIANE — Modèle Notification et journal d’envoi
 */

declare(strict_types=1);

namespace Souriane\Models;

use Souriane\Core\Database as Db;

final class Notification
{
    public const STATUTS = ['brouillon', 'programmee', 'en_cours', 'envoyee', 'annulee'];

    public static function tous(array $filtres = []): array
    {
        $sql = 'SELECT n.*, a.nom AS annonceur_nom, u.nom AS auteur_nom,
                       (SELECT COUNT(*) FROM envois e WHERE e.notification_id = n.id) AS nb_envois,
                       (SELECT COUNT(*) FROM envois e WHERE e.notification_id = n.id AND e.statut IN (\'delivre\',\'clique\')) AS nb_delivres,
                       (SELECT COUNT(*) FROM envois e WHERE e.notification_id = n.id AND e.statut = \'clique\') AS nb_clics
                FROM notifications n
                JOIN annonceurs a ON a.id = n.annonceur_id
                LEFT JOIN utilisateurs u ON u.id = n.cree_par
                WHERE 1=1';
        $params = [];

        if (!empty($filtres['statut'])) {
            $sql .= ' AND n.statut = :statut';
            $params['statut'] = $filtres['statut'];
        }
        if (!empty($filtres['annonceur_id'])) {
            $sql .= ' AND n.annonceur_id = :aid';
            $params['aid'] = (int) $filtres['annonceur_id'];
        }

        return Db::instance()->select($sql . ' ORDER BY n.cree_le DESC', $params);
    }

    public static function trouver(int $id): ?array
    {
        return Db::instance()->selectOne(
            'SELECT n.*, a.nom AS annonceur_nom FROM notifications n
             JOIN annonceurs a ON a.id = n.annonceur_id WHERE n.id = :id',
            ['id' => $id]
        );
    }

    public static function creer(array $donnees): int
    {
        return Db::instance()->insert('notifications', $donnees);
    }

    public static function modifier(int $id, array $donnees): void
    {
        Db::instance()->update('notifications', $donnees, 'id = :id', ['id' => $id]);
    }

    public static function annuler(int $id): void
    {
        Db::instance()->update('notifications', ['statut' => 'annulee'], 'id = :id', ['id' => $id]);
    }

    /** Notifications programmées dont l’échéance est atteinte. */
    public static function aEnvoyer(int $limite = 20): array
    {
        return Db::instance()->select(
            "SELECT * FROM notifications
             WHERE statut = 'programmee' AND programmee_le <= NOW()
             ORDER BY programmee_le ASC LIMIT " . max(1, min(200, $limite))
        );
    }

    /** Compteurs d’envoi d’une notification (EF-G04). */
    public static function statistiquesEnvoi(int $notificationId): array
    {
        $lignes = Db::instance()->select(
            'SELECT statut, COUNT(*) AS total FROM envois WHERE notification_id = :id GROUP BY statut',
            ['id' => $notificationId]
        );

        $resultat = [
            'en_attente' => 0, 'envoye' => 0, 'delivre' => 0,
            'clique' => 0, 'echec' => 0, 'expire' => 0,
        ];
        foreach ($lignes as $l) {
            $resultat[$l['statut']] = (int) $l['total'];
        }
        $resultat['total'] = array_sum($resultat);

        return $resultat;
    }

    /** Journal des envois d’une notification. */
    public static function envois(int $notificationId): array
    {
        return Db::instance()->select(
            'SELECT e.*, ab.endpoint, ab.frequence
             FROM envois e JOIN abonnes ab ON ab.id = e.abonne_id
             WHERE e.notification_id = :id ORDER BY e.id ASC',
            ['id' => $notificationId]
        );
    }

    /** Crée les lignes d’envoi pour les abonnés actifs. */
    public static function preparerEnvois(int $notificationId, int $annonceurId): int
    {
        $db = Db::instance();
        $abonnes = Abonne::actifs($annonceurId);

        $crees = 0;
        foreach ($abonnes as $abonne) {
            $existe = (int) $db->selectValue(
                'SELECT COUNT(*) FROM envois WHERE notification_id = :n AND abonne_id = :a',
                ['n' => $notificationId, 'a' => (int) $abonne['id']],
                0
            );
            if ($existe === 0) {
                $db->insert('envois', [
                    'notification_id' => $notificationId,
                    'abonne_id'       => (int) $abonne['id'],
                    'statut'          => 'en_attente',
                ]);
                $crees++;
            }
        }

        return $crees;
    }
}
