<?php
/**
 * Vérification du calcul des codes à usage unique (RFC 6238).
 * Exécution : php tests/verifier_totp.php
 *
 * Un second facteur mal calculé est pire qu’absent : il refuse les codes
 * légitimes, ou accepte ceux qu’il ne devrait pas. Ce que l’on peut vérifier
 * sans téléphone, ce sont les vecteurs de test publiés par la RFC elle-même —
 * des couples (horodatage, code attendu) que toute mise en œuvre correcte doit
 * reproduire à l’identique. Les voici, repris de la RFC 6238 annexe B.
 *
 * Le secret des vecteurs est la chaîne ASCII « 12345678901234567890 », dont
 * l’encodage base32 est donné ci-dessous. La RFC publie des codes à huit
 * chiffres ; la Plateforme en produit six, comme les applications
 * d’authentification courantes. Un code à six chiffres est le reste modulo un
 * million du code à huit chiffres : les vecteurs sont donc ramenés à six
 * chiffres avant comparaison, ce qui n’affaiblit pas le contrôle — la
 * troncature dynamique, qui est la partie délicate, est exercée à l’identique.
 *
 * Aucune base de données, aucun serveur : cette suite tourne seule.
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';

require_once dirname(__DIR__) . '/app/core/Totp.php';

use Souriane\Core\Totp;

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
echo "\n=== 1. Vecteurs de la RFC 6238 (annexe B) ===\n";

/** Secret des vecteurs : « 12345678901234567890 » en base32. */
$secretRfc = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

$vecteurs = [
    59          => 94287082,
    1111111109  => 7081804,
    1111111111  => 14050471,
    1234567890  => 89005924,
    2000000000  => 69279037,
    20000000000 => 65353130,
];

foreach ($vecteurs as $horodatage => $attendu8) {
    $attendu6 = str_pad((string) ($attendu8 % 1000000), 6, '0', STR_PAD_LEFT);
    $obtenu   = Totp::code($secretRfc, Totp::pasCourant((int) $horodatage));

    verifier(
        sprintf('T=%d → %s', $horodatage, $attendu6),
        $obtenu === $attendu6,
        'obtenu ' . $obtenu . ', attendu ' . $attendu6
    );
}

// ---------------------------------------------------------------------------
echo "\n=== 2. Décodage du secret ===\n";

/*
 * Le décodage base32 est la partie où une erreur passe inaperçue : un secret
 * mal décodé produit des codes cohérents avec eux-mêmes, donc acceptés par
 * cette implémentation, mais refusés par toutes les applications
 * d’authentification. Les vecteurs ci-dessus le prouvent déjà, puisque le
 * secret des vecteurs est encodé exactement comme le fait une application.
 *
 * On vérifie ici que les tolérances d’écriture courantes n’altèrent pas le
 * résultat : espaces, tirets, minuscules et remplissage « = » sont fréquents
 * lorsqu’un secret est recopié à la main.
 */
$variantes = [
    'minuscules'  => strtolower($secretRfc),
    'espaces'     => 'GEZD GNBV GY3T QOJQ GEZD GNBV GY3T QOJQ',
    'tirets'      => 'GEZD-GNBV-GY3T-QOJQ-GEZD-GNBV-GY3T-QOJQ',
    'remplissage' => $secretRfc . '========',
];

foreach ($variantes as $intitule => $variante) {
    verifier(
        'le secret reste lisible malgré ' . $intitule,
        Totp::code($variante, 1) === Totp::code($secretRfc, 1),
        'les deux écritures donnent des codes différents'
    );
}

// ---------------------------------------------------------------------------
echo "\n=== 3. Génération du secret ===\n";

$secret = Totp::secret();
verifier(
    'le secret généré fait 32 caractères base32 (160 bits)',
    strlen($secret) === 32,
    'longueur : ' . strlen($secret)
);
verifier(
    'le secret généré n’utilise que l’alphabet base32',
    preg_match('/^[A-Z2-7]{32}$/', $secret) === 1,
    'secret : ' . $secret
);

$autres = [];
for ($i = 0; $i < 40; $i++) {
    $autres[Totp::secret()] = true;
}
verifier(
    'quarante secrets générés sont tous distincts',
    count($autres) === 40,
    'seulement ' . count($autres) . ' valeurs distinctes'
);

verifier(
    'un secret trop court est refusé',
    (static function (): bool {
        try {
            Totp::secret(4);

            return false;
        } catch (\InvalidArgumentException) {
            return true;
        }
    })(),
    'aucune exception levée pour 4 octets'
);

// ---------------------------------------------------------------------------
echo "\n=== 4. Vérification d’un code ===\n";

$pasCourant = Totp::pasCourant();
$codeCourant = Totp::code($secret, $pasCourant);

verifier(
    'le code courant est accepté',
    Totp::verifier($secret, $codeCourant) === $pasCourant,
    'code refusé'
);
verifier(
    'le code reste accepté avec un espace de mise en forme',
    Totp::verifier($secret, substr($codeCourant, 0, 3) . ' ' . substr($codeCourant, 3)) === $pasCourant,
    'les séparateurs ne sont pas retirés'
);
verifier(
    'le pas précédent est accepté (horloge en retard)',
    Totp::verifier($secret, Totp::code($secret, $pasCourant - 1)) === $pasCourant - 1,
    'tolérance en arrière absente'
);
verifier(
    'le pas suivant est accepté (horloge en avance)',
    Totp::verifier($secret, Totp::code($secret, $pasCourant + 1)) === $pasCourant + 1,
    'tolérance en avant absente'
);
verifier(
    'deux pas d’écart sont refusés',
    Totp::verifier($secret, Totp::code($secret, $pasCourant + 2)) === null,
    'la fenêtre de tolérance est trop large'
);

/*
 * Usage unique : un code déjà consommé ne doit plus être accepté, même s’il
 * reste dans sa fenêtre de validité. Sans cette règle, un code observé
 * par-dessus une épaule reste utilisable pendant la minute qui suit.
 */
verifier(
    'un code déjà consommé est refusé au second emploi',
    Totp::verifier($secret, $codeCourant, $pasCourant) === null,
    'le code a été accepté deux fois'
);
verifier(
    'un code plus ancien que le dernier consommé est refusé',
    Totp::verifier($secret, Totp::code($secret, $pasCourant - 1), $pasCourant) === null,
    'un code antérieur reste accepté'
);
verifier(
    'le pas suivant reste accepté après consommation du courant',
    Totp::verifier($secret, Totp::code($secret, $pasCourant + 1), $pasCourant) === $pasCourant + 1,
    'la consommation bloque aussi le pas suivant'
);

// Saisies mal formées : après nettoyage, le code doit compter six chiffres.
// « 000000 » n’est pas dans cette liste : c’est un code bien formé, qui a
// simplement une chance sur un million d’être le bon.
foreach (['' => '(vide)', '12345' => '12345', '1234567' => '1234567', 'abcdef' => 'abcdef', '12 34' => '12 34'] as $saisie => $intitule) {
    verifier(
        sprintf('une saisie « %s » est refusée', $intitule),
        Totp::verifier($secret, (string) $saisie, null, $pasCourant) === null,
        'saisie acceptée à tort'
    );
}

verifier(
    'un code d’un autre secret est refusé',
    Totp::verifier(Totp::secret(), $codeCourant) === null,
    'un code étranger est accepté'
);

// ---------------------------------------------------------------------------
echo "\n=== 5. URI d’enrôlement ===\n";

$uri = Totp::uri($secretRfc, 'admin@souriane.sn', 'Souriane');

verifier(
    'l’URI utilise le schéma otpauth',
    str_starts_with($uri, 'otpauth://totp/'),
    $uri
);
verifier(
    'l’URI porte le secret',
    str_contains($uri, 'secret=' . $secretRfc),
    $uri
);
verifier(
    'l’URI porte l’émetteur, attendu par les applications',
    str_contains($uri, 'issuer=Souriane'),
    $uri
);
verifier(
    'l’URI déclare l’algorithme, le nombre de chiffres et la période',
    str_contains($uri, 'algorithm=SHA1')
    && str_contains($uri, 'digits=' . Totp::CHIFFRES)
    && str_contains($uri, 'period=' . Totp::PERIODE),
    $uri
);
verifier(
    'le compte est encodé dans le chemin, avec l’émetteur en préfixe',
    str_contains($uri, rawurlencode('Souriane:admin@souriane.sn')),
    $uri
);

// ---------------------------------------------------------------------------
$total = $reussis + $echecs;
echo "\n" . str_repeat('=', 62) . "\n";
echo sprintf("Codes à usage unique — %d/%d vérifications réussies", $reussis, $total) . "\n";
echo str_repeat('=', 62) . "\n";

exit($echecs === 0 ? 0 : 1);
