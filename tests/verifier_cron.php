<?php
/**
 * Vérification de la tâche planifiée d’envoi des notifications.
 * Exécution : php tests/verifier_cron.php
 *
 * Contrôle trois points du cahier des charges :
 *   1. la file des notifications programmées est bien traitée à échéance ;
 *   2. le plafond de quatre notifications par mois et par annonceur est
 *      effectivement appliqué ;
 *   3. le déclencheur HTTP exige le jeton CRON_TOKEN.
 *
 * Les données créées pour le test sont supprimées en fin de parcours.
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';


$base = 'http://localhost/sourianemedia';

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

/** Accès direct à la base, via PDO ou par le client mysql de WAMP. */
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
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    return $pdo;
}

/** Repli par le client mysql si pdo_mysql n’est pas chargé. */
function client(string $sql): string
{
    $bin = 'C:\\wamp64\\bin\\mysql\\mysql9.1.0\\bin\\mysql.exe';
    if (!is_file($bin)) {
        return '';
    }
    $env = env_fichier(dirname(__DIR__) . '/.env');

    return trim((string) shell_exec(sprintf(
        '%s -h%s -P%s -u%s %s -N -B -e %s 2>&1',
        escapeshellarg($bin),
        escapeshellarg((string) ($env['DB_HOST'] ?? '127.0.0.1')),
        escapeshellarg((string) ($env['DB_PORT'] ?? '3306')),
        escapeshellarg((string) ($env['DB_USER'] ?? 'root')),
        escapeshellarg((string) ($env['DB_NAME'] ?? 'souriane')),
        escapeshellarg($sql)
    )));
}

/** Exécute une écriture, renvoie les lignes touchées. */
function ecrire(string $sql): int
{
    $pdo = base();
    if ($pdo instanceof PDO) {
        return (int) $pdo->exec($sql);
    }
    $sortie = client($sql . '; SELECT ROW_COUNT();');
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

    return client($sql);
}

/** Exécute le script de tâche planifiée et renvoie sa sortie. */
function tache(string $arguments = ''): string
{
    $php = 'C:\\wamp64\\bin\\php\\php8.3.14\\php.exe';
    if (!is_file($php)) {
        $php = PHP_BINARY;
    }

    return (string) shell_exec(sprintf(
        '%s %s %s 2>&1',
        escapeshellarg($php),
        escapeshellarg(dirname(__DIR__) . '/cron/envoyer_notifications.php'),
        $arguments
    ));
}

/** Appel HTTP du déclencheur de tâche. */
function http(string $requete): array
{
    $ch = curl_init($requete);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false]);
    $corps = (string) curl_exec($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'corps' => $corps];
}

// ---------------------------------------------------------------------------
echo "\n=== 1. État initial de la file ===\n";

$annonceurTest = (int) lire('SELECT id FROM annonceurs WHERE statut = "actif" ORDER BY id LIMIT 1');
verifier('un annonceur actif est disponible pour le test', $annonceurTest > 0, 'aucun annonceur actif');

$abonnes = (int) lire(
    'SELECT COUNT(*) FROM abonnes WHERE annonceur_id = ' . $annonceurTest
    . ' AND statut = "actif" AND frequence = "chaque_annonce"'
);
verifier('cet annonceur compte au moins un abonné actif', $abonnes > 0, 'abonnés actifs : ' . $abonnes);

// Les abonnés en formule hebdomadaire ne reçoivent pas les envois immédiats :
// ils sont destinés à une synthèse groupée (ch. 5.5).
$abonnesDigest = (int) lire(
    'SELECT COUNT(*) FROM abonnes WHERE annonceur_id = ' . $annonceurTest
    . ' AND statut = "actif" AND frequence = "hebdomadaire"'
);

// Un second annonceur actif distinct sert au test du plafond.
$annonceurCap = (int) lire('SELECT id FROM annonceurs WHERE statut = "actif" AND id <> ' . $annonceurTest . ' ORDER BY id LIMIT 1');
if ($annonceurCap === 0) {
    $annonceurCap = $annonceurTest;
}
verifier('un annonceur sert au contrôle du plafond', $annonceurCap > 0);

// ---------------------------------------------------------------------------
echo "\n=== 2. Traitement d’une notification programmée à échéance ===\n";

$titreTest = 'Vérification automatique ' . bin2hex(random_bytes(3));
ecrire(sprintf(
    "INSERT INTO notifications (annonceur_id, titre, corps, statut, programmee_le)
     VALUES (%d, %s, %s, 'programmee', NOW() - INTERVAL 1 MINUTE)",
    $annonceurTest,
    "'" . $titreTest . "'",
    "'Message de contrôle du traitement de la file.'"
));
$idTest = (int) lire('SELECT id FROM notifications WHERE titre = ' . "'" . $titreTest . "'" . ' ORDER BY id DESC LIMIT 1');
verifier('la notification de test est créée en statut programmée', $idTest > 0, 'id ' . $idTest);

$sortie = tache('--verbeux');
verifier('la tâche planifiée s’exécute sans erreur', !str_contains($sortie, 'Erreur fatale'), trim($sortie));
verifier('la tâche annonce une notification traitée', str_contains($sortie, 'Notifications traitées : 1'), trim($sortie));

$statutApres = lire('SELECT statut FROM notifications WHERE id = ' . $idTest);
verifier('la notification passe en statut « envoyee »', $statutApres === 'envoyee', 'statut : ' . $statutApres);

$envoyeeLe = lire('SELECT IFNULL(envoyee_le, "") FROM notifications WHERE id = ' . $idTest);
verifier('la date d’envoi est renseignée', $envoyeeLe !== '', 'valeur : ' . $envoyeeLe);

$nbEnvois = (int) lire('SELECT COUNT(*) FROM envois WHERE notification_id = ' . $idTest);
verifier(
    'une ligne d’envoi est créée par abonné en formule immédiate',
    $nbEnvois === $abonnes,
    'envois : ' . $nbEnvois . ' / abonnés immédiats : ' . $abonnes
);

if ($abonnesDigest > 0) {
    $envoisDigest = (int) lire(
        'SELECT COUNT(*) FROM envois e JOIN abonnes ab ON ab.id = e.abonne_id
         WHERE e.notification_id = ' . $idTest . ' AND ab.frequence = "hebdomadaire"'
    );
    verifier(
        'les abonnés en formule hebdomadaire sont écartés de l’envoi immédiat',
        $envoisDigest === 0,
        'envois créés à tort : ' . $envoisDigest
    );
}

$statutsEnvoi = lire('SELECT GROUP_CONCAT(DISTINCT statut) FROM envois WHERE notification_id = ' . $idTest);
verifier(
    'chaque envoi est soldé (envoye, echec ou expire)',
    !str_contains($statutsEnvoi, 'en_attente'),
    'statuts : ' . $statutsEnvoi
);
verifier(
    'les échecs sont consignés avec leur motif',
    (int) lire('SELECT COUNT(*) FROM envois WHERE notification_id = ' . $idTest . ' AND statut = "echec" AND erreur IS NOT NULL')
        === (int) lire('SELECT COUNT(*) FROM envois WHERE notification_id = ' . $idTest . ' AND statut = "echec"'),
    'des échecs sans motif subsistent'
);

// Rejeu : la notification ne doit pas repartir
$sortieRejeu = tache();
verifier('un second passage ne renvoie pas la notification', str_contains($sortieRejeu, 'traitées : 0'), trim($sortieRejeu));

// ---------------------------------------------------------------------------
echo "\n=== 3. Plafond mensuel de quatre envois par annonceur ===\n";

// On fabrique quatre notifications déjà envoyées ce mois-ci pour l’annonceur
// de contrôle, puis on tente un cinquième envoi.
$creees = 0;
for ($i = 1; $i <= 4; $i++) {
    ecrire(sprintf(
        "INSERT INTO notifications (annonceur_id, titre, corps, statut, envoyee_le)
         VALUES (%d, %s, 'Historique du plafond.', 'envoyee', NOW())",
        $annonceurCap,
        "'Plafond test " . $i . ' ' . bin2hex(random_bytes(3)) . "'"
    ));
    $creees++;
}
$deja = (int) lire(
    "SELECT COUNT(*) FROM notifications WHERE annonceur_id = " . $annonceurCap
    . " AND statut = 'envoyee' AND envoyee_le >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
);
verifier('l’annonceur de contrôle atteint le plafond', $deja >= 4, 'envois ce mois : ' . $deja);

$titreBloque = 'Cinquième envoi ' . bin2hex(random_bytes(3));
ecrire(sprintf(
    "INSERT INTO notifications (annonceur_id, titre, corps, statut, programmee_le)
     VALUES (%d, %s, 'Ce message doit être refusé par le plafond.', 'programmee', NOW() - INTERVAL 1 MINUTE)",
    $annonceurCap,
    "'" . $titreBloque . "'"
));
$idBloque = (int) lire('SELECT id FROM notifications WHERE titre = ' . "'" . $titreBloque . "'" . ' ORDER BY id DESC LIMIT 1');

$sortiePlafond = tache('--verbeux');
verifier(
    'le dépassement du plafond est signalé',
    str_contains($sortiePlafond, 'Plafond atteint'),
    trim($sortiePlafond)
);

$statutBloque = lire('SELECT statut FROM notifications WHERE id = ' . $idBloque);
verifier(
    'la notification bloquée n’est pas marquée envoyée',
    $statutBloque !== 'envoyee',
    'statut : ' . $statutBloque
);
verifier(
    'aucune ligne d’envoi n’est créée pour la notification bloquée',
    (int) lire('SELECT COUNT(*) FROM envois WHERE notification_id = ' . $idBloque) === 0
);

// Le refus est tracé dans le journal technique, qui est un fichier
// (storage/logs/app-AAAA-MM-JJ.log), et non une table.
$fichierLog = dirname(__DIR__) . '/storage/logs/app-' . date('Y-m-d') . '.log';
$contenuLog = is_file($fichierLog) ? (string) file_get_contents($fichierLog) : '';
verifier(
    'le refus est consigné dans le journal technique',
    str_contains($contenuLog, 'Diffusion impossible') && str_contains($contenuLog, 'Plafond atteint'),
    'journal : ' . ($contenuLog === '' ? 'fichier absent' : 'aucune trace du plafond')
);

// ---------------------------------------------------------------------------
echo "\n=== 4. Déclencheur HTTP et verrou d’exclusion ===\n";

$sansJeton = http($base . '/tache/notifications');
verifier('sans jeton, le déclencheur répond 403', $sansJeton['code'] === 403, 'code ' . $sansJeton['code']);

$jetonFaux = http($base . '/tache/notifications?cle=jeton-invalide');
verifier('avec un jeton invalide, le déclencheur répond 403', $jetonFaux['code'] === 403, 'code ' . $jetonFaux['code']);

$env = env_fichier(dirname(__DIR__) . '/.env');
$jeton = (string) ($env['CRON_TOKEN'] ?? '');
verifier('un jeton est configuré dans .env', $jeton !== '');

if ($jeton !== '') {
    $valide = http($base . '/tache/notifications?cle=' . rawurlencode($jeton));
    verifier('avec le bon jeton, le déclencheur répond 200', $valide['code'] === 200, 'code ' . $valide['code']);
    verifier(
        'la réponse décrit le traitement',
        str_contains($valide['corps'], 'file des notifications'),
        trim($valide['corps'])
    );
}

// ---------------------------------------------------------------------------
echo "\n=== 5. Nettoyage des données de test ===\n";

$supprimesEnvois = ecrire(
    'DELETE FROM envois WHERE notification_id IN ('
    . 'SELECT id FROM (SELECT id FROM notifications WHERE titre LIKE "Plafond test %" '
    . 'OR titre LIKE "Vérification automatique %" OR titre LIKE "Cinquième envoi %") AS t)'
);
$supprimees = ecrire(
    'DELETE FROM notifications WHERE titre LIKE "Plafond test %" '
    . 'OR titre LIKE "Vérification automatique %" OR titre LIKE "Cinquième envoi %"'
);
verifier('les notifications de test sont supprimées', $supprimees >= 5, 'lignes : ' . $supprimees);

$restantes = (int) lire(
    'SELECT COUNT(*) FROM notifications WHERE titre LIKE "Plafond test %" '
    . 'OR titre LIKE "Vérification automatique %" OR titre LIKE "Cinquième envoi %"'
);
verifier('aucune notification de test ne subsiste', $restantes === 0, 'restantes : ' . $restantes);

$totalNotifications = (int) lire('SELECT COUNT(*) FROM notifications');
verifier('la base retrouve ses trois notifications d’origine', $totalNotifications === 3, 'total : ' . $totalNotifications);

$totalAbonnes = (int) lire('SELECT COUNT(*) FROM abonnes');
verifier('les abonnés de démonstration sont intacts', $totalAbonnes === 3, 'total : ' . $totalAbonnes);

// ---------------------------------------------------------------------------
$total = $reussis + $echecs;
echo "\n" . str_repeat('=', 62) . "\n";
echo sprintf("Tâche planifiée — %d/%d vérifications réussies", $reussis, $total) . "\n";
echo str_repeat('=', 62) . "\n";

exit($echecs === 0 ? 0 : 1);
