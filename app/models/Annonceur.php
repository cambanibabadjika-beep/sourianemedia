<?php
/**
 * SOURIANE — Modèle Annonceur et page publique
 */

declare(strict_types=1);

namespace Souriane\Models;

use Souriane\Core\Database;

final class Annonceur
{
    public static function tous(array $filtres = []): array
    {
        $sql = 'SELECT a.*,
                       (SELECT COUNT(*) FROM campagnes c WHERE c.annonceur_id = a.id) AS nb_campagnes,
                       (SELECT COUNT(*) FROM abonnes ab WHERE ab.annonceur_id = a.id AND ab.statut = \'actif\') AS nb_abonnes
                FROM annonceurs a WHERE 1=1';
        $params = [];

        if (!empty($filtres['statut'])) {
            $sql .= ' AND a.statut = :statut';
            $params['statut'] = $filtres['statut'];
        }
        if (!empty($filtres['recherche'])) {
            $sql .= ' AND (a.nom LIKE :q OR a.ville LIKE :q OR a.adresse_courte LIKE :q)';
            $params['q'] = '%' . $filtres['recherche'] . '%';
        }

        $sql .= ' ORDER BY a.nom ASC';

        return Database::instance()->select($sql, $params);
    }

    public static function trouver(int $id): ?array
    {
        return Database::instance()->selectOne('SELECT * FROM annonceurs WHERE id = :id', ['id' => $id]);
    }

    public static function parSlug(string $slug): ?array
    {
        return Database::instance()->selectOne(
            'SELECT * FROM annonceurs WHERE adresse_courte = :slug AND statut = \'actif\' LIMIT 1',
            ['slug' => $slug]
        );
    }

    public static function creer(array $donnees): int
    {
        $donnees['adresse_courte'] = self::slugUnique($donnees['adresse_courte'] ?? $donnees['nom']);

        return Database::instance()->insert('annonceurs', $donnees);
    }

    public static function modifier(int $id, array $donnees): void
    {
        if (isset($donnees['adresse_courte'])) {
            $donnees['adresse_courte'] = self::slugUnique($donnees['adresse_courte'], $id);
        }
        Database::instance()->update('annonceurs', $donnees, 'id = :id', ['id' => $id]);
    }

    public static function compter(): int
    {
        return (int) Database::instance()->selectValue('SELECT COUNT(*) FROM annonceurs WHERE statut <> \'archive\'', [], 0);
    }

    /** Garantit l’unicité du slug d’URL. */
    public static function slugUnique(string $base, ?int $ignorer = null): string
    {
        $slug = slugify($base) ?: 'annonceur';
        $essai = $slug;
        $i = 2;

        while (true) {
            $sql = 'SELECT COUNT(*) FROM annonceurs WHERE adresse_courte = :slug';
            $params = ['slug' => $essai];
            if ($ignorer !== null) {
                $sql .= ' AND id <> :id';
                $params['id'] = $ignorer;
            }
            if ((int) Database::instance()->selectValue($sql, $params, 0) === 0) {
                return $essai;
            }
            $essai = $slug . '-' . $i++;
        }
    }
}
