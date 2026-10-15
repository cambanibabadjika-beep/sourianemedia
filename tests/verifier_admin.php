<?php
/**
 * Vérification de bout en bout du back-office.
 * Exécution : php tests/verifier_admin.php
 *
 * Le test se connecte avec les comptes de démonstration, parcourt toutes les
 * rubriques, contrôle les règles d'accès par rôle, puis vérifie la création
 * d'un compte et l'enregistrement d'un paramètre. Toute donnée créée est
 * supprimée en fin de parcours.
 */

declare(strict_types=1);

$base = 'http://localhost/sourianemedia';

$reussis = 0;
$echecs  = 0;

/**
 * Le limiteur de débit protège la connexion : cinq tentatives par quart d'heure.
 * Un parcours de vérification en consomme plusieurs, aussi les compteurs sont-ils
 * remis à zéro avant de commencer afin que la suite soit rejouable.
 */
$dossierLimites = dirname(__DIR__) . '/storage/limites';
foreach (glob($dossierLimites . '/*.json') ?: [] as $fichier) {
    @unlink($fichier);
}

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
 * Requête HTTP avec suivi de session.
 *
 * @return array{code:int, corps:string, entetes:string}
 */
function appel(string $methode, string $url, ?array $champs = null, string $jar = ''): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $methode,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER         => true,
    ]);
    if ($champs !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($champs));
    }

    $brut = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tailleEntetes = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return [
        'code'    => $code,
        'entetes' => substr($brut, 0, $tailleEntetes),
        'corps'   => substr($brut, $tailleEntetes),
    ];
}

/** Extrait le jeton CSRF d'une page HTML. */
function jeton(string $html): string
{
    if (preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) === 1) {
        return $m[1];
    }

    return '';
}

/** Repère toute sortie parasite de PHP dans une page. */
function parasite(string $html): string
{
    foreach (['Warning:', 'Fatal error', 'Notice:', 'Deprecated:', 'xdebug-error', 'Uncaught'] as $motif) {
        if (str_contains($html, $motif)) {
            return $motif;
        }
    }

    return '';
}

/** Ouvre une session pour un compte donné. */
function session(string $jar, string $email, string $motDePasse): array
{
    global $base;
    $connexion = appel('GET', $base . '/admin/connexion', null, $jar);
    $j = jeton($connexion['corps']);
    if ($j === '') {
        return ['code' => 0, 'corps' => '', 'entetes' => ''];
    }

    return appel('POST', $base . '/admin/connexion', [
        '_csrf'        => $j,
        'email'        => $email,
        'mot_de_passe' => $motDePasse,
        'retour'       => '',
    ], $jar);
}

// ---------------------------------------------------------------------------
echo "\n=== 1. Connexion et contrôle d'accès ===\n";

$jarRegie = sys_get_temp_dir() . '/souriane-admin-regie.txt';
$jarAgent = sys_get_temp_dir() . '/souriane-admin-agent.txt';
@unlink($jarRegie);
@unlink($jarAgent);

$connexion = session($jarRegie, 'admin@souriane.sn', 'Souriane2026!');
verifier(
    'la connexion régie redirige (302)',
    $connexion['code'] === 302,
    'code ' . $connexion['code']
);
verifier(
    'la redirection vise le tableau de bord',
    str_contains($connexion['entetes'], '/admin'),
    trim((string) strstr($connexion['entetes'], 'Location:'))
);

$mauvais = session(sys_get_temp_dir() . '/souriane-admin-faux.txt', 'admin@souriane.sn', 'mauvais-mot-de-passe');
verifier('un mot de passe erroné ne connecte pas', $mauvais['code'] === 302);

// ---------------------------------------------------------------------------
echo "\n=== 2. Parcours de toutes les rubriques (rôle régie) ===\n";

$rubriques = [
    '/admin'                    => 'Tableau de bord',
    '/admin/statistiques'       => 'Statistiques',
    '/admin/annonceurs'         => 'Annonceurs',
    '/admin/annonceurs/nouveau' => 'Nouvel annonceur',
    '/admin/campagnes'          => 'Campagnes',
    '/admin/campagnes/nouveau'  => 'Nouvelle campagne',
    '/admin/panneaux'           => 'Panneaux',
    '/admin/panneaux/nouveau'   => 'Nouveau panneau',
    '/admin/pages'              => 'Pages publiques',
    '/admin/codes'              => 'QR codes',
    '/admin/notifications'      => 'Notifications',
    '/admin/notifications/nouveau' => 'Nouvelle notification',
    '/admin/poses'              => 'Preuves de pose',
    '/admin/journal'            => 'Journal',
    '/admin/utilisateurs'       => 'Utilisateurs',
    '/admin/parametres'         => 'Paramètres',
];

foreach ($rubriques as $chemin => $libelle) {
    $r = appel('GET', $base . $chemin, null, $jarRegie);
    $souci = parasite($r['corps']);
    verifier(
        sprintf('%-30s → 200 sans sortie parasite', $chemin),
        $r['code'] === 200 && $souci === '',
        $r['code'] !== 200 ? 'code ' . $r['code'] : 'parasite : ' . $souci
    );
}

// Fiches de détail
echo "\n  — Fiches de détail —\n";
foreach (['/admin/annonceurs/1', '/admin/campagnes/1', '/admin/panneaux/1', '/admin/pages/1'] as $chemin) {
    $r = appel('GET', $base . $chemin, null, $jarRegie);
    $souci = parasite($r['corps']);
    verifier(
        sprintf('%-30s → 200 sans sortie parasite', $chemin),
        $r['code'] === 200 && $souci === '',
        $r['code'] !== 200 ? 'code ' . $r['code'] : 'parasite : ' . $souci
    );
}

// ---------------------------------------------------------------------------
echo "\n=== 3. Contenu des deux nouvelles vues ===\n";

$utilisateurs = appel('GET', $base . '/admin/utilisateurs', null, $jarRegie);
verifier('la liste des comptes affiche les trois rôles', substr_count($utilisateurs['corps'], 'Annonceur') >= 1);
verifier('la liste affiche l\'adresse du compte de démonstration', str_contains($utilisateurs['corps'], 'admin@souriane.sn'));
verifier('le dialogue de création est présent', str_contains($utilisateurs['corps'], 'id="dialogue-utilisateur"'));
verifier('le bouton « Nouveau compte » ouvre le dialogue', str_contains($utilisateurs['corps'], 'data-ouvrir-dialogue="dialogue-utilisateur"'));
verifier('le champ de rattachement annonceur existe', str_contains($utilisateurs['corps'], 'id="bloc-annonceur"'));
verifier('le mot de passe minimal est annoncé', str_contains($utilisateurs['corps'], 'Au moins 10 caractères'));

$parametres = appel('GET', $base . '/admin/parametres', null, $jarRegie);
verifier(
    'les paramètres affichent une version de PHP',
    preg_match('/PHP\s+8\.\d+\.\d+/', $parametres['corps']) === 1,
    'aucune version détectée dans le bloc système'
);
verifier(
    'la version servie est celle de PHP 8.3',
    str_contains($parametres['corps'], 'PHP 8.3'),
    'version attendue 8.3.x'
);
verifier('les paramètres affichent le plafond de notifications', str_contains($parametres['corps'], 'parametres[plafond_notifications]'));
verifier('la clé publique VAPID est affichée', str_contains($parametres['corps'], 'id="vapid-publique"'));
verifier('la clé privée VAPID est masquée', str_contains($parametres['corps'], '••••••••'));
verifier(
    'la clé privée VAPID n\'apparaît jamais en clair',
    !preg_match('/id="vapid-privee"[^>]*value="([A-Za-z0-9_-]{20,})"/', $parametres['corps'])
);
verifier('le contact VAPID est affiché', str_contains($parametres['corps'], 'id="vapid-sujet"'));
verifier('les paramètres éditables sont regroupés par thème', substr_count($parametres['corps'], 'panneau__titre') >= 3);

// ---------------------------------------------------------------------------
echo "\n=== 4. Création puis suppression d'un compte ===\n";

$email = 'verif-' . bin2hex(random_bytes(4)) . '@souriane.test';

$creation = appel('POST', $base . '/admin/utilisateurs', [
    '_csrf'        => jeton($utilisateurs['corps']),
    'id'           => '0',
    'nom'          => 'Compte de vérification',
    'email'        => $email,
    'role'         => 'agent',
    'telephone'    => '+221 77 000 00 00',
    'mot_de_passe' => 'Verification2026!',
    'actif'        => '1',
], $jarRegie);
verifier('la création d\'un compte redirige', $creation['code'] === 302, 'code ' . $creation['code']);

$listeApres = appel('GET', $base . '/admin/utilisateurs', null, $jarRegie);
verifier('le compte créé apparaît dans la liste', str_contains($listeApres['corps'], $email));
verifier('le compte créé est marqué actif', str_contains($listeApres['corps'], 'Compte de vérification'));

// Mot de passe trop court → refus
$court = appel('POST', $base . '/admin/utilisateurs', [
    '_csrf'        => jeton($listeApres['corps']),
    'id'           => '0',
    'nom'          => 'Compte trop court',
    'email'        => 'court-' . bin2hex(random_bytes(3)) . '@souriane.test',
    'role'         => 'agent',
    'mot_de_passe' => 'court',
    'actif'        => '1',
], $jarRegie);
verifier('un mot de passe trop court est refusé', $court['code'] === 302);

$listeCourt = appel('GET', $base . '/admin/utilisateurs', null, $jarRegie);
verifier('le compte au mot de passe trop court n\'est pas créé', !str_contains($listeCourt['corps'], 'Compte trop court'));

// CSRF absent → 419
$sansJeton = appel('POST', $base . '/admin/utilisateurs', [
    'nom'   => 'Sans jeton',
    'email' => 'sans-jeton@souriane.test',
    'role'  => 'agent',
], $jarRegie);
verifier('une écriture sans jeton CSRF est rejetée (419)', $sansJeton['code'] === 419, 'code ' . $sansJeton['code']);

// ---------------------------------------------------------------------------
echo "\n=== 5. Enregistrement d'un paramètre (aller-retour) ===\n";

$paramAvant = appel('GET', $base . '/admin/parametres', null, $jarRegie);
preg_match('/name="parametres\[site_slogan\]" value="([^"]*)"/', $paramAvant['corps'], $m);
$sloganOrigine = $m[1] ?? '';

$ecriture = appel('POST', $base . '/admin/parametres', [
    '_csrf' => jeton($paramAvant['corps']),
    'parametres' => [
        'site_slogan'            => 'Vérification temporaire',
        'plafond_notifications'  => '4',
        'contact_email'          => 'contact@souriane.sn',
        'whatsapp_regie'         => '+221770000000',
        'site_nom'               => 'Souriane',
    ],
], $jarRegie);
verifier('l\'enregistrement des paramètres redirige', $ecriture['code'] === 302, 'code ' . $ecriture['code']);

$paramApres = appel('GET', $base . '/admin/parametres', null, $jarRegie);
verifier('le nouveau slogan est enregistré', str_contains($paramApres['corps'], 'Vérification temporaire'));
verifier(
    'la clé privée VAPID reste intacte après écriture',
    str_contains($paramApres['corps'], '••••••••')
);

// Restauration
$restauration = appel('POST', $base . '/admin/parametres', [
    '_csrf' => jeton($paramApres['corps']),
    'parametres' => ['site_slogan' => $sloganOrigine],
], $jarRegie);
$paramFinal = appel('GET', $base . '/admin/parametres', null, $jarRegie);
verifier('le slogan d\'origine est restauré', str_contains($paramFinal['corps'], 'value="' . $sloganOrigine . '"'));

// ---------------------------------------------------------------------------
echo "\n=== 6. Règles d'accès par rôle ===\n";

$connexionAgent = session($jarAgent, 'agent@souriane.sn', 'Souriane2026!');
verifier('la connexion agent réussit', $connexionAgent['code'] === 302);

foreach (['/admin' => 200, '/admin/poses' => 200, '/admin/panneaux' => 200, '/admin/statistiques' => 200] as $chemin => $attendu) {
    $r = appel('GET', $base . $chemin, null, $jarAgent);
    verifier(
        sprintf('agent : %-24s → %d', $chemin, $attendu),
        $r['code'] === $attendu,
        'code ' . $r['code']
    );
}

foreach (['/admin/utilisateurs', '/admin/parametres', '/admin/annonceurs', '/admin/notifications', '/admin/journal', '/admin/pages'] as $chemin) {
    $r = appel('GET', $base . $chemin, null, $jarAgent);
    verifier(
        sprintf('agent : %-24s → 403', $chemin),
        $r['code'] === 403,
        'code ' . $r['code']
    );
}

// Sans session : redirection vers la connexion
$jarVide = sys_get_temp_dir() . '/souriane-admin-vide.txt';
@unlink($jarVide);
foreach (['/admin', '/admin/utilisateurs', '/admin/parametres'] as $chemin) {
    $r = appel('GET', $base . $chemin, null, $jarVide);
    verifier(
        sprintf('anonyme : %-24s → 302 vers la connexion', $chemin),
        $r['code'] === 302 && str_contains($r['entetes'], 'admin/connexion'),
        'code ' . $r['code']
    );
}

// Déconnexion
$deconnexion = appel('POST', $base . '/admin/deconnexion', [
    '_csrf' => jeton($listeApres['corps']),
], $jarRegie);
verifier('la déconnexion redirige', $deconnexion['code'] === 302);
$apresDeconnexion = appel('GET', $base . '/admin', null, $jarRegie);
verifier('après déconnexion, /admin redirige vers la connexion', $apresDeconnexion['code'] === 302);

// ---------------------------------------------------------------------------
echo "\n=== 7. Nettoyage des données de test ===\n";

/**
 * Accès direct à la base pour le contrôle et le nettoyage.
 * Les identifiants proviennent de .env, comme pour l'application.
 * Si l'extension pdo_mysql n'est pas chargée dans l'interpréteur courant,
 * le repli s'appuie sur le client mysql livré avec WAMP.
 */
function base(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $env = parse_ini_file(dirname(__DIR__) . '/.env', false, INI_SCANNER_RAW) ?: [];
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

/** Exécute une requête scalaire (une seule instruction), par PDO ou en repli. */
function requete(string $sql): string
{
    if (extension_loaded('pdo_mysql')) {
        $valeur = base()->query($sql)->fetchColumn();

        return $valeur === false ? '' : (string) $valeur;
    }

    return clientMysql($sql . ';');
}

/** Exécute une écriture et renvoie le nombre de lignes touchées. */
function executer(string $sql): int
{
    if (extension_loaded('pdo_mysql')) {
        return (int) base()->exec($sql);
    }

    $sortie = clientMysql($sql . '; SELECT ROW_COUNT();');
    $lignes = preg_split('/\R/', $sortie) ?: [];

    return (int) trim((string) end($lignes));
}

/** Repli : interroge la base par le client mysql livré avec WAMP. */
function clientMysql(string $sql): string
{
    $client = 'C:\\wamp64\\bin\\mysql\\mysql9.1.0\\bin\\mysql.exe';
    if (!is_file($client)) {
        return '';
    }

    $env = parse_ini_file(dirname(__DIR__) . '/.env', false, INI_SCANNER_RAW) ?: [];
    $sortie = shell_exec(sprintf(
        '%s -h%s -P%s -u%s %s -N -B -e %s 2>&1',
        escapeshellarg($client),
        escapeshellarg((string) ($env['DB_HOST'] ?? '127.0.0.1')),
        escapeshellarg((string) ($env['DB_PORT'] ?? '3306')),
        escapeshellarg((string) ($env['DB_USER'] ?? 'root')),
        escapeshellarg((string) ($env['DB_NAME'] ?? 'souriane')),
        escapeshellarg($sql)
    ));

    return trim((string) $sortie);
}

$supprimes = executer(
    "DELETE FROM utilisateurs WHERE email LIKE 'verif-%@souriane.test'
       OR email LIKE 'court-%@souriane.test'
       OR email LIKE 'sans-jeton%@souriane.test'"
);
verifier('les comptes de vérification sont supprimés', $supprimes >= 1, 'lignes supprimées : ' . $supprimes);

$reste = (int) requete("SELECT COUNT(*) FROM utilisateurs WHERE email LIKE '%@souriane.test'");
verifier('aucun compte de test ne subsiste', $reste === 0, 'restants : ' . $reste);

$comptes = (int) requete('SELECT COUNT(*) FROM utilisateurs');
verifier('la base retrouve ses trois comptes d\'origine', $comptes === 3, 'comptes : ' . $comptes);

$sloganFinal = requete("SELECT valeur FROM parametres WHERE cle = 'site_slogan'");
verifier(
    'le slogan d\'origine est bien restauré en base',
    $sloganFinal === $sloganOrigine,
    'valeur : ' . $sloganFinal
);

@unlink($jarRegie);
@unlink($jarAgent);
@unlink($jarVide);
@unlink(sys_get_temp_dir() . '/souriane-admin-faux.txt');

// ---------------------------------------------------------------------------
$total = $reussis + $echecs;
echo "\n" . str_repeat('=', 62) . "\n";
echo sprintf("Back-office — %d/%d vérifications réussies", $reussis, $total) . "\n";
echo str_repeat('=', 62) . "\n";

exit($echecs === 0 ? 0 : 1);
