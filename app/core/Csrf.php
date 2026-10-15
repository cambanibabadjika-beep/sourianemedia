<?php
/**
 * SOURIANE — Protection contre la falsification de requête (CSRF)
 * Chaque formulaire porteur d’action est protégé (ch. 8).
 */

declare(strict_types=1);

namespace Souriane\Core;

final class Csrf
{
    private const CLE = '_csrf_token';

    public static function jeton(): string
    {
        if (empty($_SESSION[self::CLE])) {
            $_SESSION[self::CLE] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CLE];
    }

    /** Champ caché à insérer dans chaque formulaire. */
    public static function champ(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::jeton()) . '">';
    }

    public static function valide(?string $jeton): bool
    {
        if ($jeton === null || $jeton === '' || empty($_SESSION[self::CLE])) {
            return false;
        }

        return hash_equals((string) $_SESSION[self::CLE], $jeton);
    }

    /**
     * Vérifie la requête entrante. En cas d’échec, renvoie une erreur 419.
     */
    public static function verifier(Request $request): ?Response
    {
        $jeton = $request->input('_csrf', $request->server['HTTP_X_CSRF_TOKEN'] ?? null);

        if (!self::valide(is_string($jeton) ? $jeton : null)) {
            journal_technique('securite', 'Jeton CSRF invalide', [
                'chemin' => $request->path,
                'ip'     => $request->ip(),
            ]);

            if ($request->estJson() || $request->estAjax()) {
                return Response::json(['erreur' => 'Jeton de sécurité invalide ou expiré.'], 419);
            }

            return Response::html(
                '<h1>419 — Session expirée</h1><p>Rechargez la page puis réessayez.</p>',
                419
            );
        }

        return null;
    }
}
