<?php
/**
 * SOURIANE — Limitation de débit : débit contre authentification
 *
 * Vérifie la séparation entre les deux usages du limiteur :
 *
 *   - `verifier()` limite un DÉBIT : chaque appel compte, réussi ou non ;
 *   - `bloque()` / `echec()` / `oublier()` protègent une AUTHENTIFICATION :
 *     seuls les échecs comptent, et une réussite remet le compteur à zéro.
 *
 * C’est ce second point qui manquait : auparavant, cinq connexions RÉUSSIES
 * suffisaient à verrouiller une adresse pendant quinze minutes.
 *
 * Utilisation : php tests/verifier_limiteur.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use Souriane\Core\RateLimiter;

$reussis = 0;
$echecs  = 0;

function controle(string $intitule, bool $ok, string $detail = ''): void
{
    global $reussis, $echecs;

    if ($ok) {
        $reussis++;
        printf("  [OK]     %s\n", $intitule);
    } else {
        $echecs++;
        printf("  [ECHEC]  %s%s\n", $intitule, $detail !== '' ? ' — ' . $detail : '');
    }
}

echo "\n=== Limiteur de débit (ch. 8) ===\n\n";

$id = 'identite-de-test-' . bin2hex(random_bytes(3));

// ---------------------------------------------------------------------------
// 1. Consulter l’état ne consomme rien
// ---------------------------------------------------------------------------
$cle = 'test-bloque-' . bin2hex(random_bytes(4));
RateLimiter::oublier($cle, $id);

$dernier = null;
for ($i = 0; $i < 12; $i++) {
    $dernier = RateLimiter::bloque($cle, $id, 3, 60);
}

controle(
    'bloque() ne consomme aucune tentative, même appelé en boucle',
    $dernier !== null && $dernier['autorise'] === true && $dernier['restant'] === 3,
    $dernier === null ? 'aucun résultat' : 'restant=' . $dernier['restant']
);

// ---------------------------------------------------------------------------
// 2. Un échec consomme exactement une tentative
// ---------------------------------------------------------------------------
RateLimiter::echec($cle, $id, 60);
$apresUnEchec = RateLimiter::bloque($cle, $id, 3, 60);

controle(
    'un échec consomme une tentative',
    $apresUnEchec['restant'] === 2,
    'restant=' . $apresUnEchec['restant']
);

// ---------------------------------------------------------------------------
// 3. Le seuil atteint bloque, et l’attente est annoncée
// ---------------------------------------------------------------------------
RateLimiter::echec($cle, $id, 60);
RateLimiter::echec($cle, $id, 60);
$bloque = RateLimiter::bloque($cle, $id, 3, 60);

controle(
    'le seuil atteint bloque la tentative suivante',
    $bloque['autorise'] === false && $bloque['restant'] === 0,
    'autorise=' . var_export($bloque['autorise'], true)
);
controle(
    'l’attente restante est annoncée et tient dans la fenêtre',
    $bloque['reessayer_dans'] > 0 && $bloque['reessayer_dans'] <= 60,
    'reessayer_dans=' . $bloque['reessayer_dans']
);

// ---------------------------------------------------------------------------
// 4. Insister ne rallonge pas le blocage
// ---------------------------------------------------------------------------
$avant = RateLimiter::bloque($cle, $id, 3, 60);
sleep(2);
$apres = RateLimiter::bloque($cle, $id, 3, 60);

controle(
    'insister pendant le blocage ne rallonge pas l’attente',
    $apres['reessayer_dans'] < $avant['reessayer_dans'],
    'avant=' . $avant['reessayer_dans'] . ' apres=' . $apres['reessayer_dans']
);

// ---------------------------------------------------------------------------
// 5. Une réussite remet le compteur à zéro
// ---------------------------------------------------------------------------
RateLimiter::oublier($cle, $id);
$apresOubli = RateLimiter::bloque($cle, $id, 3, 60);

controle(
    'oublier() remet le compteur à zéro (connexion réussie)',
    $apresOubli['autorise'] === true && $apresOubli['restant'] === 3,
    'restant=' . $apresOubli['restant']
);

// ---------------------------------------------------------------------------
// 6. `verifier()` garde son comportement : compter CHAQUE appel
// ---------------------------------------------------------------------------
$cleDebit = 'test-debit-' . bin2hex(random_bytes(4));
RateLimiter::oublier($cleDebit, $id);

$d1 = RateLimiter::verifier($cleDebit, $id, 2, 60);
$d2 = RateLimiter::verifier($cleDebit, $id, 2, 60);
$d3 = RateLimiter::verifier($cleDebit, $id, 2, 60);

controle(
    'verifier() compte chaque appel, réussi ou non (débit)',
    $d1['autorise'] === true && $d2['autorise'] === true && $d3['autorise'] === false,
    'r1=' . var_export($d1['autorise'], true) . ' r2=' . var_export($d2['autorise'], true) . ' r3=' . var_export($d3['autorise'], true)
);

// ---------------------------------------------------------------------------
// 7. Une fenêtre écoulée libère l’accès
// ---------------------------------------------------------------------------
$cleCourte = 'test-fenetre-' . bin2hex(random_bytes(4));
RateLimiter::oublier($cleCourte, $id);
RateLimiter::echec($cleCourte, $id, 1);   // fenêtre d’une seconde
sleep(2);
$libere = RateLimiter::bloque($cleCourte, $id, 1, 1);

controle(
    'une fenêtre écoulée libère l’accès',
    $libere['autorise'] === true,
    'autorise=' . var_export($libere['autorise'], true)
);

// ---------------------------------------------------------------------------
// 8. Deux points d’entrée ne partagent pas leur compteur
// ---------------------------------------------------------------------------
$cleA = 'test-iso-a-' . bin2hex(random_bytes(4));
$cleB = 'test-iso-b-' . bin2hex(random_bytes(4));
RateLimiter::oublier($cleA, $id);
RateLimiter::oublier($cleB, $id);

RateLimiter::echec($cleA, $id, 60);
RateLimiter::echec($cleA, $id, 60);
$autre = RateLimiter::bloque($cleB, $id, 2, 60);

controle(
    'deux points d’entrée ont des compteurs indépendants',
    $autre['autorise'] === true && $autre['restant'] === 2,
    'restant=' . $autre['restant']
);

// ---------------------------------------------------------------------------
// 9. Deux identités ne partagent pas leur compteur
// ---------------------------------------------------------------------------
$idBis = 'autre-identite-' . bin2hex(random_bytes(3));
RateLimiter::oublier($cleA, $idBis);
$autreId = RateLimiter::bloque($cleA, $idBis, 2, 60);

controle(
    'deux adresses réseau ont des compteurs indépendants',
    $autreId['autorise'] === true && $autreId['restant'] === 2,
    'restant=' . $autreId['restant']
);

// ---------------------------------------------------------------------------
// Nettoyage : aucun compteur de test ne doit rester sur le disque
// ---------------------------------------------------------------------------
foreach ([$cle, $cleDebit, $cleCourte, $cleA, $cleB] as $c) {
    RateLimiter::oublier($c, $id);
    RateLimiter::oublier($c, $idBis);
}

$restants = glob(STORAGE_PATH . '/limites/*.json') ?: [];
$intrus   = array_filter($restants, static fn ($f) => str_contains($f, hash('sha256', 'test-')));

controle(
    'aucun compteur de test ne subsiste sur le disque',
    $intrus === [],
    count($intrus) . ' fichier(s) résiduel(s)'
);

echo "\n", str_repeat('=', 60), "\n";
printf("  Réussis : %d   |   Échecs : %d\n", $reussis, $echecs);
echo str_repeat('=', 60), "\n\n";

exit($echecs === 0 ? 0 : 1);
