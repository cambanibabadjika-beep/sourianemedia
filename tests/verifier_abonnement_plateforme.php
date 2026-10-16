<?php
/**
 * Vérification de la portée des abonnements aux notifications.
 * Exécution : php tests/verifier_abonnement_plateforme.php
 *
 * Le visiteur qui installe l’application ne suit pas seulement l’annonceur
 * dont il a scanné le QR code : il peut demander à recevoir les offres de tout
 * le réseau. Cette suite contrôle le socle de cette promesse :
 *
 *   1. un abonnement « plateforme » n’est rattaché à aucun annonceur ;
 *   2. un abonnement « annonceur » reste rattaché au sien ;
 *   3. la diffusion sert l’abonné « plateforme » pour n’importe quel
 *      annonceur, et l’abonné « annonceur » pour le sien seulement ;
 *   4. un même appareil n’a qu’une ligne : changer de portée met à jour
 *      l’abonnement au lieu d’en créer un second ;
 *   5. la page de découverte ne renvoie jamais deux fois la même page.
 *
 * Les abonnés créés pour le test sont supprimés en fin de parcours.
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once dirname(__DIR__) . '/app/bootstrap.php';

use Souriane\Core\Database;
use Souriane\Models\Abonne;
use Souriane\Models\Page;

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

$db = Database::instance();

// Deux annonceurs actifs distincts sont nécessaires pour distinguer les portées.
$annonceurs = $db->select("SELECT id, nom FROM annonceurs WHERE statut = 'actif' ORDER BY id LIMIT 2");

if (count($annonceurs) < 2) {
    echo "\nDeux annonceurs actifs au moins sont nécessaires pour cette vérification.\n";
    exit(1);
}

$A = (int) $annonceurs[0]['id'];
$B = (int) $annonceurs[1]['id'];

// Marqueur unique : les lignes du test sont reconnaissables et supprimables.
$marqueur     = 'test-portee-' . bin2hex(random_bytes(4));
$epPlateforme = 'https://push.example.test/' . $marqueur . '-plateforme';
$epAnnonceur  = 'https://push.example.test/' . $marqueur . '-annonceur';

$nettoyer = static function () use ($db, $marqueur): void {
    $db->query("DELETE FROM abonnes WHERE endpoint LIKE :motif", ['motif' => '%' . $marqueur . '%']);
};

$nettoyer();

// ---------------------------------------------------------------------------
echo "\n=== 1. Abonnement « plateforme » ===\n";

$idPlateforme = Abonne::enregistrer($A, $epPlateforme, 'cle-p256', 'cle-auth', 'chaque_annonce', 'plateforme');
$ligne = $db->selectOne('SELECT * FROM abonnes WHERE id = :id', ['id' => $idPlateforme]);

verifier('la ligne est créée', $ligne !== null);
verifier('la portée vaut « plateforme »', ($ligne['portee'] ?? '') === 'plateforme',
    'portée : ' . var_export($ligne['portee'] ?? null, true));
// La clé existe toujours : on compare la valeur elle-même, sans ?? — qui
// remplacerait NULL par la valeur de repli et rendrait le contrôle inopérant.
verifier('aucun annonceur n’est rattaché', $ligne['annonceur_id'] === null,
    'annonceur_id : ' . var_export($ligne['annonceur_id'], true));

// ---------------------------------------------------------------------------
echo "\n=== 2. Abonnement « annonceur » ===\n";

$idAnnonceur = Abonne::enregistrer($A, $epAnnonceur, 'cle-p256', 'cle-auth', 'chaque_annonce', 'annonceur');
$ligne = $db->selectOne('SELECT * FROM abonnes WHERE id = :id', ['id' => $idAnnonceur]);

verifier('la portée vaut « annonceur »', ($ligne['portee'] ?? '') === 'annonceur',
    'portée : ' . var_export($ligne['portee'] ?? null, true));
verifier('l’annonceur est conservé', (int) ($ligne['annonceur_id'] ?? 0) === $A);

// ---------------------------------------------------------------------------
echo "\n=== 3. Qui reçoit les offres de qui ===\n";

$servisPourA = array_map('intval', array_column(Abonne::actifs($A), 'id'));
$servisPourB = array_map('intval', array_column(Abonne::actifs($B), 'id'));

verifier('l’abonné « plateforme » est servi pour l’annonceur A',
    in_array($idPlateforme, $servisPourA, true));
verifier('l’abonné « plateforme » est servi pour l’annonceur B',
    in_array($idPlateforme, $servisPourB, true));
verifier('l’abonné « annonceur A » est servi pour l’annonceur A',
    in_array($idAnnonceur, $servisPourA, true));
verifier('l’abonné « annonceur A » n’est pas servi pour l’annonceur B',
    !in_array($idAnnonceur, $servisPourB, true));

// ---------------------------------------------------------------------------
echo "\n=== 4. Un appareil, une ligne ===\n";

// Le même endpoint se réinscrit : la ligne doit être mise à jour, pas dupliquée.
$idReprise = Abonne::enregistrer($B, $epPlateforme, 'cle-p256', 'cle-auth', 'chaque_annonce', 'annonceur');
$ligne     = $db->selectOne('SELECT * FROM abonnes WHERE id = :id', ['id' => $idPlateforme]);

verifier('la réinscription réutilise la même ligne', $idReprise === $idPlateforme,
    'identifiants : ' . $idReprise . ' / ' . $idPlateforme);
verifier('la portée revient à « annonceur »', ($ligne['portee'] ?? '') === 'annonceur');
verifier('l’annonceur devient B', (int) ($ligne['annonceur_id'] ?? 0) === $B);

$nombreLignes = (int) $db->selectValue(
    'SELECT COUNT(*) FROM abonnes WHERE endpoint_hash = :h',
    ['h' => hash('sha256', $epPlateforme)],
    0
);
verifier('il n’existe qu’une ligne pour cet endpoint', $nombreLignes === 1, 'lignes : ' . $nombreLignes);

// ---------------------------------------------------------------------------
echo "\n=== 5. Compteur des abonnés « plateforme » ===\n";

$avant = Abonne::compterPlateforme();
Abonne::enregistrer($A, $epPlateforme, 'cle-p256', 'cle-auth', 'chaque_annonce', 'plateforme');
$apres = Abonne::compterPlateforme();

verifier('le compteur augmente d’une unité', $apres === $avant + 1, "avant : $avant, après : $apres");

// ---------------------------------------------------------------------------
echo "\n=== 6. Page de découverte ===\n";

$offres = Page::publieesAvecOffre(60);
$ids    = array_map(static fn (array $o): int => (int) $o['id'], $offres);

verifier('la requête ne renvoie pas de doublon de page',
    count($ids) === count(array_unique($ids)),
    count($ids) . ' lignes pour ' . count(array_unique($ids)) . ' pages distinctes');
verifier('chaque ligne porte une adresse courte',
    array_reduce($offres, static fn (bool $c, array $o): bool => $c && $o['adresse_courte'] !== '', true));

// ---------------------------------------------------------------------------
$nettoyer();
$restants = (int) $db->selectValue(
    'SELECT COUNT(*) FROM abonnes WHERE endpoint LIKE :motif',
    ['motif' => '%' . $marqueur . '%'],
    0
);
verifier('les abonnés de test sont supprimés', $restants === 0, 'restants : ' . $restants);

$total = $reussis + $echecs;
echo "\n" . str_repeat('=', 62) . "\n";
echo sprintf('Abonnement plateforme — %d/%d vérifications réussies', $reussis, $total) . "\n";
echo str_repeat('=', 62) . "\n";

exit($echecs === 0 ? 0 : 1);
