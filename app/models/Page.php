<?php
/**
 * SOURIANE — Modèle Page publique et sections de contenu
 */

declare(strict_types=1);

namespace Souriane\Models;

use Souriane\Core\Database;

final class Page
{
    /** Ordre d’affichage imposé par le chapitre 5.2 du cahier des charges. */
    public const ORDRE_IMPOSE = [
        'offre',
        'presentation',
        'infos_complementaires',
        'galerie',
        'adresse',
        'horaires',
        'reseaux',
        'offres_a_venir',
        'video',
        'personnalise',
    ];

    public static function parSlug(string $slug): ?array
    {
        return Database::instance()->selectOne(
            "SELECT p.*, a.nom AS nom, a.nom AS annonceur_nom, a.description, a.logo, a.telephone,
                    a.whatsapp, a.email, a.adresse, a.ville, a.latitude, a.longitude, a.reseaux,
                    a.horaires, a.adresse_courte AS annonceur_slug, a.statut AS annonceur_statut
             FROM pages p
             JOIN annonceurs a ON a.id = p.annonceur_id
             WHERE p.adresse_courte = :slug
             LIMIT 1",
            ['slug' => $slug]
        );
    }

    public static function parAnnonceur(int $annonceurId): ?array
    {
        return Database::instance()->selectOne(
            'SELECT * FROM pages WHERE annonceur_id = :id LIMIT 1',
            ['id' => $annonceurId]
        );
    }

    public static function trouver(int $id): ?array
    {
        return Database::instance()->selectOne('SELECT * FROM pages WHERE id = :id', ['id' => $id]);
    }

    public static function toutes(): array
    {
        return Database::instance()->select(
            'SELECT p.*, a.nom AS annonceur_nom,
                    (SELECT COUNT(*) FROM sections_page s WHERE s.page_id = p.id AND s.actif = 1) AS nb_sections
             FROM pages p JOIN annonceurs a ON a.id = p.annonceur_id
             ORDER BY a.nom ASC'
        );
    }

    /** Sections actives d’une page, dans l’ordre imposé. */
    public static function sections(int $pageId): array
    {
        $sections = Database::instance()->select(
            'SELECT * FROM sections_page WHERE page_id = :id AND actif = 1 ORDER BY ordre ASC, id ASC',
            ['id' => $pageId]
        );

        // Réordonne selon l’ordre imposé, les types inconnus en fin
        usort($sections, static function (array $a, array $b): int {
            $ia = array_search($a['type'], self::ORDRE_IMPOSE, true);
            $ib = array_search($b['type'], self::ORDRE_IMPOSE, true);
            $ia = $ia === false ? 99 : $ia;
            $ib = $ib === false ? 99 : $ib;

            return $ia <=> $ib ?: ((int) $a['ordre'] <=> (int) $b['ordre']);
        });

        return $sections;
    }

    /** Toutes les sections, y compris inactives (administration). */
    public static function toutesSections(int $pageId): array
    {
        return Database::instance()->select(
            'SELECT * FROM sections_page WHERE page_id = :id ORDER BY ordre ASC, id ASC',
            ['id' => $pageId]
        );
    }

    public static function creer(array $donnees): int
    {
        return Database::instance()->insert('pages', $donnees);
    }

    public static function modifier(int $id, array $donnees): void
    {
        Database::instance()->update('pages', $donnees, 'id = :id', ['id' => $id]);
    }

    public static function enregistrerSection(int $pageId, string $type, ?string $titre, array $contenu, int $ordre, bool $actif): int
    {
        $db = Database::instance();
        $existante = $db->selectOne(
            'SELECT id FROM sections_page WHERE page_id = :p AND type = :t LIMIT 1',
            ['p' => $pageId, 't' => $type]
        );

        $donnees = [
            'titre'   => $titre,
            'contenu' => json_encode($contenu, JSON_UNESCAPED_UNICODE),
            'ordre'   => $ordre,
            'actif'   => $actif ? 1 : 0,
        ];

        if ($existante !== null) {
            $db->update('sections_page', $donnees, 'id = :id', ['id' => (int) $existante['id']]);

            return (int) $existante['id'];
        }

        return $db->insert('sections_page', array_merge($donnees, [
            'page_id' => $pageId,
            'type'    => $type,
        ]));
    }

    public static function supprimerSection(int $id): void
    {
        Database::instance()->delete('sections_page', 'id = :id', ['id' => $id]);
    }
}
