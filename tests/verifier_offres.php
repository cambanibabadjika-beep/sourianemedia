<?php
/**
 * Vérification de la validité des offres sur la page « Découvrir » (C1).
 * Exécution : php tests/verifier_offres.php
 *
 * La carte d’une offre annonce « Jusqu’au … ». La page doit donc respecter
 * cette échéance : une offre dont la date de fin est dépassée ne doit plus y
 * figurer. Le défaut corrigé ici était réel — `decouvrir()` lisait `debut` et
 * `fin` mais ne les exploitait jamais, si bien qu’une offre périmée restait
 * affichée indéfiniment.
 *
 * La suite crée trois offres réelles — expirée, en cours, sans échéance — puis
 * interroge la vraie page par HTTP. Elle vérifie aussi que les offres à venir
 * et sans date de fin restent visibles : le filtre ne doit porter que sur
 * l’expiration, pas sur l’absence de dates.
 *
 * Les données créées sont supprimées en fin de parcours.
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once dirname(__DIR__) . '/app/bootstrap.php';

use Souriane\Core\Database;

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

function appel(string $url, string $jar = '', ?array $champs = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
    ]);
    if ($champs !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($champs));
    }
    $brut   = (string) curl_exec($ch);
    $code   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $taille = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['code' => $code, 'corps' => substr($brut, $taille)];
}

$db       = Database::instance();
$marqueur = 'test-offre-' . bin2hex(random_bytes(4));

// Supprime tout ce que la suite a pu créer, y compris après un arrêt brutal.
$nettoyer = static function () use ($db, $marqueur): void {
    $pages = $db->select(
        'SELECT id FROM pages WHERE adresse_courte LIKE :m',
        ['m' => $marqueur . '%']
    );
    foreach ($pages as $ligne) {
        $db->query('DELETE FROM sections_page WHERE page_id = :id', ['id' => (int) $ligne['id']]);
    }
    $db->query('DELETE FROM pages WHERE adresse_courte LIKE :m', ['m' => $marqueur . '%']);
    $db->query('DELETE FROM annonceurs WHERE adresse_courte LIKE :m', ['m' => $marqueur . '%']);
};

$nettoyer();

echo "Validité des offres — page Découvrir\n";
echo str_repeat('=', 62) . "\n";

// ---------------------------------------------------------------------------
echo "\n=== 1. Mise en place ===\n";

$annonceurId = $db->insert('annonceurs', [
    'nom'            => 'Annonceur de vérification C1',
    'adresse_courte' => $marqueur,
    'description'    => 'Créé par tests/verifier_offres.php.',
    'ville'          => 'Bignona',
    'statut'         => 'actif',
]);
verifier('l’annonceur de test est créé', $annonceurId > 0, 'id ' . $annonceurId);

$hier   = date('Y-m-d', strtotime('-1 day'));
$demain = date('Y-m-d', strtotime('+1 day'));

/** Crée une page publiée portant une section « offre » aux dates données. */
$creerPage = static function (?string $debut, ?string $fin, string $suffixe) use ($db, $annonceurId, $marqueur): array {
    $adresse = $marqueur . '-' . $suffixe;
    $pageId  = $db->insert('pages', [
        'annonceur_id'   => $annonceurId,
        'adresse_courte' => $adresse,
        'titre'          => 'Offre ' . $suffixe,
        'statut'         => 'publiee',
    ]);
    $db->insert('sections_page', [
        'page_id' => $pageId,
        'type'    => 'offre',
        'titre'   => 'Offre en cours',
        'contenu' => (string) json_encode([
            'titre' => 'Offre ' . $suffixe,
            'texte' => 'Texte de vérification pour l’offre ' . $suffixe . '.',
            'debut' => $debut,
            'fin'   => $fin,
        ], JSON_UNESCAPED_UNICODE),
        'ordre'   => 1,
        'actif'   => 1,
    ]);

    return ['id' => $pageId, 'adresse' => $adresse];
};

$expiree = $creerPage(date('Y-m-d', strtotime('-10 days')), $hier, 'expiree');
$encours = $creerPage($hier, $demain, 'encours');
$sansFin = $creerPage(null, null, 'sansfin');

verifier('les trois pages de test sont publiées', $expiree['id'] > 0 && $encours['id'] > 0 && $sansFin['id'] > 0);

// ---------------------------------------------------------------------------
echo "\n=== 2. Le helper de validité ===\n";

verifier('une offre dont la fin est passée est expirée', offre_expiree(['fin' => $hier]) === true);
verifier('une offre dont la fin est à venir ne l’est pas', offre_expiree(['fin' => $demain]) === false);
verifier('une offre sans date de fin ne l’est pas', offre_expiree(['fin' => null]) === false);
verifier('une offre sans clé « fin » ne l’est pas', offre_expiree([]) === false);
verifier('une date illisible ne périme rien', offre_expiree(['fin' => 'bientôt']) === false);
verifier('une fin égale au jour même n’expire pas encore', offre_expiree(['fin' => date('Y-m-d')]) === false);

// ---------------------------------------------------------------------------
echo "\n=== 3. La page « Découvrir » ===\n";

$page = appel($base . '/decouvrir');
verifier('la page répond 200', $page['code'] === 200, 'code ' . $page['code']);
verifier('la page contient au moins une carte d’offre', str_contains($page['corps'], 'offre-carte'),
    'aucune carte rendue : le contrôle suivant ne prouverait rien');

verifier('l’offre expirée est absente de la page',
    !str_contains($page['corps'], $expiree['adresse']),
    'l’adresse ' . $expiree['adresse'] . ' figure encore dans la page');
verifier('l’offre en cours est présente', str_contains($page['corps'], $encours['adresse']));
verifier('l’offre sans échéance est présente', str_contains($page['corps'], $sansFin['adresse']));

// Le filtre vit dans le contrôleur, pas dans la requête : la requête doit
// continuer de servir les trois pages, sans quoi le contrôle ci-dessus
// passerait pour une mauvaise raison.
$servies = \Souriane\Models\Page::publieesAvecOffre(60);
$adresses = array_column($servies, 'adresse_courte');
verifier('la requête, elle, sert bien les trois pages',
    in_array($expiree['adresse'], $adresses, true)
        && in_array($encours['adresse'], $adresses, true)
        && in_array($sansFin['adresse'], $adresses, true));

// ---------------------------------------------------------------------------
echo "\n=== 4. Le badge dans le formulaire admin ===\n";

$jar = sys_get_temp_dir() . '/souriane-offres-admin.txt';
@unlink($jar);

$pageConnexion = appel($base . '/admin/connexion', $jar);
$jeton = '';
if (preg_match('/name="_csrf" value="([^"]+)"/', $pageConnexion['corps'], $m) === 1) {
    $jeton = $m[1];
}

$connexion = appel($base . '/admin/connexion', $jar, [
    '_csrf'        => $jeton,
    'email'        => 'admin@souriane.sn',
    'mot_de_passe' => 'Souriane2026!',
    'retour'       => '',
]);
verifier('la connexion régie aboutit', $connexion['code'] === 302, 'code ' . $connexion['code']);

$formExpiree = appel($base . '/admin/pages/' . $expiree['id'], $jar);
verifier('le formulaire de l’offre expirée est servi (200)',
    $formExpiree['code'] === 200, 'code ' . $formExpiree['code']);
verifier('l’offre expirée porte le badge « Expirée »',
    str_contains($formExpiree['corps'], 'Expirée'),
    'aucun badge rendu');
verifier('le formulaire signale la conséquence sur la page Découvrir',
    str_contains($formExpiree['corps'], 'n’apparaît plus sur la page'));

$formEncours = appel($base . '/admin/pages/' . $encours['id'], $jar);
verifier('le formulaire de l’offre en cours est servi (200)',
    $formEncours['code'] === 200, 'code ' . $formEncours['code']);
verifier('l’offre en cours ne porte pas le badge',
    !str_contains($formEncours['corps'], 'Expirée'),
    'un badge apparaît sur une offre valide');

@unlink($jar);

// ---------------------------------------------------------------------------
echo "\n=== 5. Nettoyage ===\n";

$nettoyer();
$restantes = (int) $db->selectValue(
    'SELECT COUNT(*) FROM pages WHERE adresse_courte LIKE :m',
    ['m' => $marqueur . '%'],
    0
);
verifier('les pages de test sont supprimées', $restantes === 0, 'restantes : ' . $restantes);

$restantsSections = (int) $db->selectValue(
    'SELECT COUNT(*) FROM sections_page WHERE contenu LIKE :m',
    ['m' => '%' . $marqueur . '%'],
    0
);
verifier('les sections de test sont supprimées', $restantsSections === 0, 'restantes : ' . $restantsSections);

$total = $reussis + $echecs;
echo "\n" . str_repeat('=', 62) . "\n";
echo sprintf('Validité des offres — %d/%d vérifications réussies', $reussis, $total) . "\n";
echo str_repeat('=', 62) . "\n";

exit($echecs === 0 ? 0 : 1);
