<?php
/**
 * SOURIANE — Paramètres applicatifs (clés de notification, réglages)
 */

declare(strict_types=1);

namespace Souriane\Models;

use Souriane\Core\Database as Db;

final class Parametre
{
    private static ?array $cache = null;

    public static function tous(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Db::instance()->select('SELECT * FROM parametres') as $ligne) {
                self::$cache[$ligne['cle']] = $ligne['valeur'];
            }
        }

        return self::$cache;
    }

    public static function lire(string $cle, ?string $defaut = null): ?string
    {
        $tous = self::tous();

        return $tous[$cle] ?? $defaut;
    }

    public static function ecrire(string $cle, ?string $valeur, ?string $libelle = null): void
    {
        Db::instance()->query(
            'INSERT INTO parametres (cle, valeur, libelle) VALUES (:c, :v, :l)
             ON DUPLICATE KEY UPDATE valeur = VALUES(valeur),
                                     libelle = COALESCE(VALUES(libelle), libelle)',
            ['c' => $cle, 'v' => $valeur, 'l' => $libelle]
        );
        self::$cache = null;
    }

    public static function lignes(): array
    {
        return Db::instance()->select('SELECT * FROM parametres ORDER BY cle ASC');
    }
}
