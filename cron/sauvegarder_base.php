<?php
/**
 * SOURIANE — Sauvegarde de la base de données.
 *
 * Produit un dump SQL complet dans storage/sauvegardes/, puis ne conserve que
 * les N sauvegardes les plus récentes.
 *
 * Utilisation :
 *   php cron/sauvegarder_base.php [--garder=7] [--dossier=chemin] [--silencieux] [--aide]
 *
 * Planification (chaque nuit à 3 h) :
 *   0 3 * * * /usr/bin/php /chemin/vers/cron/sauvegarder_base.php --silencieux
 *
 * Sous Windows, par le Planificateur de tâches :
 *   Programme : C:\xampp\php\php.exe
 *   Arguments : C:\xampp\htdocs\sourianemedia\cron\sauvegarder_base.php --silencieux
 *
 * Restauration (le dump contient CREATE DATABASE et USE) :
 *   mysql -u <utilisateur> -p < storage/sauvegardes/souriane-<horodatage>.sql
 *
 * mysqldump est requis, et aucun repli n'est tenté en PHP : un dump reconstruit
 * à la main laisserait de côté les vues, les index et l'encodage, tout en
 * portant le nom d'une sauvegarde. Mieux vaut un échec visible qu'une fausse
 * sécurité. Le chemin du binaire est cherché parmi les emplacements usuels et
 * peut être forcé par MYSQLDUMP_CHEMIN dans .env.
 *
 * Le script est réservé à la ligne de commande : une sauvegarde déclenchable
 * par HTTP permettrait de remplir le disque à volonté.
 */

declare(strict_types=1);

$racine = dirname(__DIR__);
require_once $racine . '/app/bootstrap.php';

// ---------------------------------------------------------------------------
// Contexte d’exécution
// ---------------------------------------------------------------------------

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Cette tâche s’exécute en ligne de commande uniquement.\n";
    exit(1);
}

$options = getopt('', ['garder::', 'dossier::', 'silencieux', 'aide']);

if (isset($options['aide'])) {
    echo "Usage : php cron/sauvegarder_base.php [options]\n\n";
    echo "  --garder=N     Nombre de sauvegardes conservées (défaut 7, max 365)\n";
    echo "  --dossier=C    Dossier de destination (défaut storage/sauvegardes)\n";
    echo "  --silencieux   N’écrit rien hors erreur\n";
    echo "  --aide         Affiche cette aide\n";
    exit(0);
}

$garder     = max(1, min(365, (int) ($options['garder'] ?? 7)));
$silencieux = isset($options['silencieux']);
$dossier    = (string) ($options['dossier'] ?? (STORAGE_PATH . '/sauvegardes'));

/** Écrit une ligne horodatée sur la sortie standard. */
function ligne(string $texte): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $texte . "\n";
}

/** L’exécution de programmes externes est-elle permise ? */
function execDisponible(): bool
{
    if (!function_exists('exec')) {
        return false;
    }

    $desactivees = array_map('trim', explode(',', (string) ini_get('disable_functions')));

    return !in_array('exec', $desactivees, true);
}

/**
 * Cherche mysqldump parmi les emplacements usuels.
 *
 * Le voisinage de l’interpréteur PHP est examiné en premier : XAMPP et WAMP
 * installent MySQL à côté de PHP, et c’est de là que part le plus souvent la
 * commande qui fonctionne. Le PATH ne vient qu’en dernier recours.
 */
function trouverMysqldump(): ?string
{
    $force = trim((string) env('MYSQLDUMP_CHEMIN', ''));
    if ($force !== '' && is_file($force)) {
        return $force;
    }

    $php = str_replace('\\', '/', dirname(PHP_BINARY));
    $exe = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';

    $candidats = [
        $php . '/../mysql/bin/mysqldump' . $exe,
        $php . '/../../mysql/bin/mysqldump' . $exe,
        'C:/xampp/mysql/bin/mysqldump.exe',
        '/usr/bin/mysqldump',
        '/usr/local/bin/mysqldump',
        '/usr/local/mysql/bin/mysqldump',
        '/opt/homebrew/bin/mysqldump',
    ];

    foreach ($candidats as $candidat) {
        if (is_file($candidat)) {
            return $candidat;
        }
    }

    if (!execDisponible()) {
        return null;
    }

    $recherche = PHP_OS_FAMILY === 'Windows'
        ? 'where mysqldump 2>NUL'
        : 'command -v mysqldump 2>/dev/null';

    $sortie = (string) @shell_exec($recherche);
    $premier = trim((string) strtok($sortie, "\r\n"));

    return $premier !== '' && is_file($premier) ? $premier : null;
}

/** Lit les derniers octets d’un fichier, sans le charger entièrement. */
function lireQueue(string $fichier, int $octets): string
{
    $taille = (int) @filesize($fichier);
    if ($taille <= 0) {
        return '';
    }

    $poignee = @fopen($fichier, 'rb');
    if ($poignee === false) {
        return '';
    }

    fseek($poignee, max(0, $taille - $octets));
    $contenu = (string) fread($poignee, $octets);
    fclose($poignee);

    return $contenu;
}

/**
 * Contrôle qu’un dump est complet.
 *
 * mysqldump termine toujours par « -- Dump completed on … ». Son absence
 * signifie que l’écriture s’est interrompue — disque plein, processus tué —
 * et qu’un fichier partiel porte le nom d’une sauvegarde valide. C’est
 * exactement le cas qu’il faut refuser.
 *
 * @return array{0: bool, 1: string}
 */
function dumpComplet(string $fichier): array
{
    $taille = @filesize($fichier);
    if ($taille === false || $taille < 1024) {
        return [false, 'fichier absent ou suspicieusement petit (' . (int) $taille . ' octets)'];
    }

    $entete = (string) @file_get_contents($fichier, false, null, 0, 65536);
    if (!str_contains($entete, 'CREATE TABLE')) {
        return [false, 'aucune instruction CREATE TABLE dans les 64 premiers ko'];
    }

    if (!str_contains(lireQueue($fichier, 8192), 'Dump completed on')) {
        return [false, 'marqueur de fin absent : écriture interrompue'];
    }

    return [true, ''];
}

/**
 * Ne conserve que les N sauvegardes les plus récentes.
 *
 * Le nom porte l’horodatage, donc le tri lexicographique décroissant sur le
 * chemin complet classe aussi bien que sur la date.
 *
 * @return list<string> noms des fichiers supprimés
 */
function rotation(string $dossier, int $garder): array
{
    $fichiers = glob($dossier . '/souriane-*.sql') ?: [];
    rsort($fichiers, SORT_STRING);

    $supprimes = [];
    foreach (array_slice($fichiers, $garder) as $ancien) {
        if (@unlink($ancien)) {
            $supprimes[] = basename($ancien);
        }
    }

    return $supprimes;
}

// ---------------------------------------------------------------------------
// Verrou d’exclusion mutuelle
// ---------------------------------------------------------------------------

/**
 * Deux passages simultanés écriraient deux fois le même dump, et le second
 * pourrait tronquer le fichier du premier.
 */
$cheminVerrou = STORAGE_PATH . '/sauvegarde.lock';
$verrou = @fopen($cheminVerrou, 'c');

if ($verrou === false) {
    ligne('Verrou indisponible : ' . $cheminVerrou);
    exit(1);
}

if (!flock($verrou, LOCK_EX | LOCK_NB)) {
    if (!$silencieux) {
        ligne('Une sauvegarde est déjà en cours, passage ignoré.');
    }
    fclose($verrou);
    exit(0);
}

ftruncate($verrou, 0);
fwrite($verrou, (string) getmypid() . "\n");
fflush($verrou);

// ---------------------------------------------------------------------------
// Sauvegarde
// ---------------------------------------------------------------------------

$debut         = microtime(true);
$fichierConfig = '';
$sortie        = 0;

try {
    if (!is_dir($dossier) && !@mkdir($dossier, 0770, true) && !is_dir($dossier)) {
        throw new RuntimeException('Dossier de sauvegarde inaccessible : ' . $dossier);
    }

    $binaire = trouverMysqldump();
    if ($binaire === null) {
        throw new RuntimeException(
            'mysqldump est introuvable. Installez-le, ou renseignez MYSQLDUMP_CHEMIN dans .env.'
        );
    }

    if (!execDisponible()) {
        throw new RuntimeException('La fonction exec() est désactivée : impossible de lancer mysqldump.');
    }

    $fichier = $dossier . '/souriane-' . date('Y-m-d-His') . '.sql';

    // Les identifiants passent par un fichier de configuration temporaire
    // plutôt que par la ligne de commande : un mot de passe en argument est
    // visible de tout utilisateur capable de lister les processus.
    $fichierConfig = $dossier . '/.sauvegarde.cnf';
    $motDePasse    = str_replace(['\\', '"'], ['\\\\', '\\"'], DB_PASS);

    $ini = "[client]\n"
        . 'host=' . DB_HOST . "\n"
        . 'port=' . DB_PORT . "\n"
        . 'user=' . DB_USER . "\n"
        . 'password="' . $motDePasse . '"' . "\n";

    if (@file_put_contents($fichierConfig, $ini) === false) {
        throw new RuntimeException('Fichier d’identifiants non écrit : ' . $fichierConfig);
    }
    @chmod($fichierConfig, 0600);

    $commande = escapeshellarg($binaire)
        . ' --defaults-extra-file=' . escapeshellarg($fichierConfig)
        . ' --single-transaction --quick --routines --events'
        . ' --default-character-set=' . escapeshellarg(DB_CHARSET)
        . ' --databases ' . escapeshellarg(DB_NAME)
        . ' --result-file=' . escapeshellarg($fichier);

    $lignesCommande = [];
    $code           = 0;
    exec($commande, $lignesCommande, $code);

    if ($code !== 0) {
        @unlink($fichier);
        throw new RuntimeException(
            'mysqldump a échoué (code ' . $code . ') : ' . trim(implode(' ', $lignesCommande))
        );
    }

    [$complet, $raison] = dumpComplet($fichier);
    if (!$complet) {
        @unlink($fichier);
        throw new RuntimeException('Dump incomplet, fichier écarté : ' . $raison);
    }

    $supprimes = rotation($dossier, $garder);

    journal_technique('sauvegarde', 'Sauvegarde de la base effectuée', [
        'fichier'  => basename($fichier),
        'octets'   => (int) filesize($fichier),
        'purgees'  => $supprimes,
        'conserve' => $garder,
    ]);

    if (!$silencieux) {
        ligne(sprintf(
            'Sauvegarde écrite : %s (%s ko)',
            basename($fichier),
            number_format((int) filesize($fichier) / 1024, 0, ',', ' ')
        ));
        if ($supprimes !== []) {
            ligne('Purgées (' . count($supprimes) . ') : ' . implode(', ', $supprimes));
        }
        ligne(sprintf('Terminé en %.0f ms.', (microtime(true) - $debut) * 1000));
    }
} catch (Throwable $e) {
    ligne('Erreur : ' . $e->getMessage());
    journal_technique('erreur', 'Sauvegarde de la base échouée', [
        'message' => $e->getMessage(),
    ]);
    $sortie = 1;
} finally {
    if ($fichierConfig !== '' && is_file($fichierConfig)) {
        @unlink($fichierConfig);
    }

    flock($verrou, LOCK_UN);
    fclose($verrou);
    @unlink($cheminVerrou);
}

exit($sortie);
