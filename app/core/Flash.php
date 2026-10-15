<?php
/**
 * SOURIANE — Messages flash (retour utilisateur après action)
 */

declare(strict_types=1);

namespace Souriane\Core;

final class Flash
{
    private const CLE = '_flash';

    public static function ajouter(string $type, string $message): void
    {
        $_SESSION[self::CLE][] = ['type' => $type, 'message' => $message];
    }

    public static function succes(string $message): void
    {
        self::ajouter('succes', $message);
    }

    public static function erreur(string $message): void
    {
        self::ajouter('erreur', $message);
    }

    public static function info(string $message): void
    {
        self::ajouter('info', $message);
    }

    public static function recuperer(): array
    {
        $messages = $_SESSION[self::CLE] ?? [];
        unset($_SESSION[self::CLE]);

        return $messages;
    }
}
