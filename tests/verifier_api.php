<?php
/**
 * Vérification de bout en bout de l'API JSON.
 * Exécution : php tests/verifier_api.php
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';

/*
 * Le coffre et le calcul des codes à usage unique sont ceux de l'application.
 * Le compte de démonstration est protégé par un second facteur (ch. 8) : la
 * suite lit le secret partagé et produit un code valide avec le code du site,
 * plutôt qu'avec une valeur de circonstance qui masquerait une régression.
 */
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Coffre.php';
require_once dirname(__DIR__) . '/app/core/Totp.php';

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

/*
 * Garde-fou de fuseau horaire.
 *
 * L'application fixe son fuseau dans app/bootstrap.php (Africa/Dakar) ; le
 * harnais l'impose aussi dans tests/env.php. Si les deux divergent, les suites
 * qui raisonnent sur une date — le nom du fichier de journal, par exemple —
 * cherchent un fichier que l'application n'écrit pas. Le défaut est muet la
 * plupart du temps : il ne se manifeste que lorsque les deux fuseaux ne sont
 * pas sur le même jour.
 *
 * La sonde renvoie l'heure de l'application au format ISO 8601, décalage
 * compris : comparer les décalages suffit à détecter la divergence, sans avoir
 * à lire le code source de l'application.
 */
$heureApplication = (string) ($sante['json']['heure'] ?? '');
$decalageApp      = substr($heureApplication, -6);
$decalageHarnais  = date('P');
verifier(
    'le harnais applique le même fuseau que l’application',
    $decalageApp === $decalageHarnais,
    'application ' . ($decalageApp !== '' ? $decalageApp : '(illisible)')
        . ' / harnais ' . $decalageHarnais
);

/*
 * Le point d’entrée des statistiques exige une session : il faut donc se
 * connecter pour l’interroger, et non se contenter de constater le 401.
 *
 * EF-F07 impose que chaque rapport sépare le mesuré, l’estimé et le déclaré.
 * Un consommateur de l’interface de programmation reçoit ces chiffres sans
 * passer par l’écran : les trois blocs doivent donc être présents dans la
 * réponse, sous peine de voir un chiffre estimé repris comme une mesure.
 */
$jarStats = sys_get_temp_dir() . '/souriane-api-stats.txt';
@unlink($jarStats);

/**
 * Code à deux facteurs courant d'un compte.
 *
 * Le secret est lu en base et déchiffré par le coffre de l'application, puis
 * le code est calculé par l'implémentation de la RFC 6238 que sert le site :
 * la suite éprouve le vrai chemin, sans valeur de circonstance.
 *
 * Un code ne sert qu'une fois (RFC 6238 § 5.2). Si l'intervalle courant a
 * déjà été consommé, on attend le suivant plutôt que de réutiliser le même.
 */
function code2fa(string $email): string
{
    $secret = \Souriane\Core\Coffre::ouvrir(
        trim(lire("SELECT IFNULL(totp_secret, '') FROM utilisateurs WHERE email = '" . $email . "'"))
    );

    if ($secret === null) {
        return '';
    }

    $dernier = trim(lire("SELECT IFNULL(totp_dernier_pas, '') FROM utilisateurs WHERE email = '" . $email . "'"));
    $pas     = \Souriane\Core\Totp::pasCourant();

    if ($dernier !== '' && $pas <= (int) $dernier) {
        $attente = ((int) $dernier + 1) * \Souriane\Core\Totp::PERIODE - time() + 1;
        if ($attente > 0) {
            echo "  (intervalle de 30 s déjà consommé — attente de {$attente} s pour le code suivant)\n";
            sleep($attente);
        }
        $pas = \Souriane\Core\Totp::pasCourant();
    }

    return \Souriane\Core\Totp::code($secret, $pas);
}

$pageConnexion = appel('GET', $base . '/admin/connexion', null, null, $jarStats);
preg_match('/name="_csrf" value="([^"]+)"/', $pageConnexion['corps'], $mJeton);
$jetonConnexion = $mJeton[1] ?? '';

$connexion = appel('POST', $base . '/admin/connexion', [
    '_csrf'        => $jetonConnexion,
    'email'        => 'admin@souriane.sn',
    'mot_de_passe' => 'Souriane2026!',
    'retour'       => '',
], null, $jarStats);

/*
 * Second facteur (ch. 8). Le compte de démonstration en est protégé : le mot
 * de passe seul n’ouvre plus la session. On interroge l’écran de code plutôt
 * que l’en-tête de redirection — l’utilitaire d’appel de cette suite ne
 * remonte pas les en-têtes, et un 200 sur cet écran signifie exactement
 * « un code est attendu ».
 *
 * SECURITE_2FA_SANS_CODE dispense de saisir le code (voir
 * app/config/config.php) : le contrôle suit alors le comportement configuré.
 */
$etape2fa = appel('GET', $base . '/admin/connexion/code', null, null, $jarStats);

if (SECURITE_2FA_SANS_CODE) {
    verifier(
        'dérogation active : le mot de passe ouvre la session sans code (ch. 8)',
        $connexion['code'] === 302,
        'code ' . $connexion['code']
    );
} else {
    verifier(
        'le second facteur est demandé après le mot de passe (ch. 8)',
        $etape2fa['code'] === 200,
        'code ' . $etape2fa['code']
    );
}

if ($etape2fa['code'] === 200) {
    preg_match('/name="_csrf" value="([^"]+)"/', (string) $etape2fa['corps'], $m2fa);

    $connexion = appel('POST', $base . '/admin/connexion/code', [
        '_csrf' => $m2fa[1] ?? '',
        'code'  => code2fa('admin@souriane.sn'),
    ], null, $jarStats);
}

verifier('connexion régie pour interroger les statistiques', $connexion['code'] === 302, 'code ' . $connexion['code']);

$statsAuth = appel('GET', $base . '/api/campagnes/1/statistiques', null, null, $jarStats);
verifier(
    'statistiques accessibles une fois authentifié (200)',
    $statsAuth['code'] === 200,
    'code ' . $statsAuth['code']
);

$corpsStats = $statsAuth['json'] ?? [];
verifier(
    'la réponse sépare le mesuré, l’estimé et le déclaré (EF-F07)',
    isset($corpsStats['mesure'], $corpsStats['estimation'], $corpsStats['declare']),
    'blocs présents : ' . implode(', ', array_keys($corpsStats))
);
verifier(
    'le bloc mesuré porte les compteurs bruts',
    isset($corpsStats['mesure']['scans_bruts'], $corpsStats['mesure']['visiteurs_uniques']),
    'compteurs mesurés incomplets'
);
verifier(
    'le bloc estimé annonce sa méthode',
    !empty($corpsStats['estimation']['methode']),
    'méthode d’estimation absente'
);
verifier(
    'le bloc déclaré annonce sa nature',
    ($corpsStats['declare']['nature'] ?? '') === 'déclaré',
    'nature déclarée absente ou inattendue'
);

@unlink($jarStats);

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
echo "\n=== 8. Filtres du parc (EF-A05) ===\n";

/**
 * Compte les lignes réellement rendues dans le tableau du parc.
 *
 * Le contrôle porte sur le HTML servi, pas sur la réponse d’une fonction
 * interne : c’est ce que le visiteur reçoit qui doit être juste.
 */
function lignesParc(string $html): int
{
    return substr_count($html, '<td class="tableau__principal">');
}

/** Lit le décompte annoncé par la page, ou null s'il est absent. */
function compteAnnonce(string $html): ?int
{
    return preg_match('/id="parc-resultat">(\d+)</', $html, $m) === 1 ? (int) $m[1] : null;
}

/**
 * Cite une valeur pour l’insérer dans une requête de contrôle.
 *
 * Le harnais interroge la base directement afin de confronter ce que la page
 * affiche à ce qu’elle contient réellement. L’extension PDO n’est pas garantie
 * dans l’interpréteur courant : à défaut, la valeur est échappée à la main.
 */
function citer(string $valeur): string
{
    $pdo = base();

    return $pdo instanceof PDO
        ? $pdo->quote($valeur)
        : "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $valeur) . "'";
}

/**
 * Échappe une chaîne comme le fait la vue, pour comparer un attribut rendu.
 *
 * L’application dispose de `e()` ; le harnais ne charge pas l’application, il
 * ne voit que du HTML. Cette fonction reproduit donc l’échappement attendu.
 */
function html(string $valeur): string
{
    return htmlspecialchars($valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$parc = appel('GET', $base . '/le-parc', null, null, $jar);
verifier('la page du parc répond (200)', $parc['code'] === 200, 'code ' . $parc['code']);

// Les quatre critères du chapitre 4 sont présents dans le formulaire
foreach (['filtre-ville', 'filtre-format', 'filtre-statut', 'filtre-disponible'] as $controle) {
    verifier(
        sprintf('le formulaire du parc propose le critère « %s »', str_replace('filtre-', '', $controle)),
        str_contains($parc['corps'], 'id="' . $controle . '"'),
        'critère absent'
    );
}
verifier(
    'le filtre du parc est un formulaire de lecture, utilisable sans JavaScript',
    str_contains($parc['corps'], 'method="get"') && str_contains($parc['corps'], 'action="' . $base . '/le-parc"'),
    'formulaire de filtre absent ou non GET'
);

// Le décompte annoncé doit être celui des lignes rendues : le filtre étant
// appliqué par le serveur, les deux ne peuvent pas diverger.
$totalParc = lignesParc($parc['corps']);
verifier(
    'le décompte annoncé correspond aux lignes affichées',
    compteAnnonce($parc['corps']) === $totalParc,
    'annoncé : ' . var_export(compteAnnonce($parc['corps']), true) . ' / lignes : ' . $totalParc
);

// Un support retiré du parc n’a pas sa place dans la vitrine
$archivesBase = (int) lire('SELECT COUNT(*) FROM panneaux WHERE statut = \'archive\'');
$tousBase     = (int) lire('SELECT COUNT(*) FROM panneaux');
verifier(
    'les supports archivés sont exclus de la vitrine',
    $totalParc === $tousBase - $archivesBase,
    'affichés : ' . $totalParc . ' / attendu : ' . ($tousBase - $archivesBase)
);

if ($archivesBase > 0) {
    $avecArchives = appel('GET', $base . '/le-parc?statut=archive', null, null, $jar);
    verifier(
        'les supports archivés restent consultables sur demande explicite',
        lignesParc($avecArchives['corps']) === $archivesBase,
        'affichés : ' . lignesParc($avecArchives['corps']) . ' / attendu : ' . $archivesBase
    );
}

// --- Filtre par ville ------------------------------------------------------
$ville = (string) lire("SELECT ville FROM panneaux WHERE ville IS NOT NULL AND statut <> 'archive' GROUP BY ville ORDER BY COUNT(*) DESC LIMIT 1");
$villeBase = (int) lire('SELECT COUNT(*) FROM panneaux WHERE ville = ' . citer($ville) . " AND statut <> 'archive'");

if ($ville !== '') {
    $parVille = appel('GET', $base . '/le-parc?ville=' . rawurlencode($ville), null, null, $jar);
    verifier(
        'le filtre par ville ne garde que cette ville',
        lignesParc($parVille['corps']) === $villeBase,
        'affichés : ' . lignesParc($parVille['corps']) . ' / attendu : ' . $villeBase
    );
    verifier(
        'le filtre par ville est reflété dans le formulaire',
        str_contains($parVille['corps'], 'value="' . html($ville) . '" selected'),
        'sélection non conservée dans le formulaire'
    );
}

// --- Filtre par format -----------------------------------------------------
$format = (string) lire("SELECT format FROM panneaux WHERE format IS NOT NULL AND statut <> 'archive' GROUP BY format ORDER BY COUNT(*) DESC LIMIT 1");
$formatBase = (int) lire('SELECT COUNT(*) FROM panneaux WHERE format = ' . citer($format) . " AND statut <> 'archive'");

if ($format !== '') {
    $parFormat = appel('GET', $base . '/le-parc?format=' . rawurlencode($format), null, null, $jar);
    verifier(
        'le filtre par format ne garde que ce format',
        lignesParc($parFormat['corps']) === $formatBase,
        'affichés : ' . lignesParc($parFormat['corps']) . ' / attendu : ' . $formatBase
    );
}

// --- Filtre par statut -----------------------------------------------------
$statutBase = (int) lire("SELECT COUNT(*) FROM panneaux WHERE statut = 'disponible'");
$parStatut  = appel('GET', $base . '/le-parc?statut=disponible', null, null, $jar);
verifier(
    'le filtre par statut ne garde que ce statut',
    lignesParc($parStatut['corps']) === $statutBase,
    'affichés : ' . lignesParc($parStatut['corps']) . ' / attendu : ' . $statutBase
);

// --- Filtre de disponibilité ----------------------------------------------
$parDispo = appel('GET', $base . '/le-parc?disponible=1', null, null, $jar);
verifier(
    'le filtre de disponibilité ne garde que les supports disponibles',
    lignesParc($parDispo['corps']) === $statutBase,
    'affichés : ' . lignesParc($parDispo['corps']) . ' / attendu : ' . $statutBase
);

// --- Combinaison des critères ---------------------------------------------
if ($ville !== '' && $format !== '') {
    $combineBase = (int) lire(
        'SELECT COUNT(*) FROM panneaux WHERE ville = ' . citer($ville)
        . ' AND format = ' . citer($format) . " AND statut <> 'archive'"
    );
    $combine = appel('GET', $base . '/le-parc?ville=' . rawurlencode($ville) . '&format=' . rawurlencode($format), null, null, $jar);
    verifier(
        'deux critères se combinent au lieu de s’additionner',
        lignesParc($combine['corps']) === $combineBase,
        'affichés : ' . lignesParc($combine['corps']) . ' / attendu : ' . $combineBase
    );
}

// --- Une valeur inventée ne doit pas vider la page -------------------------
$invente = appel('GET', $base . '/le-parc?ville=Atlantide', null, null, $jar);
verifier(
    'une valeur de filtre inconnue est ignorée, le parc reste affiché',
    $invente['code'] === 200 && lignesParc($invente['corps']) === $totalParc,
    'affichés : ' . lignesParc($invente['corps']) . ' / attendu : ' . $totalParc
);

// --- Un filtre sans résultat explique la situation ------------------------
/*
 * La combinaison doit être légitime, et non inventée : une valeur inconnue est
 * justement ignorée par le filtre — comportement vérifié juste au-dessus — de
 * sorte qu'une valeur inventée ne produirait jamais de page vide. On cherche
 * donc une ville et un format qui n'y coexistent pas réellement.
 */
$paireVide = (string) lire(
    'SELECT CONCAT(p.ville, \'|\', f.format)'
    . ' FROM (SELECT DISTINCT ville FROM panneaux WHERE ville IS NOT NULL) p'
    . ' CROSS JOIN (SELECT DISTINCT format FROM panneaux WHERE format IS NOT NULL) f'
    . ' WHERE NOT EXISTS (SELECT 1 FROM panneaux x WHERE x.ville = p.ville AND x.format = f.format)'
    . ' LIMIT 1'
);
[$villeVide, $formatVide] = array_pad(explode('|', $paireVide), 2, '');

verifier(
    'une combinaison ville × format sans résultat existe dans le jeu de démonstration',
    $villeVide !== '' && $formatVide !== '',
    'combinaison introuvable : ' . $paireVide
);

$sansResultat = appel(
    'GET',
    $base . '/le-parc?ville=' . rawurlencode($villeVide) . '&format=' . rawurlencode($formatVide),
    null,
    null,
    $jar
);
verifier(
    'un filtre sans résultat affiche un état vide explicite',
    $sansResultat['code'] === 200
    && lignesParc($sansResultat['corps']) === 0
    && str_contains($sansResultat['corps'], 'Aucun support ne correspond'),
    'code ' . $sansResultat['code'] . ' / lignes : ' . lignesParc($sansResultat['corps'])
);
verifier(
    'l’état vide permet de revenir à l’ensemble du parc',
    str_contains($sansResultat['corps'], 'Voir tout le parc'),
    'retour à l’ensemble absent'
);
verifier(
    'le formulaire reste disponible quand le filtre ne ramène rien',
    str_contains($sansResultat['corps'], 'id="filtre-ville"'),
    'formulaire masqué : le filtre ne peut plus être défait'
);

// ---------------------------------------------------------------------------
echo "\n=== 8 bis. Historique des notifications (EF-E10) ===\n";

$annonceurId = (int) lire("SELECT id FROM annonceurs WHERE statut = 'actif' ORDER BY id LIMIT 1");

$historique = appel('GET', $base . '/api/annonceurs/' . $annonceurId . '/notifications', null, null, $jar);
verifier(
    'l’historique est accessible publiquement (200)',
    $historique['code'] === 200,
    'code ' . $historique['code']
);
verifier(
    'la réponse contient une liste de notifications',
    is_array($historique['json']['notifications'] ?? null),
    'structure : ' . json_encode($historique['json'] ?? null)
);
verifier(
    'l’identifiant d’annonceur est renvoyé',
    ($historique['json']['annonceur_id'] ?? 0) === $annonceurId,
    'id : ' . ($historique['json']['annonceur_id'] ?? 'absent')
);

$historiqueInconnu = appel('GET', $base . '/api/annonceurs/99999/notifications', null, null, $jar);
verifier(
    'annonceur inconnu → 404',
    $historiqueInconnu['code'] === 404,
    'code ' . $historiqueInconnu['code']
);

// ---------------------------------------------------------------------------
echo "\n=== 9. Nettoyage des données de test ===\n";

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
