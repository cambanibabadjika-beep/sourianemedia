<?php
/**
 * Vérification de la sauvegarde de la base (proposition D5).
 * Exécution : php tests/verifier_sauvegarde.php
 *
 * Le script `cron/sauvegarder_base.php` produit un dump SQL et n’en garde
 * qu’un nombre fixe. Une sauvegarde qui ne restaure rien étant plus
 * dangereuse qu’aucune sauvegarde — elle donne la fausse assurance d’en
 * avoir une —, la suite contrôle trois choses de près :
 *
 *   1. un dump réel est produit, complet et exploitable ;
 *   2. la rotation ne conserve que le nombre demandé ;
 *   3. un dump tronqué est **refusé et supprimé**, pas conservé.
 *
 * Le troisième point est éprouvé avec un faux mysqldump qui n’écrit pas le
 * marqueur de fin : sans ce contrôle, un disque plein produirait un fichier
 * portant le nom d’une sauvegarde valide.
 *
 * Tout se passe dans un dossier temporaire ; les sauvegardes réelles ne sont
 * pas touchées.
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once dirname(__DIR__) . '/app/bootstrap.php';

$racine = dirname(__DIR__);
$script = $racine . '/cron/sauvegarder_base.php';

$reussis = 0;
$echecs  = 0;

function verifier(string $intitule, bool $condition, string $detail = ''): void
{
    global $reussis, $echecs;
    if ($condition) {
        $reussis++;
        echo "  [OK]     $intitule\n";
    } else {
        $echecs++;
        echo "  [ECHEC]  $intitule" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

/**
 * Lance le script de sauvegarde.
 *
 * @param list<string>          $arguments
 * @param array<string, string> $env
 * @return array{0: int, 1: string}
 */
function lancer(array $arguments, array $env = []): array
{
    global $script;

    $commande = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
    foreach ($arguments as $argument) {
        $commande .= ' ' . escapeshellarg($argument);
    }

    // Les variables d’environnement du processus enfant ne s’écrivent pas de
    // la même façon sous Windows et ailleurs.
    if (PHP_OS_FAMILY === 'Windows') {
        $prefixe = '';
        foreach ($env as $cle => $valeur) {
            $prefixe .= 'set ' . $cle . '=' . $valeur . ' && ';
        }
        $commande = $prefixe . $commande;
    } else {
        $prefixe = '';
        foreach ($env as $cle => $valeur) {
            $prefixe .= $cle . '=' . escapeshellarg($valeur) . ' ';
        }
        $commande = $prefixe . $commande;
    }

    $lignes = [];
    $code   = 0;
    exec($commande, $lignes, $code);

    return [$code, implode("\n", $lignes)];
}

/** Fichiers de sauvegarde présents dans un dossier, du plus ancien au plus récent. */
function sauvegardes(string $dossier): array
{
    $fichiers = glob($dossier . '/souriane-*.sql') ?: [];
    sort($fichiers, SORT_STRING);

    return $fichiers;
}

$dossier = sys_get_temp_dir() . '/souriane-sauvegarde-' . bin2hex(random_bytes(4));

echo "Sauvegarde de la base — script et rotation\n";
echo str_repeat('=', 62) . "\n";

// ---------------------------------------------------------------------------
echo "\n=== 1. Un dump réel, complet ===\n";

@mkdir($dossier, 0770, true);

[$code, $sortie] = lancer(['--dossier=' . $dossier]);
verifier('le script se termine sans erreur', $code === 0, 'code ' . $code . ' — ' . trim($sortie));

$fichiers = sauvegardes($dossier);
verifier('une sauvegarde est produite', count($fichiers) === 1, count($fichiers) . ' fichier(s)');

if ($fichiers !== []) {
    $dump    = $fichiers[0];
    $contenu = (string) file_get_contents($dump);
    $taille  = (int) filesize($dump);

    verifier('le fichier dépasse 1 ko', $taille > 1024, $taille . ' octets');
    verifier('le dump déclare les tables', str_contains($contenu, 'CREATE TABLE'));
    verifier('le dump contient la table « utilisateurs »', str_contains($contenu, 'CREATE TABLE `utilisateurs`'));
    verifier('le dump contient la vue « v_stats_campagne »', str_contains($contenu, 'v_stats_campagne'));
    verifier('le dump se déclare complet', str_contains($contenu, 'Dump completed on'),
        'marqueur de fin absent');
    verifier('le nom du fichier porte la date', preg_match('/souriane-\d{4}-\d{2}-\d{2}-\d{6}\.sql$/', $dump) === 1,
        basename($dump));
}

verifier('le fichier d’identifiants temporaire est supprimé',
    glob($dossier . '/*.cnf') === [], 'un fichier .cnf subsiste');
verifier('le verrou est libéré',
    !is_file(STORAGE_PATH . '/sauvegarde.lock'), 'storage/sauvegarde.lock subsiste');

// ---------------------------------------------------------------------------
echo "\n=== 2. La rotation ne garde que le nombre demandé ===\n";

// Quatre sauvegardes anciennes, nommées comme le script les nommerait : le tri
// se fait sur l’horodatage du nom, pas sur la date de modification du fichier.
foreach (['2019-01-01-030000', '2020-01-01-030000', '2021-01-01-030000', '2022-01-01-030000'] as $quand) {
    file_put_contents($dossier . '/souriane-' . $quand . '.sql', "-- ancienne sauvegarde\n");
}

[$code, $sortie] = lancer(['--dossier=' . $dossier, '--garder=2']);
verifier('la rotation se termine sans erreur', $code === 0, 'code ' . $code);

$restants = sauvegardes($dossier);
verifier('il ne reste que deux sauvegardes', count($restants) === 2,
    count($restants) . ' : ' . implode(', ', array_map('basename', $restants)));
verifier('les deux conservées sont les plus récentes',
    $restants !== [] && !str_contains(implode(' ', $restants), '2019-01-01')
        && !str_contains(implode(' ', $restants), '2020-01-01')
        && !str_contains(implode(' ', $restants), '2021-01-01'),
    implode(', ', array_map('basename', $restants)));

// ---------------------------------------------------------------------------
echo "\n=== 3. Un dump tronqué est refusé ===\n";

// Faux mysqldump : il honore --result-file mais n’écrit jamais le marqueur de
// fin. C’est exactement ce que produit une écriture interrompue.
$fauxSource = $dossier . '/faux_dump.php';
file_put_contents($fauxSource, <<<'PHP'
<?php
$sortie = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--result-file=')) {
        $sortie = substr($argument, strlen('--result-file='));
    }
}
if ($sortie !== '') {
    file_put_contents($sortie, "-- MariaDB dump\nCREATE TABLE t (id int);\n");
}
exit(0);
PHP);

$fauxBinaire = $dossier . '/faux-mysqldump' . (PHP_OS_FAMILY === 'Windows' ? '.bat' : '.sh');
if (PHP_OS_FAMILY === 'Windows') {
    file_put_contents($fauxBinaire, "@echo off\r\n\"" . PHP_BINARY . "\" \"" . $fauxSource . "\" %*\r\n");
} else {
    file_put_contents($fauxBinaire, "#!/bin/sh\nexec \"" . PHP_BINARY . "\" \"" . $fauxSource . "\" \"$@\"\n");
    chmod($fauxBinaire, 0755);
}

$avant = count(sauvegardes($dossier));

[$code, $sortie] = lancer(['--dossier=' . $dossier], ['MYSQLDUMP_CHEMIN' => $fauxBinaire]);
verifier('le script signale l’échec', $code === 1, 'code ' . $code);
verifier('le message nomme la cause', str_contains($sortie, 'Dump incomplet') || str_contains($sortie, 'petit'),
    trim($sortie));

$apres = count(sauvegardes($dossier));
verifier('aucune sauvegarde tronquée n’est conservée', $apres === $avant,
    $avant . ' avant, ' . $apres . ' après');

// ---------------------------------------------------------------------------
echo "\n=== 4. L’aide et le refus par HTTP ===\n";

[$code, $sortie] = lancer(['--aide']);
verifier('l’aide s’affiche et sort en 0', $code === 0 && str_contains($sortie, 'Usage'),
    'code ' . $code);

$ch = curl_init('http://localhost/sourianemedia/cron/sauvegarder_base.php');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
$corps = (string) curl_exec($ch);
$codeHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
verifier('l’appel HTTP n’aboutit pas', $codeHttp !== 200, 'code ' . $codeHttp);
verifier('l’appel HTTP ne produit aucun dump',
    !str_contains($corps, 'CREATE TABLE') && !str_contains($corps, 'Dump completed'),
    'le corps ressemble à un dump');

// ---------------------------------------------------------------------------
echo "\n=== 5. La sauvegarde n’est pas téléchargeable ===\n";

// Une sauvegarde joignable en HTTP livrerait toute la base — hachages de mots
// de passe compris — à qui devine le nom du fichier. Le dossier storage/ est
// interdit par .htaccess ; encore faut-il le constater plutôt que le croire.
$ch = curl_init('http://localhost/sourianemedia/');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
curl_exec($ch);
$serveurActif = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
curl_close($ch);

if (!$serveurActif) {
    echo "  [NOTE]   serveur HTTP injoignable : contrôle d’accès non éprouvé\n";
} else {
    $sonde = STORAGE_PATH . '/sauvegardes/sonde-acces-http-' . bin2hex(random_bytes(4)) . '.sql';
    @mkdir(dirname($sonde), 0770, true);
    file_put_contents($sonde, "-- sonde d’accès\nCREATE TABLE sonde (id int);\n");

    $ch = curl_init('http://localhost/sourianemedia/storage/sauvegardes/' . basename($sonde));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $corps    = (string) curl_exec($ch);
    $codeHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    verifier('le dump placé dans storage/ n’est pas servi',
        $codeHttp === 403 || $codeHttp === 404, 'code ' . $codeHttp);
    verifier('aucun contenu de dump ne fuit',
        !str_contains($corps, 'CREATE TABLE'), 'le corps contient un dump');

    @unlink($sonde);
    verifier('la sonde est retirée', !is_file($sonde));
}

// ---------------------------------------------------------------------------
echo "\n=== 6. Nettoyage ===\n";

foreach (glob($dossier . '/*') ?: [] as $fichier) {
    @unlink($fichier);
}
@rmdir($dossier);

verifier('le dossier temporaire est supprimé', !is_dir($dossier));

$total = $reussis + $echecs;
echo "\n" . str_repeat('=', 62) . "\n";
echo sprintf('Sauvegarde de la base — %d/%d vérifications réussies', $reussis, $total) . "\n";
echo str_repeat('=', 62) . "\n";

exit($echecs === 0 ? 0 : 1);
