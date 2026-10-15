<?php
/**
 * Vérification du parcours « Demande de proposition » (prospect → régie).
 * Exécution : php tests/verifier_demande.php
 *
 * Contrôle le formulaire public (GET), l’enregistrement (POST valide, CSRF
 * invalide, champs manquants) et le câblage de la route back-office.
 */

declare(strict_types=1);

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
        CURLOPT_TIMEOUT        => 20,
    ]);
    if ($champs !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($champs));
    }
    $brut   = (string) curl_exec($ch);
    $code   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $taille = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return [
        'code'    => $code,
        'entetes' => substr($brut, 0, $taille),
        'corps'   => substr($brut, $taille),
    ];
}

function jeton(string $html): string
{
    if (preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) === 1) {
        return $m[1];
    }

    return '';
}

function redirigeVers(string $entetes, string $fragment): bool
{
    if (preg_match('/^Location:\s*(.+)$/mi', $entetes, $m) !== 1) {
        return false;
    }

    return str_contains(trim($m[1]), $fragment);
}

echo "Parcours Demande de proposition\n";
echo str_repeat('=', 60) . "\n";

// 1. Le formulaire public est accessible et complet.
$accueil = appel('GET', $base . '/annonceurs/demande', null, '');
verifier('GET /annonceurs/demande répond 200', $accueil['code'] === 200, 'code=' . $accueil['code']);
verifier('Le formulaire contient le champ enseigne', str_contains($accueil['corps'], 'name="enseigne"'));
verifier('Le formulaire contient le champ e-mail', str_contains($accueil['corps'], 'name="email"'));
verifier('Le jeton CSRF est présent', jeton($accueil['corps']) !== '');
verifier('La ville est proposée (liste)', str_contains($accueil['corps'], 'name="ville"'));

// 2. Soumission valide : enregistrement + message de succès après redirection.
$jar = sys_get_temp_dir() . '/souriane-demande-' . uniqid() . '.txt';
$page = appel('GET', $base . '/annonceurs/demande', null, $jar);
$tok  = jeton($page['corps']);
$ok   = appel('POST', $base . '/annonceurs/demande', [
    '_csrf'    => $tok,
    'enseigne' => 'Quincaillerie Bignona',
    'contact'  => 'Aminata Diallo',
    'email'    => 'aminata@quincaillerie.sn',
    'ville'    => 'Bignona',
    'formats'  => ['2m x 3m'],
    'periode'  => 'Février 2026',
    'budget'   => '500 000 FCFA',
    'message'  => 'Vitrine en bordure de route.',
], $jar);
verifier('POST valide redirige (302)', $ok['code'] === 302, 'code=' . $ok['code']);
verifier('Redirection vers la page de demande', redirigeVers($ok['entetes'], 'annonceurs/demande'));

$suivi = appel('GET', $base . '/annonceurs/demande', null, $jar);
verifier(
    'Le message de succès s’affiche après envoi',
    str_contains($suivi['corps'], 'Votre demande a bien été envoyée'),
    'flash non affiché'
);

// 3. CSRF invalide → 419.
$jar2 = sys_get_temp_dir() . '/souriane-demande2-' . uniqid() . '.txt';
$page2 = appel('GET', $base . '/annonceurs/demande', null, $jar2);
$tok2  = jeton($page2['corps']);
$csrf  = appel('POST', $base . '/annonceurs/demande', [
    '_csrf'    => 'jeton-invalide-' . uniqid(),
    'enseigne' => 'Test',
    'contact'  => 'Test',
    'email'    => 'test@example.sn',
    'ville'    => 'Bignona',
], $jar2);
verifier('POST CSRF invalide renvoie 419', $csrf['code'] === 419, 'code=' . $csrf['code']);

// 4. Champs requis manquants → 422 + erreurs affichées.
$jar3 = sys_get_temp_dir() . '/souriane-demande3-' . uniqid() . '.txt';
$page3 = appel('GET', $base . '/annonceurs/demande', null, $jar3);
$tok3  = jeton($page3['corps']);
$invalide = appel('POST', $base . '/annonceurs/demande', [
    '_csrf' => $tok3,
], $jar3);
verifier('POST incomplet renvoie 422', $invalide['code'] === 422, 'code=' . $invalide['code']);
verifier(
    'Le message d’erreur e-mail est affiché',
    str_contains($invalide['corps'], 'e-mail est nécessaire'),
    ''
);

// 5. La route back-office est câblée (redirige vers la connexion si anonyme).
$bo = appel('GET', $base . '/admin/demandes', null, '');
verifier(
    'GET /admin/demandes est protégée (redirection connexion)',
    $bo['code'] === 302 && redirigeVers($bo['entetes'], 'admin/connexion'),
    'code=' . $bo['code']
);

echo str_repeat('=', 60) . "\n";
echo "Résultat : $reussis réussite(s), $echecs échec(s)\n";
exit($echecs === 0 ? 0 : 1);
