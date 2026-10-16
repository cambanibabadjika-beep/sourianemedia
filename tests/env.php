<?php
/**
 * SOURIANE — Lecture du fichier .env pour les suites de vérification.
 *
 * Les suites lisaient le .env avec parse_ini_file(). Or ce parseur refuse les
 * caractères réservés de l'ini — ( ) { } [ | & ~ ! ^ " $ — jusque dans les
 * commentaires introduits par #. Le .env.example recopié en .env, comme le
 * demande le README, contient de la prose française avec des parenthèses et
 * des guillemets : parse_ini_file() échouait alors sur le fichier entier et
 * rendait un tableau vide, sans que rien ne le signale.
 *
 * Conséquence mesurée : CRON_TOKEN et les clés VAPID n'étaient jamais lus, et
 * quatre vérifications échouaient — le jeton de la tâche planifiée, le
 * masquage de la clé privée, son intégrité après écriture, et sa transmission
 * au client. Non pas parce que l'application était fautive, mais parce que le
 * harnais ne lisait pas le même fichier de la même façon qu'elle.
 *
 * Cette fonction reprend la lecture de l'application telle quelle
 * (app/config/config.php, fonction env_load) : un seul lecteur, donc plus de
 * divergence possible entre ce que l'application voit et ce que les tests
 * voient.
 */

declare(strict_types=1);

/*
 * Le fuseau de l'application est fixé dans app/bootstrap.php. Le harnais doit
 * appliquer le même, pour la même raison que ci-dessus : s'il ne le fait pas,
 * il ne voit pas ce que voit l'application.
 *
 * Mesure du 15/10/2026 : php.ini de cette machine définit date.timezone =
 * Europe/Berlin (UTC+2 en été) tandis que l'application force Africa/Dakar
 * (UTC+0). Entre 22 h et minuit UTC, les deux désignent deux jours différents.
 * verifier_cron.php cherchait alors storage/logs/app-<lendemain>.log, que
 * l'application n'écrit évidemment pas, et l'assertion « le refus est consigné
 * dans le journal technique » échouait — sans que rien ne soit cassé dans le
 * code. Le défaut ne se manifestait donc que deux heures par jour.
 */
date_default_timezone_set('Africa/Dakar');

/**
 * Lit un fichier .env et rend les couples clé => valeur.
 *
 * @return array<string, string>
 */
function env_fichier(string $chemin): array
{
    $valeurs = [];

    if (!is_readable($chemin)) {
        return $valeurs;
    }

    $lignes = file($chemin, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lignes === false) {
        return $valeurs;
    }

    foreach ($lignes as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || str_starts_with($ligne, '#')) {
            continue;
        }
        if (!str_contains($ligne, '=')) {
            continue;
        }

        [$cle, $valeur] = explode('=', $ligne, 2);
        $cle    = trim($cle);
        $valeur = trim($valeur);

        // Retire les guillemets encadrants, comme env_load().
        if (strlen($valeur) >= 2) {
            $premier = $valeur[0];
            $dernier = $valeur[strlen($valeur) - 1];
            if (($premier === '"' && $dernier === '"')
                || ($premier === "'" && $dernier === "'")) {
                $valeur = substr($valeur, 1, -1);
            }
        }

        $valeurs[$cle] = $valeur;
    }

    return $valeurs;
}
