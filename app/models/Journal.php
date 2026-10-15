<?php
/**
 * SOURIANE — Journal des opérations d’administration (EF-G05)
 */

declare(strict_types=1);

namespace Souriane\Models;

use Souriane\Core\Database as Db;

final class Journal
{
    public static function dernieres(int $limite = 60, array $filtres = []): array
    {
        $limite = max(1, min(500, $limite));

        $sql = 'SELECT j.*, u.nom AS utilisateur_nom, u.role
                FROM journal_operations j
                LEFT JOIN utilisateurs u ON u.id = j.utilisateur_id
                WHERE 1=1';
        $params = [];

        if (!empty($filtres['action'])) {
            $sql .= ' AND j.action = :action';
            $params['action'] = $filtres['action'];
        }

        $sql .= ' ORDER BY j.date DESC, j.id DESC LIMIT ' . $limite;

        return Db::instance()->select($sql, $params);
    }

    public static function actions(): array
    {
        return array_column(
            Db::instance()->select('SELECT DISTINCT action FROM journal_operations ORDER BY action'),
            'action'
        );
    }
}
