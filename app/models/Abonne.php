<?php
/**
 * SOURIANE — Modèle Abonné aux notifications
 * Consentement explicite, désinscription en un clic (ch. 8).
 */

declare(strict_types=1);

namespace Souriane\Models;

use Souriane\Core\Database as Db;

final class Abonne
{
    /**
     * Enregistre ou réactive un abonnement.
     *
     * @return int Identifiant de l’abonné
     */
    public static function enregistrer(
        int $annonceurId,
        string $endpoint,
        string $cleP256dh,
        string $cleAuth,
        string $frequence = 'chaque_annonce'
    ): int {
        $db = Db::instance();
        $hash = hash('sha256', $endpoint);

        if (!in_array($frequence, ['chaque_annonce', 'hebdomadaire'], true)) {
            $frequence = 'chaque_annonce';
        }

        $existant = $db->selectOne('SELECT * FROM abonnes WHERE endpoint_hash = :h', ['h' => $hash]);

        if ($existant !== null) {
            $db->update('abonnes', [
                'annonceur_id' => $annonceurId,
                'cle_p256dh'   => $cleP256dh,
                'cle_auth'     => $cleAuth,
                'frequence'    => $frequence,
                'statut'       => 'actif',
            ], 'id = :id', ['id' => (int) $existant['id']]);

            return (int) $existant['id'];
        }

        return $db->insert('abonnes', [
            'annonceur_id'    => $annonceurId,
            'endpoint'        => $endpoint,
            'endpoint_hash'   => $hash,
            'cle_p256dh'      => $cleP256dh,
            'cle_auth'        => $cleAuth,
            'frequence'       => $frequence,
            'statut'          => 'actif',
            'consentement_le' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Désactive un abonnement (désinscription en un clic). */
    public static function desactiverParEndpoint(string $endpoint): int
    {
        return Db::instance()->update(
            'abonnes',
            ['statut' => 'desabonne'],
            'endpoint_hash = :h',
            ['h' => hash('sha256', $endpoint)]
        );
    }

    public static function desactiver(int $id): void
    {
        Db::instance()->update('abonnes', ['statut' => 'desabonne'], 'id = :id', ['id' => $id]);
    }

    /** Suppression définitive (droit à l’effacement, ch. 8). */
    public static function supprimer(int $id): void
    {
        Db::instance()->delete('abonnes', 'id = :id', ['id' => $id]);
    }

    /** Abonnés actifs d’un annonceur. */
    public static function actifs(int $annonceurId, string $frequence = 'chaque_annonce'): array
    {
        $sql = "SELECT * FROM abonnes WHERE annonceur_id = :id AND statut = 'actif'";
        $params = ['id' => $annonceurId];

        if ($frequence === 'chaque_annonce') {
            $sql .= " AND frequence = 'chaque_annonce'";
        }

        return Db::instance()->select($sql, $params);
    }

    public static function tous(array $filtres = []): array
    {
        $sql = 'SELECT ab.*, a.nom AS annonceur_nom
                FROM abonnes ab JOIN annonceurs a ON a.id = ab.annonceur_id WHERE 1=1';
        $params = [];

        if (!empty($filtres['annonceur_id'])) {
            $sql .= ' AND ab.annonceur_id = :aid';
            $params['aid'] = (int) $filtres['annonceur_id'];
        }
        if (!empty($filtres['statut'])) {
            $sql .= ' AND ab.statut = :statut';
            $params['statut'] = $filtres['statut'];
        }

        return Db::instance()->select($sql . ' ORDER BY ab.cree_le DESC', $params);
    }

    public static function compter(int $annonceurId, string $statut = 'actif'): int
    {
        return (int) Db::instance()->selectValue(
            'SELECT COUNT(*) FROM abonnes WHERE annonceur_id = :id AND statut = :s',
            ['id' => $annonceurId, 's' => $statut],
            0
        );
    }

    /**
     * Nombre de notifications déjà envoyées à un annonceur sur le mois courant.
     * Sert au plafond de fréquence (ch. 5.5).
     */
    public static function envoisMoisCourant(int $annonceurId): int
    {
        return (int) Db::instance()->selectValue(
            "SELECT COUNT(*) FROM notifications
             WHERE annonceur_id = :id
               AND statut = 'envoyee'
               AND envoyee_le >= DATE_FORMAT(CURDATE(), '%Y-%m-01')",
            ['id' => $annonceurId],
            0
        );
    }
}
