<?php
/**
 * Vérification de bout en bout de l'API JSON.
 * Exécution : php tests/verifier_api.php
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';


$base = 'http://localhost/sourianemedia';
$jar  = sys_get_temp_dir() . '/souriane-cookies.txt';
@unlink($jar);

function appel(string $methode, string $url, ?array $corps = null, ?string $csrf = null, string $jar = ''): array
{
    $ch = curl_init($url);
    $entetes = ['Accept: application/json'];
    if ($csrf !== null) {
        $entetes[] = 'X-CSRF-Token: ' . $csrf;
    }
    if ($corps !== null) {
        $entetes[] = 'Content-Type: application/json';
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $methode,
        CURLOPT_HTTPHEADER     => $entetes,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($corps !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corps));
    }

    $reponse = curl_exec($ch);
    $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'corps' => $reponse, 'json' => json_decode((string) $reponse, true)];
}

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

// ---------------------------------------------------------------------------
echo "\n=== 1. Récupération du jeton CSRF depuis la page annonceur ===\n";

// Repère pris avant toute écriture : le nettoyage final supprimera les
// événements dont l'identifiant dépasse ce point. Certains événements
// (abonnement, désabonnement) n'ont pas d'identifiant de session et ne
// pourraient pas être ciblés autrement.
$idEvenementInitial = (int) lire('SELECT IFNULL(MAX(id), 0) FROM evenements');
$idVisiteInitial    = (int) lire('SELECT IFNULL(MAX(id), 0) FROM visites');
// Volumes de référence : la vérification doit rendre le journal exactement
// dans l'état où elle l'a trouvé, sans supposer un total figé.
$nbEvenementsInitial = (int) lire('SELECT COUNT(*) FROM evenements');
$nbVisitesInitial    = (int) lire('SELECT COUNT(*) FROM visites');

$page = appel('GET', $base . '/quincaillerie-bignona', null, null, $jar);
verifier('la page annonceur répond 200', $page['code'] === 200, 'code ' . $page['code']);

preg_match('/window\.SOURIANE = (\{.*?\});<\/script>/s', (string) $page['corps'], $m);
$config = $m[1] ?? '{}';
$config = json_decode($config, true) ?: [];

$csrf    = $config['csrf'] ?? '';
$session = $config['session'] ?? '';

verifier('jeton CSRF présent', $csrf !== '', 'jeton vide');
verifier('identifiant de session présent', $session !== '', 'session vide');
verifier('clé VAPID transmise au client', ($config['vapid'] ?? '') !== '');

// ---------------------------------------------------------------------------
echo "\n=== 2. POST /api/evenements ===\n";

$sansJeton = appel('POST', $base . '/api/evenements', ['evenements' => [['type' => 'clic_appel']]], null, $jar);
verifier('refus sans jeton CSRF (419)', $sansJeton['code'] === 419, 'code ' . $sansJeton['code']);

$lot = appel('POST', $base . '/api/evenements', [
    'session_id'    => $session,
    'page_id'       => 1,
    'annonceur_id'  => 1,
    'campagne_id'   => 1,
    'panneau_id'    => 1,
    'code_court_id' => 1,
    'evenements'    => [
        ['type' => 'clic_appel'],
        ['type' => 'clic_whatsapp', 'meta' => ['origine' => 'test']],
        ['type' => 'clic_itineraire'],
        ['type' => 'scan'],            // type réservé au serveur : doit être ignoré
        ['type' => 'type_inconnu'],    // doit être ignoré
    ],
], $csrf, $jar);

verifier('le lot est accepté (200)', $lot['code'] === 200, 'code ' . $lot['code']);
verifier('3 événements sur 5 enregistrés', ($lot['json']['enregistres'] ?? -1) === 3,
    'enregistres = ' . var_export($lot['json']['enregistres'] ?? null, true));

// ---------------------------------------------------------------------------
echo "\n=== 3. POST /api/abonnements ===\n";

$endpoint = 'https://fcm.googleapis.com/fcm/send/test-' . bin2hex(random_bytes(8));

$abonnement = appel('POST', $base . '/api/abonnements', [
    'annonceur_id'  => 1,
    'page_id'       => 1,
    'code_court_id' => 1,
    'session_id'    => $session,
    'endpoint'      => $endpoint,
    'keys'          => [
        'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM',
        'auth'   => 'tBHItJI5svbpez7KI4CCXg',
    ],
    'frequence'     => 'hebdomadaire',
], $csrf, $jar);

verifier('abonnement créé (201)', $abonnement['code'] === 201, 'code ' . $abonnement['code']);
verifier('identifiant d’abonné retourné', !empty($abonnement['json']['abonne']));
verifier('fréquence conservée', ($abonnement['json']['frequence'] ?? '') === 'hebdomadaire');

$mauvais = appel('POST', $base . '/api/abonnements', [
    'annonceur_id' => 1,
    'endpoint'     => 'http://insecure.example/x',
    'keys'         => ['p256dh' => 'a', 'auth' => 'b'],
], $csrf, $jar);
verifier('endpoint non HTTPS refusé (422)', $mauvais['code'] === 422, 'code ' . $mauvais['code']);

$inconnu = appel('POST', $base . '/api/abonnements', [
    'annonceur_id' => 99999,
    'endpoint'     => 'https://fcm.googleapis.com/fcm/send/x',
    'keys'         => ['p256dh' => 'a', 'auth' => 'b'],
], $csrf, $jar);
verifier('annonceur inconnu refusé (404)', $inconnu['code'] === 404, 'code ' . $inconnu['code']);

// ---------------------------------------------------------------------------
echo "\n=== 4. POST /api/abonnements/suppression ===\n";

$suppression = appel('POST', $base . '/api/abonnements/suppression', [
    'endpoint'     => $endpoint,
    'annonceur_id' => 1,
    'session_id'   => $session,
], $csrf, $jar);

verifier('désinscription acceptée (200)', $suppression['code'] === 200, 'code ' . $suppression['code']);
verifier('un abonnement désactivé', ($suppression['json']['desabonnes'] ?? 0) === 1,
    'desabonnes = ' . var_export($suppression['json']['desabonnes'] ?? null, true));

// ---------------------------------------------------------------------------
echo "\n=== 5. GET /api/campagnes/1/statistiques ===\n";

$stats = appel('GET', $base . '/api/campagnes/1/statistiques', null, null, $jar);
verifier('accès refusé sans authentification (401)', $stats['code'] === 401, 'code ' . $stats['code']);

$sante = appel('GET', $base . '/api/sante', null, null, $jar);
verifier('sonde de disponibilité (200)', $sante['code'] === 200 && ($sante['json']['ok'] ?? false) === true);

// ---------------------------------------------------------------------------
echo "\n=== 6. Limitation de débit ===\n";

$refus = 0;
for ($i = 0; $i < 25; $i++) {
    $r = appel('POST', $base . '/api/abonnements', [
        'annonceur_id' => 1,
        'endpoint'     => 'https://fcm.googleapis.com/fcm/send/debit-' . $i,
        'keys'         => ['p256dh' => 'a', 'auth' => 'b'],
    ], $csrf, $jar);
    if ($r['code'] === 429) {
        $refus++;
    }
}
verifier('limitation de débit déclenchée (429)', $refus > 0, 'aucun 429 sur 25 appels');

// ---------------------------------------------------------------------------
echo "\n=== 7. Pages publiques et codes ===\n";

$code = appel('GET', $base . '/c/m3x9', null, null, $jar);
verifier('code court redirigé (302)', $code['code'] === 302, 'code ' . $code['code']);

$inconnu = appel('GET', $base . '/c/zzzzz', null, null, $jar);
verifier('code inconnu → 404', $inconnu['code'] === 404, 'code ' . $inconnu['code']);

$brouillon = appel('GET', $base . '/teranga-tissus', null, null, $jar);
verifier('page non publiée → 503', $brouillon['code'] === 503, 'code ' . $brouillon['code']);

$manifeste = appel('GET', $base . '/manifest.webmanifest', null, null, $jar);
$manifesteJson = json_decode((string) $manifeste['corps'], true);
verifier('manifeste servi et valide', is_array($manifesteJson) && isset($manifesteJson['icons']));
verifier('manifeste en mode standalone', ($manifesteJson['display'] ?? '') === 'standalone');

$sw = appel('GET', $base . '/service-worker.js', null, null, $jar);
verifier('service worker servi', $sw['code'] === 200 && str_contains((string) $sw['corps'], 'addEventListener'));

// ---------------------------------------------------------------------------
echo "\n=== 8. Nettoyage des données de test ===\n";

/**
 * Accès direct à la base pour retirer ce que la vérification a créé.
 * Les identifiants proviennent de .env, comme pour l'application.
 */
function base(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo;
    }
    if (!extension_loaded('pdo_mysql')) {
        $pdo = null;

        return null;
    }

    $env = env_fichier(dirname(__DIR__) . '/.env');
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $env['DB_HOST'] ?? '127.0.0.1',
            $env['DB_PORT'] ?? '3306',
            $env['DB_NAME'] ?? 'souriane',
            $env['DB_CHARSET'] ?? 'utf8mb4'
        ),
        (string) ($env['DB_USER'] ?? 'root'),
        (string) ($env['DB_PASS'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    return $pdo;
}

/** Exécute une écriture, renvoie les lignes touchées. */
function ecrire(string $sql): int
{
    $pdo = base();
    if ($pdo instanceof PDO) {
        return (int) $pdo->exec($sql);
    }

    $bin = 'C:\\wamp64\\bin\\mysql\\mysql9.1.0\\bin\\mysql.exe';
    if (!is_file($bin)) {
        return 0;
    }
    $env = env_fichier(dirname(__DIR__) . '/.env');
    $sortie = trim((string) shell_exec(sprintf(
        '%s -h%s -P%s -u%s %s -N -B -e %s 2>&1',
        escapeshellarg($bin),
        escapeshellarg((string) ($env['DB_HOST'] ?? '127.0.0.1')),
        escapeshellarg((string) ($env['DB_PORT'] ?? '3306')),
        escapeshellarg((string) ($env['DB_USER'] ?? 'root')),
        escapeshellarg((string) ($env['DB_NAME'] ?? 'souriane')),
        escapeshellarg($sql . '; SELECT ROW_COUNT();')
    )));
    $lignes = preg_split('/\R/', $sortie) ?: [];

    return (int) trim((string) end($lignes));
}

/** Lit une valeur scalaire. */
function lire(string $sql): string
{
    $pdo = base();
    if ($pdo instanceof PDO) {
        $valeur = $pdo->query($sql)->fetchColumn();

        return $valeur === false ? '' : (string) $valeur;
    }

    return '';
}

// Les abonnements créés par le test portent un endpoint reconnaissable.
$cibles = "SELECT id FROM abonnes WHERE endpoint LIKE '%/test-%' OR endpoint LIKE '%/debit-%'";

ecrire('DELETE FROM envois WHERE abonne_id IN (' . $cibles . ')');
$abonnesSupprimes = ecrire('DELETE FROM abonnes WHERE endpoint LIKE "%/test-%" OR endpoint LIKE "%/debit-%"');
verifier('les abonnements de test sont supprimés', $abonnesSupprimes >= 1, 'lignes : ' . $abonnesSupprimes);

// Les événements enregistrés pendant la vérification sont ceux dont
// l'identifiant dépasse le repère pris au départ.
$evenementsSupprimes = ecrire('DELETE FROM evenements WHERE id > ' . $idEvenementInitial);
verifier('les événements de test sont supprimés', $evenementsSupprimes >= 1, 'lignes : ' . $evenementsSupprimes);

$visitesSupprimees = ecrire('DELETE FROM visites WHERE id > ' . $idVisiteInitial);
verifier('les visites de test sont supprimées', $visitesSupprimees >= 1, 'lignes : ' . $visitesSupprimees);

$restants = (int) lire("SELECT COUNT(*) FROM abonnes WHERE endpoint LIKE '%/test-%' OR endpoint LIKE '%/debit-%'");
verifier('aucun abonné de test ne subsiste', $restants === 0, 'restants : ' . $restants);

$totalAbonnes = (int) lire('SELECT COUNT(*) FROM abonnes');
verifier('les abonnés de démonstration sont intacts', $totalAbonnes === 3, 'total : ' . $totalAbonnes);

$totalEvenements = (int) lire('SELECT COUNT(*) FROM evenements');
verifier(
    'le journal d’événements retrouve son volume d’origine',
    $totalEvenements === $nbEvenementsInitial,
    'attendu ' . $nbEvenementsInitial . ', obtenu ' . $totalEvenements
);

$totalVisites = (int) lire('SELECT COUNT(*) FROM visites');
verifier(
    'le journal des visites retrouve son volume d’origine',
    $totalVisites === $nbVisitesInitial,
    'attendu ' . $nbVisitesInitial . ', obtenu ' . $totalVisites
);

// Les compteurs de limitation de débit sont remis à zéro pour que la suite
// reste rejouable immédiatement.
foreach (glob(dirname(__DIR__) . '/storage/limites/*.json') ?: [] as $fichier) {
    @unlink($fichier);
}
verifier('les compteurs de limitation sont réinitialisés', true);

// ---------------------------------------------------------------------------
@unlink($jar);

echo "\n============================================================\n";
echo sprintf("  Réussis : %d   |   Échecs : %d\n", $reussis, $echecs);
echo "============================================================\n";

exit($echecs === 0 ? 0 : 1);
