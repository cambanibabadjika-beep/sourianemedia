<?php
/**
 * SOURIANE — Moteur de vues
 * Gabarits PHP simples : une vue, un gabarit englobant facultatif.
 */

declare(strict_types=1);

namespace Souriane\Core;

use RuntimeException;

final class View
{
    /** Données partagées par toutes les vues. */
    private static array $partage = [];

    public static function partager(string $cle, mixed $valeur): void
    {
        self::$partage[$cle] = $valeur;
    }

    /**
     * Rend une vue et retourne le HTML.
     *
     * @param string      $vue    Chemin relatif sous app/views, sans .php
     * @param array       $donnees Variables exposées à la vue
     * @param string|null $layout Gabarit sous app/views/layouts
     */
    public static function rendre(string $vue, array $donnees = [], ?string $layout = null): string
    {
        $contenu = self::capturer($vue, $donnees);

        if ($layout === null) {
            return $contenu;
        }

        return self::capturer('layouts/' . $layout, array_merge($donnees, ['contenu' => $contenu]));
    }

    private static function capturer(string $vue, array $donnees): string
    {
        $chemin = SOURIANE_ROOT . '/app/views/' . $vue . '.php';
        if (!is_file($chemin)) {
            throw new RuntimeException('Vue introuvable : ' . $vue);
        }

        extract(array_merge(self::$partage, $donnees), EXTR_SKIP);

        ob_start();
        try {
            require $chemin;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
