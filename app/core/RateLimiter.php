<?php
/**
 * SOURIANE — Limitation de débit (ch. 8)
 *
 * Fenêtre glissante par adresse réseau et par point d’entrée.
 * Stockage sur disque : aucune dépendance, aucun état en base.
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
        $dossier = STORAGE_PATH . '/limites';
        if (!is_dir($dossier)) {
            @mkdir($dossier, 0775, true);
        }

        $fichier = $dossier . '/' . hash('sha256', $cle . '|' . $identite) . '.json';
        $maintenant = time();

        $donnees = ['appels' => []];
        if (is_file($fichier)) {
            $brut = @file_get_contents($fichier);
            $decode = $brut ? json_decode($brut, true) : null;
            if (is_array($decode) && isset($decode['appels']) && is_array($decode['appels'])) {
                $donnees = $decode;
            }
        }

        // Ne conserve que les appels de la fenêtre courante
        $appels = array_values(array_filter(
            $donnees['appels'],
            static fn ($t) => is_numeric($t) && ($maintenant - (int) $t) < $fenetre
        ));

        $restant = max(0, $max - count($appels));

        if (count($appels) >= $max) {
            $plus_ancien = (int) min($appels);
            $reessayer = max(1, $fenetre - ($maintenant - $plus_ancien));
            self::ecrire($fichier, $appels);

            return ['autorise' => false, 'restant' => 0, 'reessayer_dans' => $reessayer];
        }

        $appels[] = $maintenant;
        self::ecrire($fichier, $appels);

        return ['autorise' => true, 'restant' => max(0, $max - count($appels)), 'reessayer_dans' => 0];
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
