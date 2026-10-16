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
     * @param int|null $annonceurId Annonceur suivi, ou null si la portée couvre
     *                             toute la plateforme
     * @param string   $portee      'annonceur' (défaut) ou 'plateforme'
     *
     * @return int Identifiant de l’abonné
     */
    public static function enregistrer(
        ?int $annonceurId,
        string $endpoint,
        string $cleP256dh,
        string $cleAuth,
        string $frequence = 'chaque_annonce',
        string $portee = 'annonceur'
    ): int {
        $db = Db::instance();
        $hash = hash('sha256', $endpoint);

        if (!in_array($frequence, ['chaque_annonce', 'hebdomadaire'], true)) {
            $frequence = 'chaque_annonce';
        }
        if (!in_array($portee, ['annonceur', 'plateforme'], true)) {
            $portee = 'annonceur';
        }

        // Un abonnement « plateforme » n’est rattaché à aucun annonceur : la
        // colonne accepte NULL, la clé étrangère reste donc valable.
        $annonceurStocke = $portee === 'plateforme' ? null : $annonceurId;

        $existant = $db->selectOne('SELECT * FROM abonnes WHERE endpoint_hash = :h', ['h' => $hash]);

        if ($existant !== null) {
            $db->update('abonnes', [
                'annonceur_id' => $annonceurStocke,
                'portee'       => $portee,
                'cle_p256dh'   => $cleP256dh,
                'cle_auth'     => $cleAuth,
                'frequence'    => $frequence,
                'statut'       => 'actif',
            ], 'id = :id', ['id' => (int) $existant['id']]);

            return (int) $existant['id'];
        }

        return $db->insert('abonnes', [
            'annonceur_id'    => $annonceurStocke,
            'portee'          => $portee,
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

    /**
     * Abonnés à servir pour un envoi donné.
     *
     * Un abonné « plateforme » reçoit les offres de tous les annonceurs : il
     * est donc inclus quel que soit l’annonceur concerné. Un abonné
     * « annonceur » ne reçoit que celles de son annonceur.
     */
    public static function actifs(int $annonceurId, string $frequence = 'chaque_annonce'): array
    {
        $sql = "SELECT * FROM abonnes
                WHERE statut = 'actif'
                  AND (annonceur_id = :id OR portee = 'plateforme')";
        $params = ['id' => $annonceurId];

        if ($frequence === 'chaque_annonce') {
            $sql .= " AND frequence = 'chaque_annonce'";
        }

        return Db::instance()->select($sql, $params);
    }

    /** Nombre d’abonnés couvrant toute la plateforme. */
    public static function compterPlateforme(string $statut = 'actif'): int
    {
        return (int) Db::instance()->selectValue(
            "SELECT COUNT(*) FROM abonnes WHERE portee = 'plateforme' AND statut = :s",
            ['s' => $statut],
            0
        );
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
