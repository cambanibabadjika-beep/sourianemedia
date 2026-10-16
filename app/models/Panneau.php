<?php
/**
 * SOURIANE — Modèle Panneau (parc de supports physiques)
 */

declare(strict_types=1);

namespace Souriane\Models;

use Souriane\Core\Database as Db;

final class Panneau
{
    public static function tous(array $filtres = []): array
    {
        $sql = 'SELECT p.*,
                    (SELECT COUNT(*) FROM campagne_panneaux cp
                     WHERE cp.panneau_id = p.id AND cp.date_fin >= CURDATE()) AS nb_campagnes_actives
                FROM panneaux p WHERE 1=1';
        $params = [];

        if (!empty($filtres['statut'])) {
            $sql .= ' AND p.statut = :statut';
            $params['statut'] = $filtres['statut'];
        }
        if (!empty($filtres['ville'])) {
            $sql .= ' AND p.ville = :ville';
            $params['ville'] = $filtres['ville'];
        }
        if (!empty($filtres['format'])) {
            $sql .= ' AND p.format = :format';
            $params['format'] = $filtres['format'];
        }
        if (!empty($filtres['recherche'])) {
            $sql .= ' AND (p.reference LIKE :q OR p.libelle LIKE :q OR p.adresse LIKE :q)';
            $params['q'] = '%' . $filtres['recherche'] . '%';
        }
        if (!empty($filtres['disponible_seulement'])) {
            $sql .= ' AND p.statut IN (\'disponible\')';
        }
        /*
         * Les supports retirés du parc n’ont pas leur place dans une vitrine
         * commerciale. Ils restent consultables en les demandant
         * explicitement — l’administration, elle, n’exclut rien : elle doit
         * voir le parc tel qu’il est, archivés compris.
         */
        if (!empty($filtres['exclure_archive'])) {
            $sql .= " AND p.statut <> 'archive'";
        }

        $sql .= ' ORDER BY p.reference ASC';

        return Db::instance()->select($sql, $params);
    }

    public static function trouver(int $id): ?array
    {
        return Db::instance()->selectOne('SELECT * FROM panneaux WHERE id = :id', ['id' => $id]);
    }

    public static function parReference(string $reference): ?array
    {
        return Db::instance()->selectOne('SELECT * FROM panneaux WHERE reference = :r', ['r' => $reference]);
    }

    public static function creer(array $donnees): int
    {
        return Db::instance()->insert('panneaux', $donnees);
    }

    public static function modifier(int $id, array $donnees): void
    {
        Db::instance()->update('panneaux', $donnees, 'id = :id', ['id' => $id]);
    }

    public static function compter(array $filtres = []): int
    {
        $sql = 'SELECT COUNT(*) FROM panneaux WHERE statut <> \'archive\'';
        $params = [];
        if (!empty($filtres['ville'])) {
            $sql .= ' AND ville = :ville';
            $params['ville'] = $filtres['ville'];
        }

        return (int) Db::instance()->selectValue($sql, $params, 0);
    }

    public static function parVille(): array
    {
        return Db::instance()->select(
            'SELECT ville, COUNT(*) AS total,
                    SUM(statut = \'disponible\') AS disponibles
             FROM panneaux WHERE statut <> \'archive\' AND ville IS NOT NULL
             GROUP BY ville ORDER BY total DESC'
        );
    }

    public static function formats(): array
    {
        return array_column(
            Db::instance()->select('SELECT DISTINCT format FROM panneaux WHERE format IS NOT NULL ORDER BY format'),
            'format'
        );
    }

    public static function villes(): array
    {
        return array_column(
            Db::instance()->select('SELECT DISTINCT ville FROM panneaux WHERE ville IS NOT NULL ORDER BY ville'),
            'ville'
        );
    }

    /**
     * Vérifie la disponibilité d’un panneau sur une période (EF-B05).
     * La lecture est verrouillée (FOR UPDATE) pour empêcher la double réservation.
     */
    public static function disponibleSurPeriode(int $panneauId, string $debut, string $fin, ?int $campagneIgnoree = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM campagne_panneaux cp
                JOIN campagnes c ON c.id = cp.campagne_id
                WHERE cp.panneau_id = :pid
                  AND c.statut IN (\'reservee\',\'en_cours\')
                  AND NOT (cp.date_fin < :debut OR cp.date_debut > :fin)';
        $params = ['pid' => $panneauId, 'debut' => $debut, 'fin' => $fin];

        if ($campagneIgnoree !== null) {
            $sql .= ' AND cp.campagne_id <> :cid';
            $params['cid'] = $campagneIgnoree;
        }

        return (int) Db::instance()->selectValue($sql, $params, 0) === 0;
    }

    /** Calendrier de disponibilité d’un panneau (EF-A04). */
    public static function calendrier(int $panneauId, int $mois = 6): array
    {
        $mois = max(1, min(24, $mois));

        return Db::instance()->select(
            "SELECT c.nom AS campagne, a.nom AS annonceur, cp.date_debut, cp.date_fin, c.statut
             FROM campagne_panneaux cp
             JOIN campagnes c ON c.id = cp.campagne_id
             JOIN annonceurs a ON a.id = c.annonceur_id
             WHERE cp.panneau_id = :pid
               AND cp.date_fin >= CURDATE()
               AND cp.date_debut <= DATE_ADD(CURDATE(), INTERVAL {$mois} MONTH)
               AND c.statut <> 'annulee'
             ORDER BY cp.date_debut ASC",
            ['pid' => $panneauId]
        );
    }
}
