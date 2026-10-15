<?php
/**
 * SOURIANE — Modèle Demande de proposition (prospects).
 *
 * Une demande est déposée par un visiteur depuis la page publique
 * /annonceurs/demande, puis traitée par la régie dans le back-office.
 */

declare(strict_types=1);

namespace Souriane\Models;

use Souriane\Core\Database;

final class Demande
{
    /** Statuts autorisés (doivent exister dans ETATS_LIBELLES). */
    public const STATUTS = ['nouveau', 'contacte', 'converti', 'archive'];

    /** Liste des demandes, du plus récent au plus ancien. */
    public static function tous(array $filtres = []): array
    {
        $sql    = 'SELECT * FROM demandes WHERE 1=1';
        $params = [];

        if (!empty($filtres['statut'])) {
            $sql              .= ' AND statut = :statut';
            $params['statut'] = $filtres['statut'];
        }

        $sql .= ' ORDER BY cree_le DESC';

        return Database::instance()->select($sql, $params);
    }

    /** Demande unique, ou null si introuvable. */
    public static function trouver(int $id): ?array
    {
        return Database::instance()->selectOne('SELECT * FROM demandes WHERE id = :id', ['id' => $id]);
    }

    /** Enregistre une nouvelle demande et renvoie son identifiant. */
    public static function creer(array $donnees): int
    {
        return Database::instance()->insert('demandes', $donnees);
    }

    /** Met à jour le statut d’une demande. */
    public static function changerStatut(int $id, string $statut): void
    {
        Database::instance()->update('demandes', ['statut' => $statut], 'id = :id', ['id' => $id]);
    }

    /** Nombre de demandes par statut (pour les filtres du back-office). */
    public static function compterParStatut(): array
    {
        $lignes = Database::instance()->select(
            'SELECT statut, COUNT(*) AS nb FROM demandes GROUP BY statut'
        );

        $totaux = [];
        foreach (self::STATUTS as $statut) {
            $totaux[$statut] = 0;
        }
        foreach ($lignes as $ligne) {
            $totaux[$ligne['statut']] = (int) $ligne['nb'];
        }

        return $totaux;
    }

    /** Nombre de demandes encore non traitées (pastille du menu). */
    public static function compterNouvelles(): int
    {
        return (int) Database::instance()->selectValue(
            "SELECT COUNT(*) FROM demandes WHERE statut = 'nouveau'",
            [],
            0
        );
    }
}
