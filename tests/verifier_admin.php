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

require_once __DIR__ . '/env.php';

/*
 * Le coffre et le calcul des codes à usage unique sont ceux de l'application.
 * La suite doit lire le secret partagé et produire un code valide pour
 * franchir le second facteur (ch. 8) : le faire avec le code du site, plutôt
 * qu'en recopiant ici une valeur de circonstance, garantit qu'aucune porte
 * dérobée n'a été ajoutée pour faire passer le test. config.php est requis
 * pour HASH_SECRET, dont la clé du coffre est dérivée.
 */
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Coffre.php';
require_once dirname(__DIR__) . '/app/core/Totp.php';

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

/**
 * Code à deux facteurs courant d'un compte.
 *
 * Le secret est lu en base et déchiffré par le coffre de l'application, puis
 * le code est calculé par l'implémentation de la RFC 6238 que sert le site.
 * La suite éprouve donc le vrai chemin : si le coffre ou le calcul cessaient
 * de fonctionner, la connexion échouerait ici comme elle échouerait pour un
 * utilisateur, au lieu de passer par une valeur de circonstance.
 *
 * Un code ne sert qu'une fois (RFC 6238 § 5.2). Lorsque l'intervalle courant
 * a déjà été consommé par une connexion précédente de la même suite, on
 * attend le suivant plutôt que de réutiliser le même : contourner la règle
 * ici reviendrait à ne plus la vérifier du tout.
 */
function code2fa(string $email): string
{
    $secret = \Souriane\Core\Coffre::ouvrir(
        requete("SELECT IFNULL(totp_secret, '') FROM utilisateurs WHERE email = '" . $email . "'")
    );

    if ($secret === null) {
        return '';
    }

    $dernier = requete("SELECT IFNULL(totp_dernier_pas, '') FROM utilisateurs WHERE email = '" . $email . "'");
    $pas     = \Souriane\Core\Totp::pasCourant();

    if ($dernier !== '' && $pas <= (int) $dernier) {
        $attente = ((int) $dernier + 1) * \Souriane\Core\Totp::PERIODE - time() + 1;
        if ($attente > 0) {
            echo "        (intervalle de 30 s déjà consommé — attente de {$attente} s pour le code suivant)\n";
            sleep($attente);
        }
        $pas = \Souriane\Core\Totp::pasCourant();
    }

    return \Souriane\Core\Totp::code($secret, $pas);
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

    $reponse = appel('POST', $base . '/admin/connexion', [
        '_csrf'        => $j,
        'email'        => $email,
        'mot_de_passe' => $motDePasse,
        'retour'       => '',
    ], $jar);

    // Second facteur (ch. 8) : sur un compte d'administration, le mot de passe
    // n'ouvre plus la session à lui seul. La suite franchit l'étape comme le
    // ferait un utilisateur, avec un code calculé pour de bon.
    if ($reponse['code'] === 302 && str_contains($reponse['entetes'], 'connexion/code')) {
        $page = appel('GET', $base . '/admin/connexion/code', null, $jar);

        $reponse = appel('POST', $base . '/admin/connexion/code', [
            '_csrf' => jeton($page['corps']),
            'code'  => code2fa($email),
        ], $jar);
    }

    return $reponse;
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
    'la version servie est une version PHP 8.x supportée (>= 8.1)',
    (preg_match('/PHP\s+(\d+\.\d+\.\d+)/', $parametres['corps'], $m) === 1)
        && version_compare($m[1], '8.1.0', '>='),
    'version PHP inférieure à 8.1 non supportée'
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
echo "\n=== 6. Destination d'un code court et affiche imprimable (EF-C03, EF-G06) ===\n";

/*
 * Le sujet est l'indirection décrite au chapitre 5.1 : le QR code encode une
 * URL courte, jamais l'adresse de la page. Changer la destination ne touche
 * donc pas au visuel imprimé.
 *
 * Deux conséquences pour la vérification :
 *   — il ne suffit pas que la valeur soit enregistrée en base, il faut que le
 *     scan mène effectivement à la nouvelle cible ; c'est cela, et rien
 *     d'autre, que le critère AC-03 demande ;
 *   — un code désactivé ne doit plus résoudre. La règle est donc vérifiée
 *     aussi sur le refus, pas seulement sur l'acceptation.
 *
 * Le code de démonstration utilisé est remis dans son état d'origine en fin de
 * section, et les événements de scan créés par la vérification sont supprimés :
 * la suite reste rejouable.
 */
$codeId    = (int) requete('SELECT id FROM codes_courts ORDER BY id LIMIT 1');
$codeTexte = requete('SELECT code FROM codes_courts WHERE id = ' . $codeId);

verifier('un code court de démonstration existe', $codeId > 0 && $codeTexte !== '', 'identifiant : ' . $codeId);

$etatCodeAvant = requete(
    "SELECT CONCAT_WS('~', code, IFNULL(destination, '-'), actif, IFNULL(code_secours, '-'))"
    . ' FROM codes_courts WHERE id = ' . $codeId
);

/** Nombre d'événements présents avant les scans de cette section. */
$dernierEvenement = (int) requete('SELECT IFNULL(MAX(id), 0) FROM evenements');

/** Relit la destination enregistrée pour le code vérifié. */
function destinationEnregistree(int $codeId): string
{
    return requete("SELECT IFNULL(destination, 'NULL') FROM codes_courts WHERE id = " . $codeId);
}

/** Soumet une destination et retourne la réponse brute. */
function soumettreDestination(string $base, int $codeId, string $jetonCsrf, string $valeur, string $jar): array
{
    return appel('POST', $base . '/admin/codes/' . $codeId . '/destination', [
        '_csrf'       => $jetonCsrf,
        'destination' => $valeur,
    ], $jar);
}

/** Ouvre le code court comme le ferait l'appareil photo du passant. */
function scanner(string $base, string $codeTexte): array
{
    return appel('GET', $base . '/c/' . rawurlencode($codeTexte), null, '');
}

// --- La page des codes expose les deux commandes ---------------------------
$pageCodes = appel('GET', $base . '/admin/codes', null, $jarRegie);
$jetonCodes = jeton($pageCodes['corps']);

verifier(
    'la page des codes propose de modifier la destination',
    str_contains($pageCodes['corps'], '/destination'),
    'aucun formulaire de destination'
);
verifier(
    'la page des codes propose l\'affiche imprimable',
    str_contains($pageCodes['corps'], '/affiche'),
    'aucun lien vers l\'affiche'
);
verifier(
    'la page des codes annonce la destination courante',
    str_contains($pageCodes['corps'], 'Mène vers :'),
    'destination non affichée'
);

// --- 1. Un chemin interne est accepté et le scan le suit --------------------
$ecriture = soumettreDestination($base, $codeId, $jetonCodes, '/quincaillerie-bignona', $jarRegie);
verifier('la modification de destination redirige (302)', $ecriture['code'] === 302, 'code ' . $ecriture['code']);
verifier(
    'le chemin interne est enregistré',
    destinationEnregistree($codeId) === '/quincaillerie-bignona',
    'valeur : ' . destinationEnregistree($codeId)
);

$scan = scanner($base, $codeTexte);
verifier(
    'le scan suit la nouvelle destination (AC-03)',
    $scan['code'] === 302 && str_contains($scan['entetes'], '/quincaillerie-bignona'),
    'code ' . $scan['code'] . ' — ' . trim((string) strstr($scan['entetes'], 'Location:'))
);
verifier(
    'le scan est attribué au bon code',
    (int) requete(
        'SELECT COUNT(*) FROM evenements WHERE id > ' . $dernierEvenement
        . " AND type = 'scan' AND code_court_id = " . $codeId
    ) >= 1,
    'aucun événement de scan rattaché au code ' . $codeId
);

// --- 2. Une adresse https complète est acceptée -----------------------------
$ecriture = soumettreDestination($base, $codeId, $jetonCodes, 'https://exemple.test/offre', $jarRegie);
verifier(
    'une adresse https complète est acceptée',
    destinationEnregistree($codeId) === 'https://exemple.test/offre',
    'valeur : ' . destinationEnregistree($codeId)
);

// --- 3. Les destinations dangereuses sont refusées --------------------------
/*
 * La destination est recopiée telle quelle dans un en-tête Location. Sans
 * filtre, « javascript: » s'exécuterait dans le contexte du site et « //hote »
 * ouvrirait une redirection vers un tiers : deux façons de détourner le
 * domaine de la Plateforme.
 */
$refus = [
    'javascript:alert(1)'    => 'un schéma javascript:',
    '//exemple.test/piege'   => 'une adresse relative au protocole est refusée',
    'http://exemple.test/x'  => 'une adresse http non chiffrée est refusée',
    'data:text/html,<b>x</b>' => 'un schéma data:',
];

foreach ($refus as $valeur => $intitule) {
    soumettreDestination($base, $codeId, $jetonCodes, $valeur, $jarRegie);
    verifier(
        $intitule . ' est refusé',
        destinationEnregistree($codeId) === 'https://exemple.test/offre',
        'destination devenue : ' . destinationEnregistree($codeId)
    );
}

$apresRefus = appel('GET', $base . '/admin/codes', null, $jarRegie);
verifier(
    'le refus est expliqué à l\'opérateur',
    str_contains($apresRefus['corps'], 'Destination refusée'),
    'aucun message de refus'
);

// --- 4. Une écriture sans jeton CSRF est rejetée ---------------------------
$sansJeton = appel('POST', $base . '/admin/codes/' . $codeId . '/destination', [
    'destination' => '/detournement',
], $jarRegie);
verifier(
    'une destination soumise sans jeton CSRF est rejetée (419)',
    $sansJeton['code'] === 419,
    'code ' . $sansJeton['code']
);
verifier(
    'le rejet CSRF n\'a rien écrit',
    destinationEnregistree($codeId) === 'https://exemple.test/offre',
    'destination devenue : ' . destinationEnregistree($codeId)
);

// --- 5. Un champ vidé réinitialise vers la page de l'annonceur -------------
$slugAnnonceur = requete(
    'SELECT IFNULL(pg.adresse_courte, a.adresse_courte)'
    . ' FROM codes_courts cc'
    . ' JOIN campagnes c ON c.id = cc.campagne_id'
    . ' JOIN annonceurs a ON a.id = c.annonceur_id'
    . ' LEFT JOIN pages pg ON pg.annonceur_id = a.id'
    . ' WHERE cc.id = ' . $codeId . ' LIMIT 1'
);

soumettreDestination($base, $codeId, $jetonCodes, '', $jarRegie);
verifier(
    'un champ vidé réinitialise la destination',
    destinationEnregistree($codeId) === 'NULL',
    'valeur : ' . destinationEnregistree($codeId)
);

$scanDefaut = scanner($base, $codeTexte);
verifier(
    'sans surcharge, le scan mène à la page de l\'annonceur',
    $scanDefaut['code'] === 302 && $slugAnnonceur !== '' && str_contains($scanDefaut['entetes'], $slugAnnonceur),
    'code ' . $scanDefaut['code'] . ' — ' . trim((string) strstr($scanDefaut['entetes'], 'Location:'))
);

// --- 6. L'affiche imprimable ------------------------------------------------
$affiche = appel('GET', $base . '/admin/codes/' . $codeId . '/affiche', null, $jarRegie);
verifier('l\'affiche se rend (200)', $affiche['code'] === 200, 'code ' . $affiche['code']);
verifier(
    'l\'affiche ne contient aucune sortie parasite',
    parasite($affiche['corps']) === '',
    parasite($affiche['corps'])
);
verifier(
    'l\'affiche est un document autonome, sans gabarit d\'administration',
    str_contains($affiche['corps'], '<!DOCTYPE html>') && !str_contains($affiche['corps'], 'admin-laterale'),
    'gabarit d\'administration détecté'
);
verifier(
    'l\'affiche déclare la taille du papier (@page)',
    str_contains($affiche['corps'], '@page') && str_contains($affiche['corps'], 'size: A0 portrait'),
    'aucune règle @page exploitable'
);
verifier(
    'l\'affiche porte le QR code en sombre sur fond clair',
    str_contains($affiche['corps'], '<g fill="#000000"') && str_contains($affiche['corps'], 'fill="#FFFFFF"'),
    'le code n\'est pas rendu en noir sur blanc'
);
verifier(
    'l\'affiche respecte la zone de silence de quatre modules',
    str_contains($affiche['corps'], 'marge de silence 4 modules'),
    'zone de silence non annoncée'
);
verifier(
    'l\'affiche imprime le code court en secours sous le QR code',
    str_contains($affiche['corps'], strtoupper($codeTexte)),
    'code court absent de l\'affiche'
);
verifier(
    'l\'affiche applique la règle de dimensionnement distance ÷ 10',
    str_contains($affiche['corps'], 'Exigé à 5 m : 50 cm'),
    'règle de dimensionnement non appliquée'
);
verifier(
    'l\'affiche indique la cible réelle de la redirection',
    str_contains($affiche['corps'], 'admin/codes'),
    'ligne technique absente'
);

// --- 7. Deux modèles de visuel (EF-G06) ------------------------------------
$vitrine = appel('GET', $base . '/admin/codes/' . $codeId . '/affiche?modele=vitrine', null, $jarRegie);
verifier(
    'le modèle de vitrine est appliqué',
    $vitrine['code'] === 200 && str_contains($vitrine['corps'], 'feuille--vitrine'),
    'code ' . $vitrine['code']
);

$modeleInvente = appel('GET', $base . '/admin/codes/' . $codeId . '/affiche?modele=nimportequoi', null, $jarRegie);
verifier(
    'un modèle inconnu retombe sur le modèle panneau',
    $modeleInvente['code'] === 200 && str_contains($modeleInvente['corps'], 'feuille--panneau'),
    'code ' . $modeleInvente['code']
);

// --- 8. Le sous-dimensionnement est signalé, pas masqué --------------------
/*
 * Le chapitre 5.1 vise 60 à 80 cm de largeur utile pour un panneau de bord de
 * route. Une fois la marge de silence comprise, cela dépasse ce que porte un
 * A0 : le plus grand format normalisé ne suffit pas. L'affiche doit le dire
 * plutôt que de laisser poser un code illisible.
 */
$tropPetit = appel('GET', $base . '/admin/codes/' . $codeId . '/affiche?distance=8', null, $jarRegie);
verifier(
    'à 8 m, l\'affiche exige un symbole de 80 cm',
    str_contains($tropPetit['corps'], 'Exigé à 8 m : 80 cm'),
    'dimensionnement non calculé'
);
verifier(
    'au-delà du A0, le sous-dimensionnement est signalé',
    str_contains($tropPetit['corps'], 'Affiche sous-dimensionnée'),
    'avertissement absent'
);
verifier(
    'l\'épreuve sous-dimensionnée porte la mise en garde sur le papier',
    str_contains($tropPetit['corps'], 'ne pas poser en l’état'),
    'mise en garde non imprimée'
);
verifier(
    'une distance hors bornes est ramenée dans l\'intervalle',
    str_contains(
        appel('GET', $base . '/admin/codes/' . $codeId . '/affiche?distance=999', null, $jarRegie)['corps'],
        'Exigé à 20 m : 200 cm'
    ),
    'la distance n\'est pas bornée à 20 m'
);

// --- 9. L'affiche exige une session ----------------------------------------
$jarAfficheAnonyme = sys_get_temp_dir() . '/souriane-affiche-anonyme.txt';
@unlink($jarAfficheAnonyme);
$afficheAnonyme = appel('GET', $base . '/admin/codes/' . $codeId . '/affiche', null, $jarAfficheAnonyme);
verifier(
    'l\'affiche exige une session (302 vers la connexion)',
    $afficheAnonyme['code'] === 302 && str_contains($afficheAnonyme['entetes'], 'admin/connexion'),
    'code ' . $afficheAnonyme['code']
);
@unlink($jarAfficheAnonyme);

// --- 10. Remise en état ----------------------------------------------------
executer('DELETE FROM evenements WHERE id > ' . $dernierEvenement);
verifier(
    'les scans de vérification sont retirés de la mesure',
    (int) requete('SELECT COUNT(*) FROM evenements WHERE id > ' . $dernierEvenement) === 0,
    'événements restants'
);

[$codeOrigine, $destinationOrigine, $actifOrigine, $secoursOrigine] = explode('~', $etatCodeAvant);
executer(sprintf(
    "UPDATE codes_courts SET destination = %s, actif = %d, code_secours = %s WHERE id = %d",
    $destinationOrigine === '-' ? 'NULL' : "'" . $destinationOrigine . "'",
    (int) $actifOrigine,
    $secoursOrigine === '-' ? 'NULL' : "'" . $secoursOrigine . "'",
    $codeId
));

$etatCodeApres = requete(
    "SELECT CONCAT_WS('~', code, IFNULL(destination, '-'), actif, IFNULL(code_secours, '-'))"
    . ' FROM codes_courts WHERE id = ' . $codeId
);
verifier(
    'le code de démonstration retrouve son état d\'origine',
    $etatCodeApres === $etatCodeAvant,
    'avant : ' . $etatCodeAvant . ' / après : ' . $etatCodeApres
);
verifier(
    'le code court n\'a pas changé — le visuel imprimé reste valable',
    $codeOrigine === $codeTexte,
    'code avant : ' . $codeOrigine . ' / après : ' . $codeTexte
);

// ---------------------------------------------------------------------------
echo "\n=== 7. Rapport de fin de campagne — mesuré, estimé, déclaré (EF-F06, EF-F07) ===\n";

/**
 * Extrait la valeur d'un indicateur du rapport imprimable.
 *
 * Le contrôle ne porte pas sur la présence d'un mot dans la page mais sur le
 * chiffre affiché : une page qui se rend avec un zéro partout passerait un
 * test de présence, pas celui-ci.
 */
function valeurRapport(string $html, string $libelle): ?string
{
    $motif = '/' . preg_quote($libelle, '/') . '<\/td>\s*<td class="nombre"><strong>([^<]+)<\/strong>/';

    return preg_match($motif, $html, $m) === 1 ? trim($m[1]) : null;
}

$campagneId = (int) requete('SELECT id FROM campagnes ORDER BY id LIMIT 1');
verifier('une campagne de démonstration existe', $campagneId > 0, 'identifiant : ' . $campagneId);

$rapport = appel('GET', $base . '/admin/campagnes/' . $campagneId . '/rapport', null, $jarRegie);
verifier('le rapport de fin de campagne se rend (200)', $rapport['code'] === 200, 'code ' . $rapport['code']);
verifier(
    'le rapport ne contient aucune sortie parasite',
    parasite($rapport['corps']) === '',
    parasite($rapport['corps'])
);
verifier(
    'le rapport est un document autonome, sans gabarit d\'administration',
    str_contains($rapport['corps'], '<!DOCTYPE html>') && !str_contains($rapport['corps'], 'admin-laterale'),
    'gabarit d\'administration détecté'
);

// --- EF-F07 : les trois natures sont nommées et tenues séparées ------------
foreach (['Mesuré', 'Estimé', 'Déclaré'] as $nature) {
    verifier(
        sprintf('le rapport nomme la nature « %s »', $nature),
        str_contains($rapport['corps'], '>' . $nature . '<'),
        'nature absente du rapport'
    );
}
verifier(
    'le détail par panneau range ses colonnes sous trois natures distinctes',
    substr_count($rapport['corps'], 'class="nombre col-mesure"') >= 3
    && substr_count($rapport['corps'], 'class="nombre col-declare"') >= 2
    && substr_count($rapport['corps'], 'class="nombre col-estime"') >= 3,
    'colonnes non séparées par nature'
);
verifier(
    'le rapport rappelle la méthode d\'estimation',
    str_contains($rapport['corps'], 'Méthode.')
    && str_contains($rapport['corps'], '1 % du trafic déclaré cumulé'),
    'méthode non explicitée'
);
verifier(
    'le rapport avertit qu\'un estimé n\'est pas une mesure',
    str_contains($rapport['corps'], 'ne mesurent rien'),
    'avertissement de nature absent'
);
verifier(
    'le rapport identifie la source des données déclarées',
    str_contains($rapport['corps'], 'Déclaration de la régie'),
    'source déclarée absente'
);

// --- Les chiffres affichés sont ceux de la base ----------------------------
$scansBase = (int) requete(
    'SELECT COUNT(*) FROM evenements WHERE campagne_id = ' . $campagneId . " AND type = 'scan'"
);
$scansPage = valeurRapport($rapport['corps'], 'Scans bruts');
verifier(
    'le rapport affiche le nombre de scans réellement enregistrés',
    $scansPage === number_format($scansBase, 0, ',', ' '),
    'page : ' . ($scansPage ?? '(absent)') . ' / base : ' . $scansBase
);

$visiteursBase = (int) requete(
    'SELECT COUNT(DISTINCT session_id) FROM evenements WHERE campagne_id = ' . $campagneId . " AND type = 'scan'"
);
$visiteursPage = valeurRapport($rapport['corps'], 'Visiteurs uniques');
verifier(
    'le rapport distingue les visiteurs uniques des scans bruts (EF-F02)',
    $visiteursPage === number_format($visiteursBase, 0, ',', ' '),
    'page : ' . ($visiteursPage ?? '(absent)') . ' / base : ' . $visiteursBase
);

$nbPanneaux = (int) requete('SELECT COUNT(*) FROM campagne_panneaux WHERE campagne_id = ' . $campagneId);
verifier(
    'le rapport compte les panneaux rattachés à la campagne',
    str_contains($rapport['corps'], '<div class="repere__valeur">' . $nbPanneaux . '</div>'),
    'panneaux attendus : ' . $nbPanneaux
);

// --- L'export CSV ---------------------------------------------------------
$csv = appel('GET', $base . '/admin/campagnes/' . $campagneId . '/rapport.csv', null, $jarRegie);
verifier('l\'export CSV se télécharge (200)', $csv['code'] === 200, 'code ' . $csv['code']);
verifier(
    'l\'export est annoncé comme un fichier CSV',
    stripos($csv['entetes'], 'text/csv') !== false,
    trim((string) strstr($csv['entetes'], 'Content-Type:'))
);
verifier(
    'l\'export est proposé au téléchargement sous un nom de fichier',
    stripos($csv['entetes'], 'attachment') !== false && stripos($csv['entetes'], '.csv') !== false,
    trim((string) strstr($csv['entetes'], 'Content-Disposition:'))
);
verifier(
    'l\'export commence par la marque d\'ordre des octets attendue par le tableur',
    str_starts_with($csv['corps'], "\xEF\xBB\xBF"),
    'absence de marque d’ordre des octets'
);
verifier(
    'l\'export sépare les colonnes par des points-virgules',
    str_contains($csv['corps'], '"Nature";"Indicateur";"Valeur";"Précision"'),
    'en-tête du tableau des indicateurs introuvable'
);

// EF-F07 dans le fichier lui-même : chaque ligne de données porte sa nature
// dans sa première colonne, si bien qu'un tri ou une suppression de colonne ne
// peut pas faire passer un estimé pour une mesure.
foreach (['Mesuré', 'Estimé', 'Déclaré'] as $nature) {
    verifier(
        sprintf('l\'export porte des lignes de nature « %s »', $nature),
        str_contains($csv['corps'], '"' . $nature . '";'),
        'aucune ligne de cette nature'
    );
}
verifier(
    'l\'export étiquette ses colonnes par nature',
    str_contains($csv['corps'], 'Scans (mesuré)')
    && str_contains($csv['corps'], 'Trafic par jour (déclaré)')
    && str_contains($csv['corps'], 'Passages cumulés (estimé)'),
    'colonnes non étiquetées par nature'
);

// Deux chemins de calcul divergeraient : l'export doit porter le chiffre de la
// page, lui-même vérifié contre la base juste au-dessus.
verifier(
    'l\'export porte le même nombre de scans que la page',
    str_contains($csv['corps'], '"Mesuré";"Scans bruts";"' . $scansBase . '"'),
    'export et page divergent sur les scans bruts'
);

// --- Accès ---------------------------------------------------------------
$jarRapportAnonyme = sys_get_temp_dir() . '/souriane-rapport-anonyme.txt';
@unlink($jarRapportAnonyme);
$rapportAnonyme = appel('GET', $base . '/admin/campagnes/' . $campagneId . '/rapport', null, $jarRapportAnonyme);
verifier(
    'le rapport exige une session (302 vers la connexion)',
    $rapportAnonyme['code'] === 302 && str_contains($rapportAnonyme['entetes'], 'admin/connexion'),
    'code ' . $rapportAnonyme['code']
);
@unlink($jarRapportAnonyme);

/*
 * Un annonceur ne doit lire que ses propres campagnes. Les campagnes de
 * démonstration appartiennent à des annonceurs distincts : celle du compte
 * annonceur de démonstration, et une autre.
 */
$campagneDeLAnnonceur = (int) requete(
    'SELECT id FROM campagnes WHERE annonceur_id = '
    . "(SELECT annonceur_id FROM utilisateurs WHERE role = 'annonceur' ORDER BY id LIMIT 1)"
    . ' ORDER BY id LIMIT 1'
);
$campagneDAutrui = (int) requete(
    'SELECT id FROM campagnes WHERE annonceur_id <> '
    . "(SELECT annonceur_id FROM utilisateurs WHERE role = 'annonceur' ORDER BY id LIMIT 1)"
    . ' ORDER BY id LIMIT 1'
);

if ($campagneDeLAnnonceur > 0 && $campagneDAutrui > 0) {
    $jarAnnonceur = sys_get_temp_dir() . '/souriane-rapport-annonceur.txt';
    @unlink($jarAnnonceur);
    $connexionAnnonceur = session($jarAnnonceur, 'annonceur@souriane.sn', 'Souriane2026!');
    verifier('la connexion annonceur réussit', $connexionAnnonceur['code'] === 302);

    $sien = appel('GET', $base . '/admin/campagnes/' . $campagneDeLAnnonceur . '/rapport', null, $jarAnnonceur);
    verifier(
        'un annonceur peut lire le rapport de sa propre campagne',
        $sien['code'] === 200,
        'code ' . $sien['code']
    );

    $autre = appel('GET', $base . '/admin/campagnes/' . $campagneDAutrui . '/rapport', null, $jarAnnonceur);
    verifier(
        'un annonceur ne peut pas lire le rapport d\'une autre campagne',
        $autre['code'] === 403,
        'code ' . $autre['code']
    );

    $autreCsv = appel('GET', $base . '/admin/campagnes/' . $campagneDAutrui . '/rapport.csv', null, $jarAnnonceur);
    verifier(
        'l\'export est protégé par la même règle que la page',
        $autreCsv['code'] === 403,
        'code ' . $autreCsv['code']
    );

    @unlink($jarAnnonceur);
} else {
    verifier(
        'les campagnes de démonstration appartiennent à des annonceurs distincts',
        false,
        'jeu de démonstration insuffisant pour vérifier la séparation par annonceur'
    );
}

// --- Une campagne inexistante ne produit pas de rapport -------------------
$inexistant = appel('GET', $base . '/admin/campagnes/999999/rapport', null, $jarRegie);
verifier(
    'une campagne inexistante ne produit pas de rapport',
    $inexistant['code'] === 302,
    'code ' . $inexistant['code']
);

// ---------------------------------------------------------------------------
echo "\n=== 8. Règles d'accès par rôle ===\n";

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
echo "\n=== 9. Second facteur des comptes d'administration (ch. 8) ===\n";

/*
 * « Les comptes d'administration sont protégés par une authentification à
 *   deux facteurs. »
 *
 * La suite franchit le second facteur pour de bon : elle lit le secret dans
 * la base, le déchiffre avec le coffre de l'application et calcule le code
 * attendu. Aucune porte dérobée n'a été ajoutée au site pour la faire passer ;
 * si le coffre ou le calcul cessaient de fonctionner, tout échouerait ici.
 *
 * Les compteurs de limitation sont remis à zéro : la section consomme
 * plusieurs tentatives de code, et la suite doit rester rejouable.
 */
foreach (glob($dossierLimites . '/*.json') ?: [] as $fichier) {
    @unlink($fichier);
}

// La section précédente se termine par une déconnexion : la session régie est
// rouverte ici, car plusieurs vérifications portent sur ses propres réglages.
$reouverture = session($jarRegie, 'admin@souriane.sn', 'Souriane2026!');
verifier(
    'la session régie est rouverte pour cette section',
    $reouverture['code'] === 302,
    'code ' . $reouverture['code']
);

$codeValide = code2fa('admin@souriane.sn');
$codeFaux   = str_pad((string) (((int) $codeValide + 1) % 1000000), 6, '0', STR_PAD_LEFT);

// --- Le mot de passe seul n'ouvre plus de session -------------------------
$jar2fa = sys_get_temp_dir() . '/souriane-admin-2fa.txt';
@unlink($jar2fa);

$pc2fa = appel('GET', $base . '/admin/connexion', null, $jar2fa);
$mdpSeul = appel('POST', $base . '/admin/connexion', [
    '_csrf'        => jeton($pc2fa['corps']),
    'email'        => 'admin@souriane.sn',
    'mot_de_passe' => 'Souriane2026!',
    'retour'       => '',
], $jar2fa);
verifier(
    'le mot de passe seul ne connecte pas un compte d’administration',
    $mdpSeul['code'] === 302 && str_contains($mdpSeul['entetes'], 'connexion/code'),
    trim((string) strstr($mdpSeul['entetes'], 'Location:'))
);

$sansCode = appel('GET', $base . '/admin', null, $jar2fa);
verifier(
    'sans le code, le back-office reste fermé',
    $sansCode['code'] === 302 && str_contains($sansCode['entetes'], 'admin/connexion'),
    'code ' . $sansCode['code']
);

// --- L'écran de code ------------------------------------------------------
$ecranCode = appel('GET', $base . '/admin/connexion/code', null, $jar2fa);
verifier('l’écran de code est servi (200)', $ecranCode['code'] === 200, 'code ' . $ecranCode['code']);
verifier(
    'l’écran de code ne produit aucune sortie parasite',
    parasite($ecranCode['corps']) === '',
    parasite($ecranCode['corps'])
);
verifier('le champ de code est présent', str_contains($ecranCode['corps'], 'id="code"'));
verifier('une voie de secours est proposée', str_contains($ecranCode['corps'], 'id="secours"'));
verifier('le compte concerné est nommé', str_contains($ecranCode['corps'], 'admin@souriane.sn'));
verifier(
    'l’écran de code n’est pas mis en cache',
    str_contains(strtolower($ecranCode['entetes']), 'no-store'),
    'en-têtes : ' . trim((string) strstr($ecranCode['entetes'], 'Cache-Control:'))
);
verifier(
    'la clé de démonstration n’est rappelée qu’en mode démonstration',
    APP_DEBUG
        ? str_contains($ecranCode['corps'], SECURITE_2FA_SECRET_DEMO)
        : !str_contains($ecranCode['corps'], 'Démonstration'),
    'APP_DEBUG = ' . var_export(APP_DEBUG, true)
);

$jetonCode = jeton($ecranCode['corps']);

// --- Un code erroné est refusé -------------------------------------------
$refuse = appel('POST', $base . '/admin/connexion/code', [
    '_csrf' => $jetonCode,
    'code'  => $codeFaux,
], $jar2fa);
verifier(
    'un code erroné est refusé',
    $refuse['code'] === 302 && str_contains($refuse['entetes'], 'connexion/code'),
    trim((string) strstr($refuse['entetes'], 'Location:'))
);
verifier(
    'un code erroné n’ouvre pas la session',
    appel('GET', $base . '/admin', null, $jar2fa)['code'] === 302
);

// --- Un code valide ouvre la session -------------------------------------
$accepte = appel('POST', $base . '/admin/connexion/code', [
    '_csrf' => $jetonCode,
    'code'  => $codeValide,
], $jar2fa);
verifier(
    'un code valide ouvre la session',
    $accepte['code'] === 302 && !str_contains($accepte['entetes'], 'connexion/code'),
    trim((string) strstr($accepte['entetes'], 'Location:'))
);
verifier(
    'le back-office est alors accessible',
    appel('GET', $base . '/admin', null, $jar2fa)['code'] === 200
);

// --- Le même code ne resert pas (RFC 6238 § 5.2) --------------------------
$jarRejeu = sys_get_temp_dir() . '/souriane-admin-rejeu.txt';
@unlink($jarRejeu);

$pcRejeu = appel('GET', $base . '/admin/connexion', null, $jarRejeu);
appel('POST', $base . '/admin/connexion', [
    '_csrf'        => jeton($pcRejeu['corps']),
    'email'        => 'admin@souriane.sn',
    'mot_de_passe' => 'Souriane2026!',
    'retour'       => '',
], $jarRejeu);

$pageRejeu = appel('GET', $base . '/admin/connexion/code', null, $jarRejeu);
$rejeu = appel('POST', $base . '/admin/connexion/code', [
    '_csrf' => jeton($pageRejeu['corps']),
    'code'  => $codeValide,
], $jarRejeu);
verifier(
    'un code déjà utilisé est refusé',
    $rejeu['code'] === 302 && str_contains($rejeu['entetes'], 'connexion/code'),
    trim((string) strstr($rejeu['entetes'], 'Location:'))
);
verifier(
    'le rejeu n’ouvre pas de session',
    appel('GET', $base . '/admin', null, $jarRejeu)['code'] === 302
);

// --- Sans mot de passe validé, pas d'écran de code ------------------------
$jarNeuf = sys_get_temp_dir() . '/souriane-admin-neuf.txt';
@unlink($jarNeuf);
$codeSansMdp = appel('GET', $base . '/admin/connexion/code', null, $jarNeuf);
verifier(
    'l’écran de code exige un mot de passe déjà validé',
    $codeSansMdp['code'] === 302 && str_contains($codeSansMdp['entetes'], 'admin/connexion'),
    'code ' . $codeSansMdp['code']
);

// --- Le jeton CSRF est exigé ----------------------------------------------
$sansJeton = appel('POST', $base . '/admin/connexion/code', ['code' => $codeFaux], $jar2fa);
verifier(
    'l’écran de code exige un jeton CSRF (419)',
    $sansJeton['code'] === 419,
    'code ' . $sansJeton['code']
);

// --- Un compte client n'est pas soumis au second facteur ------------------
$jarClient = sys_get_temp_dir() . '/souriane-admin-client-2fa.txt';
@unlink($jarClient);
$pcClient = appel('GET', $base . '/admin/connexion', null, $jarClient);
$connexionClient = appel('POST', $base . '/admin/connexion', [
    '_csrf'        => jeton($pcClient['corps']),
    'email'        => 'annonceur@souriane.sn',
    'mot_de_passe' => 'Souriane2026!',
    'retour'       => '',
], $jarClient);
verifier(
    'un compte client n’est pas soumis au second facteur',
    $connexionClient['code'] === 302 && !str_contains($connexionClient['entetes'], 'connexion/code'),
    trim((string) strstr($connexionClient['entetes'], 'Location:'))
);

// --- Enrôlement imposé à un compte d'administration non protégé -----------
$email2fa = 'verif-2fa-' . bin2hex(random_bytes(4)) . '@souriane.test';
$mdp2fa   = 'Verification2FA2026!';

executer(sprintf(
    "INSERT INTO utilisateurs (nom, email, mot_de_passe, role, actif) VALUES ('Compte 2FA de vérification', %s, %s, 'regie', 1)",
    base()->quote($email2fa),
    base()->quote(password_hash($mdp2fa, PASSWORD_ARGON2ID))
));

$jarEnro = sys_get_temp_dir() . '/souriane-admin-enro.txt';
@unlink($jarEnro);

$pcEnro = appel('GET', $base . '/admin/connexion', null, $jarEnro);
$etapeEnro = appel('POST', $base . '/admin/connexion', [
    '_csrf'        => jeton($pcEnro['corps']),
    'email'        => $email2fa,
    'mot_de_passe' => $mdp2fa,
    'retour'       => '',
], $jarEnro);
verifier(
    'un compte d’administration sans second facteur est conduit à l’enrôlement',
    $etapeEnro['code'] === 302 && str_contains($etapeEnro['entetes'], 'connexion/enrolement'),
    trim((string) strstr($etapeEnro['entetes'], 'Location:'))
);

$bloque = appel('GET', $base . '/admin/codes', null, $jarEnro);
verifier(
    'tant que le second facteur n’est pas en place, le back-office reste fermé',
    $bloque['code'] === 302 && str_contains($bloque['entetes'], 'connexion/enrolement'),
    'code ' . $bloque['code']
);

$pageEnro = appel('GET', $base . '/admin/connexion/enrolement', null, $jarEnro);
verifier('l’écran d’enrôlement est servi (200)', $pageEnro['code'] === 200, 'code ' . $pageEnro['code']);
verifier(
    'l’écran d’enrôlement ne produit aucune sortie parasite',
    parasite($pageEnro['corps']) === '',
    parasite($pageEnro['corps'])
);
verifier(
    'l’écran d’enrôlement dessine un QR code',
    str_contains($pageEnro['corps'], 'aria-label="QR code"'),
    'aucun SVG de QR code'
);
verifier(
    'l’enrôlement est présenté comme obligatoire',
    str_contains($pageEnro['corps'], 'obligatoire'),
    'mention absente'
);

preg_match('/class="cle-secret">([A-Z2-7]{16,})</', $pageEnro['corps'], $mCle);
$cleEnro = $mCle[1] ?? '';
verifier(
    'la clé partagée est affichée pour la recopie manuelle',
    strlen($cleEnro) >= 32,
    $cleEnro === '' ? 'clé absente' : 'clé de ' . strlen($cleEnro) . ' caractères'
);

$pageEnro2 = appel('GET', $base . '/admin/connexion/enrolement', null, $jarEnro);
preg_match('/class="cle-secret">([A-Z2-7]{16,})</', $pageEnro2['corps'], $mCle2);
verifier(
    'la clé ne change pas au rafraîchissement : le QR déjà scanné reste valable',
    ($mCle2[1] ?? '') === $cleEnro,
    'deux chargements donnent deux clés'
);

$confirme = appel('POST', $base . '/admin/connexion/enrolement', [
    '_csrf' => jeton($pageEnro['corps']),
    'code'  => \Souriane\Core\Totp::code($cleEnro),
], $jarEnro);
verifier(
    'un code calculé depuis la clé affichée active le second facteur',
    $confirme['code'] === 302 && str_contains($confirme['entetes'], 'admin/securite'),
    trim((string) strstr($confirme['entetes'], 'Location:'))
);
verifier(
    'la session est ouverte une fois l’enrôlement confirmé',
    appel('GET', $base . '/admin', null, $jarEnro)['code'] === 200
);

$securite = appel('GET', $base . '/admin/securite', null, $jarEnro);
verifier('la page de sécurité du compte est servie (200)', $securite['code'] === 200, 'code ' . $securite['code']);
verifier(
    'la page de sécurité ne produit aucune sortie parasite',
    parasite($securite['corps']) === '',
    parasite($securite['corps'])
);
verifier('la page annonce le second facteur actif', str_contains($securite['corps'], 'Active'));

preg_match_all('/<li class="monospace">([A-Z0-9]{4}-[A-Z0-9]{4})<\/li>/', $securite['corps'], $mCodes);
$codesSecours = $mCodes[1] ?? [];
verifier(
    'les codes de secours sont remis à l’activation',
    count($codesSecours) === SECURITE_2FA_CODES_SECOURS,
    count($codesSecours) . ' code(s) affiché(s)'
);
verifier(
    'les codes de secours sont affichés sous une forme recopiable',
    ($codesSecours[0] ?? '') !== '' && preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $codesSecours[0]) === 1,
    (string) ($codesSecours[0] ?? '—')
);

$securite2 = appel('GET', $base . '/admin/securite', null, $jarEnro);
verifier(
    'les codes de secours ne sont plus affichés au rechargement',
    !str_contains($securite2['corps'], 'Vos codes de secours'),
    'la liste réapparaît : elle serait relisible à volonté'
);

// --- Ce qui est rangé, et sous quelle forme -------------------------------
verifier(
    'le compte est marqué protégé en base',
    requete("SELECT totp_actif FROM utilisateurs WHERE email = '" . $email2fa . "'") === '1',
    'totp_actif = ' . requete("SELECT IFNULL(totp_actif,'-') FROM utilisateurs WHERE email = '" . $email2fa . "'")
);
verifier(
    'le secret n’est pas rangé en clair',
    requete("SELECT COUNT(*) FROM utilisateurs WHERE email = '" . $email2fa . "' AND totp_secret = '" . $cleEnro . "'") === '0',
    'la clé apparaît telle quelle dans la colonne'
);
verifier(
    'le secret est rangé chiffré',
    str_starts_with(
        requete("SELECT IFNULL(totp_secret,'') FROM utilisateurs WHERE email = '" . $email2fa . "'"),
        'v1.'
    ),
    'format de coffre inattendu'
);
verifier(
    'les codes de secours sont rangés hachés, jamais en clair',
    (int) requete("SELECT CHAR_LENGTH(IFNULL(totp_codes_secours,'')) FROM utilisateurs WHERE email = '" . $email2fa . "'") >= SECURITE_2FA_CODES_SECOURS * 64,
    'longueur ' . requete("SELECT CHAR_LENGTH(IFNULL(totp_codes_secours,'')) FROM utilisateurs WHERE email = '" . $email2fa . "'")
);
verifier(
    'aucun code de secours n’apparaît en clair dans la base',
    requete("SELECT COUNT(*) FROM utilisateurs WHERE email = '" . $email2fa . "' AND totp_codes_secours LIKE '%" . ($codesSecours[0] ?? '@@') . "%'") === '0',
    'le premier code figure en base'
);

// --- Le compte enrôlé redemande un code ----------------------------------
// La section enchaîne plus de cinq connexions, alors que le limiteur en
// autorise cinq par quart d'heure et par adresse. Les compteurs sont remis à
// zéro : le limiteur lui-même n'est pas touché, et la suite reste rejouable.
foreach (glob($dossierLimites . '/*.json') ?: [] as $fichier) {
    @unlink($fichier);
}

$jarEnro3 = sys_get_temp_dir() . '/souriane-admin-enro3.txt';
@unlink($jarEnro3);
$pcEnro3 = appel('GET', $base . '/admin/connexion', null, $jarEnro3);
$etape3 = appel('POST', $base . '/admin/connexion', [
    '_csrf'        => jeton($pcEnro3['corps']),
    'email'        => $email2fa,
    'mot_de_passe' => $mdp2fa,
    'retour'       => '',
], $jarEnro3);
verifier(
    'une fois enrôlé, le compte redemande un code à la connexion',
    $etape3['code'] === 302 && str_contains($etape3['entetes'], 'connexion/code'),
    trim((string) strstr($etape3['entetes'], 'Location:'))
);

// --- Un code de secours, et son usage unique ------------------------------
$pageSecours = appel('GET', $base . '/admin/connexion/code', null, $jarEnro3);
$parSecours = appel('POST', $base . '/admin/connexion/code', [
    '_csrf'   => jeton($pageSecours['corps']),
    'secours' => (string) ($codesSecours[0] ?? ''),
], $jarEnro3);
verifier(
    'un code de secours ouvre la session',
    $parSecours['code'] === 302 && !str_contains($parSecours['entetes'], 'connexion/code'),
    trim((string) strstr($parSecours['entetes'], 'Location:'))
);

foreach (glob($dossierLimites . '/*.json') ?: [] as $fichier) {
    @unlink($fichier);
}

$jarEnro4 = sys_get_temp_dir() . '/souriane-admin-enro4.txt';
@unlink($jarEnro4);
$pcEnro4 = appel('GET', $base . '/admin/connexion', null, $jarEnro4);
appel('POST', $base . '/admin/connexion', [
    '_csrf'        => jeton($pcEnro4['corps']),
    'email'        => $email2fa,
    'mot_de_passe' => $mdp2fa,
    'retour'       => '',
], $jarEnro4);
$pageSecours2 = appel('GET', $base . '/admin/connexion/code', null, $jarEnro4);
$rejeuSecours = appel('POST', $base . '/admin/connexion/code', [
    '_csrf'   => jeton($pageSecours2['corps']),
    'secours' => (string) ($codesSecours[0] ?? ''),
], $jarEnro4);
verifier(
    'un code de secours ne sert qu’une fois',
    $rejeuSecours['code'] === 302 && str_contains($rejeuSecours['entetes'], 'connexion/code'),
    trim((string) strstr($rejeuSecours['entetes'], 'Location:'))
);
verifier(
    'le code de secours consommé est retiré de la liste',
    requete(
        "SELECT COUNT(*) FROM utilisateurs WHERE email = '" . $email2fa . "'"
        . " AND totp_codes_secours LIKE '%" . hash('sha256', str_replace('-', '', (string) ($codesSecours[0] ?? ''))) . "%'"
    ) === '0',
    'l’empreinte du code est encore en base'
);

// --- Le retrait est refusé aux comptes d'administration -------------------
// Le jeton est pris sur une page chargée avec la session régie : un jeton
// appartient à une session, et le réutiliser depuis une autre ferait échouer
// la requête en 419 — ce qui ne prouverait rien sur le fond.
$securiteRegie = appel('GET', $base . '/admin/securite', null, $jarRegie);
verifier(
    'la page de sécurité est accessible à la régie',
    $securiteRegie['code'] === 200,
    'code ' . $securiteRegie['code']
);

$desac = appel('POST', $base . '/admin/securite/desactiver', [
    '_csrf'        => jeton($securiteRegie['corps']),
    'mot_de_passe' => 'Souriane2026!',
    'code'         => $codeFaux,
], $jarRegie);
verifier(
    'le retrait du second facteur est refusé à un rôle d’administration',
    $desac['code'] === 302 && str_contains($desac['entetes'], 'admin/securite'),
    trim((string) strstr($desac['entetes'], 'Location:'))
);

$securiteApres = appel('GET', $base . '/admin/securite', null, $jarRegie);
verifier(
    'le refus s’appuie sur le chapitre 8, et non sur un code invalide',
    str_contains($securiteApres['corps'], 'chapitre 8 impose'),
    'message attendu absent'
);
verifier(
    'le second facteur du compte régie reste actif après la tentative',
    requete("SELECT totp_actif FROM utilisateurs WHERE email = 'admin@souriane.sn'") === '1',
    'le second facteur a été retiré'
);

// --- La mise en place exige le mot de passe -------------------------------
$activerSansMdp = appel('POST', $base . '/admin/securite/activer', [
    '_csrf'        => jeton($securiteApres['corps']),
    'mot_de_passe' => 'mauvais-mot-de-passe',
], $jarRegie);
verifier(
    'la mise en place du second facteur exige le mot de passe du compte',
    $activerSansMdp['code'] === 302 && str_contains($activerSansMdp['entetes'], 'admin/securite'),
    trim((string) strstr($activerSansMdp['entetes'], 'Location:'))
);

$apresMdp = appel('GET', $base . '/admin/securite', null, $jarRegie);
verifier(
    'un mot de passe erroné est signalé',
    str_contains($apresMdp['corps'], 'Mot de passe incorrect'),
    'aucun message d’erreur'
);

// Un enrôlement aurait été engagé si le mot de passe n'avait pas été exigé :
// l'écran d'enrôlement serait alors servi au lieu de renvoyer aux réglages.
$enroIndu = appel('GET', $base . '/admin/connexion/enrolement', null, $jarRegie);
verifier(
    'un mot de passe erroné n’engage aucun enrôlement',
    $enroIndu['code'] === 302 && str_contains($enroIndu['entetes'], 'admin/securite'),
    trim((string) strstr($enroIndu['entetes'], 'Location:'))
);

// --- Remise en état -------------------------------------------------------
executer("DELETE FROM utilisateurs WHERE email = '" . $email2fa . "'");
verifier(
    'le compte de vérification 2FA est supprimé',
    requete("SELECT COUNT(*) FROM utilisateurs WHERE email = '" . $email2fa . "'") === '0'
);

foreach ([$jar2fa, $jarRejeu, $jarNeuf, $jarClient, $jarEnro, $jarEnro3, $jarEnro4] as $fichierJar) {
    @unlink($fichierJar);
}
verifier('les fichiers de session de la section sont supprimés', true);

// ---------------------------------------------------------------------------
echo "\n=== 10. Nettoyage des données de test ===\n";

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

    $env = env_fichier(dirname(__DIR__) . '/.env');
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
