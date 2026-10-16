<?php
/**
 * SOURIANE — Limitation de débit (ch. 8)
 *
 * Fenêtre glissante par adresse réseau et par point d’entrée.
 * Stockage sur disque : aucune dépendance, aucun état en base.
 *
 * Deux besoins distincts, qu’il ne faut pas confondre :
 *
 *  - `verifier()` : limiter un DÉBIT. Chaque appel compte, réussi ou non.
 *    C’est le bon comportement pour une API publique.
 *
 *  - `bloque()` / `echec()` / `oublier()` : protéger une AUTHENTIFICATION
 *    contre la force brute. Seuls les ÉCHECS comptent, et une réussite remet
 *    le compteur à zéro. Sans cela, un utilisateur légitime se verrouille
 *    lui-même au bout de quelques connexions réussies.
 */

declare(strict_types=1);

namespace Souriane\Core;

final class RateLimiter
{
    /**
     * @param string $cle     Identifiant du point d’entrée (ex. « api.evenements »)
     * @param string $identite Adresse réseau ou jeton
     * @param int    $max      Nombre d’appels autorisés dans la fenêtre
     * @param int    $fenetre  Durée de la fenêtre en secondes
     *
     * @return array{autorise:bool, restant:int, reessayer_dans:int}
     */
    public static function verifier(string $cle, string $identite, int $max = RATE_LIMIT_MAX, int $fenetre = RATE_LIMIT_WINDOW): array
    {
        $fichier    = self::chemin($cle, $identite);
        $maintenant = time();
        $appels     = self::appelsRecents($fichier, $fenetre, $maintenant);

        if (count($appels) >= $max) {
            self::ecrire($fichier, $appels);

            return [
                'autorise'       => false,
                'restant'        => 0,
                'reessayer_dans' => self::attente($appels, $fenetre, $maintenant),
            ];
        }

        $appels[] = $maintenant;
        self::ecrire($fichier, $appels);

        return ['autorise' => true, 'restant' => max(0, $max - count($appels)), 'reessayer_dans' => 0];
    }

    /**
     * Contrôle d’accès qui NE consomme aucune tentative.
     *
     * À appeler avant de traiter la demande : une tentative refusée ne doit pas
     * compter une fois de plus, sinon le blocage ne fait que s’allonger à chaque
     * essai de l’utilisateur bloqué.
     *
     * @return array{autorise:bool, restant:int, reessayer_dans:int}
     */
    public static function bloque(string $cle, string $identite, int $max, int $fenetre): array
    {
        $fichier    = self::chemin($cle, $identite);
        $maintenant = time();
        $appels     = self::appelsRecents($fichier, $fenetre, $maintenant);

        if (count($appels) >= $max) {
            return [
                'autorise'       => false,
                'restant'        => 0,
                'reessayer_dans' => self::attente($appels, $fenetre, $maintenant),
            ];
        }

        return ['autorise' => true, 'restant' => max(0, $max - count($appels)), 'reessayer_dans' => 0];
    }

    /** Enregistre une tentative d’authentification échouée. */
    public static function echec(string $cle, string $identite, int $fenetre): void
    {
        $fichier    = self::chemin($cle, $identite);
        $maintenant = time();
        $appels     = self::appelsRecents($fichier, $fenetre, $maintenant);

        $appels[] = $maintenant;
        self::ecrire($fichier, $appels);
    }

    /** Efface le compteur : une réussite ne doit laisser aucune trace. */
    public static function oublier(string $cle, string $identite): void
    {
        $fichier = self::chemin($cle, $identite);
        if (is_file($fichier)) {
            @unlink($fichier);
        }
    }

    // -----------------------------------------------------------------------
    // Interne
    // -----------------------------------------------------------------------

    /** Chemin du compteur, créé au besoin. */
    private static function chemin(string $cle, string $identite): string
    {
        $dossier = STORAGE_PATH . '/limites';
        if (!is_dir($dossier)) {
            @mkdir($dossier, 0775, true);
        }

        return $dossier . '/' . hash('sha256', $cle . '|' . $identite) . '.json';
    }

    /** Appels encore compris dans la fenêtre courante. */
    private static function appelsRecents(string $fichier, int $fenetre, int $maintenant): array
    {
        if (!is_file($fichier)) {
            return [];
        }

        $brut   = @file_get_contents($fichier);
        $decode = $brut ? json_decode($brut, true) : null;

        if (!is_array($decode) || !isset($decode['appels']) || !is_array($decode['appels'])) {
            return [];
        }

        return array_values(array_filter(
            $decode['appels'],
            static fn ($t) => is_numeric($t) && ($maintenant - (int) $t) < $fenetre
        ));
    }

    /** Secondes à attendre avant que la plus ancienne tentative ne sorte. */
    private static function attente(array $appels, int $fenetre, int $maintenant): int
    {
        if ($appels === []) {
            return 0;
        }

        return max(1, $fenetre - ($maintenant - (int) min($appels)));
    }

    private static function ecrire(string $fichier, array $appels): void
    {
        @file_put_contents(
            $fichier,
            json_encode(['appels' => $appels, 'maj' => time()]),
            LOCK_EX
        );
    }
}
