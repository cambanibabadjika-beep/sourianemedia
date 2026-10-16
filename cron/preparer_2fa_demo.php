<?php
/**
 * SOURIANE — Second facteur des comptes de démonstration
 *
 * Le chapitre 8 impose une authentification à deux facteurs sur les comptes
 * d’administration. Or la base de démonstration est réimportée à chaque
 * exécution des suites de vérification : sans ce script, la démonstration
 * s’arrêterait sur un écran de code que personne ne peut franchir, et les
 * suites ne pourraient plus se connecter.
 *
 * Le script refuse de s’exécuter hors mode démonstration. En production, le
 * second facteur se met en place depuis l’écran « Sécurité du compte », avec
 * une clé que personne d’autre ne connaît.
 *
 * Utilisation :
 *   php cron/preparer_2fa_demo.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use Souriane\Core\Database;
use Souriane\Core\Totp;
use Souriane\Services\SecondFacteur;

/** Comptes d’administration de la démonstration. */
const COMPTES_DEMO = ['admin@souriane.sn', 'agent@souriane.sn'];

/**
 * Clé partagée par les comptes de démonstration.
 *
 * Elle est fixe, et non tirée au hasard, pour que les suites de vérification
 * puissent la relire d’une exécution à l’autre. Elle vaut exactement la clé
 * ASCII « 12345678901234567890 » de l’annexe B de la RFC 6238 — les tests
 * disposent donc d’un point d’ancrage publié, indépendant de nous.
 *
 * Le secret n’est pas plus sensible que le mot de passe de démonstration, qui
 * est affiché en clair sur l’écran de connexion lorsque APP_DEBUG est actif.
 */
const SECRET_DEMO = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

echo "Souriane — second facteur des comptes de démonstration\n";
echo str_repeat('-', 58), "\n";

if (!APP_DEBUG) {
    fwrite(STDERR, "Refusé : APP_DEBUG est désactivé.\n\n");
    fwrite(STDERR, "Ce script n’existe que pour la démonstration. En production, activez le\n");
    fwrite(STDERR, "second facteur depuis « Sécurité du compte », avec votre propre clé :\n");
    fwrite(STDERR, "c’est la seule qui ne soit connue que de vous.\n");
    exit(1);
}

$db    = Database::instance();
$faits = 0;

foreach (COMPTES_DEMO as $email) {
    $utilisateur = $db->selectOne(
        'SELECT * FROM utilisateurs WHERE email = :email LIMIT 1',
        ['email' => $email]
    );

    if ($utilisateur === null) {
        echo "· $email — compte absent, ignoré\n";
        continue;
    }

    $id = (int) $utilisateur['id'];

    // Le code attendu est calculé par l’implémentation elle-même : si la clé
    // ou le calcul étaient fautifs, l’activation échouerait ici plutôt que
    // silencieusement sur le poste de l’exploitant.
    $codes = SecondFacteur::confirmer($id, SECRET_DEMO, Totp::code(SECRET_DEMO));

    if ($codes === null) {
        fwrite(STDERR, "!! $email — activation refusée par la vérification\n");
        exit(1);
    }

    $faits++;
    echo '· ', $email, ' — second facteur actif, ', count($codes), " codes de secours\n";
}

echo str_repeat('-', 58), "\n";
echo "Comptes préparés : $faits\n";
echo "Clé partagée  : ", SECRET_DEMO, "\n";
echo "Code courant  : ", Totp::code(SECRET_DEMO), " (change toutes les ", Totp::PERIODE, " s)\n";

// La clé est écrite dans .env pour que l’écran de code puisse la rappeler en
// mode démonstration, exactement comme le mot de passe l’est déjà.
$cheminEnv = SOURIANE_ROOT . '/.env';
if (is_file($cheminEnv) && is_writable($cheminEnv)) {
    $contenu = (string) file_get_contents($cheminEnv);

    if (preg_match('/^SECURITE_2FA_SECRET_DEMO=.*$/m', $contenu) === 1) {
        $contenu = (string) preg_replace('/^SECURITE_2FA_SECRET_DEMO=.*$/m', 'SECURITE_2FA_SECRET_DEMO=' . SECRET_DEMO, $contenu);
    } else {
        $contenu = rtrim($contenu, "\n") . "\nSECURITE_2FA_SECRET_DEMO=" . SECRET_DEMO . "\n";
    }

    file_put_contents($cheminEnv, $contenu, LOCK_EX);
    echo "Fichier .env  : SECURITE_2FA_SECRET_DEMO renseigné\n";
} else {
    echo "Fichier .env  : non inscriptible, la clé n’y a pas été ajoutée\n";
}
