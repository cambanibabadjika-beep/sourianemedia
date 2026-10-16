<?php
/**
 * Vérification des contrastes de la charte.
 * Exécution : php tests/verifier_contraste.php
 *
 * Le cahier des charges impose l'accessibilité (ENF-05). Ce contrôle recalcule
 * les ratios de contraste WCAG 2.1 des principales paires texte/fond de la
 * charte violette et signale toute régression lors d'un changement de couleur.
 *
 * Seuils retenus :
 *   4.5:1 — texte courant (niveau AA)
 *   3.0:1 — grand texte, éléments d'interface et indicateurs de focus
 */

declare(strict_types=1);

$reussis = 0;
$echecs  = 0;

/** Luminance relative d'une couleur, selon WCAG 2.1. */
function luminance(string $hex): float
{
    $hex = ltrim($hex, '#');
    $canaux = [
        hexdec(substr($hex, 0, 2)) / 255,
        hexdec(substr($hex, 2, 2)) / 255,
        hexdec(substr($hex, 4, 2)) / 255,
    ];

    $lineaire = array_map(
        static fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
        $canaux
    );

    return 0.2126 * $lineaire[0] + 0.7152 * $lineaire[1] + 0.0722 * $lineaire[2];
}

/** Ratio de contraste entre deux couleurs. */
function contraste(string $a, string $b): float
{
    $la = luminance($a);
    $lb = luminance($b);

    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/**
 * Compose une couleur translucide par-dessus un fond opaque.
 *
 * Indispensable pour tester les surfaces de verre : le contraste réel dépend
 * de ce qu'il y a DERRIÈRE, pas du seul code hexadécimal de la surface. On
 * calcule donc la couleur effective, en mélange alpha simple (sRGB).
 *
 * @param string $fond   Couleur opaque du dessous, en #RRGGBB.
 * @param string $dessus Couleur de la couche translucide, en #RRGGBB.
 * @param float  $alpha  Opacité de la couche supérieure, de 0 à 1.
 */
function composer(string $fond, string $dessus, float $alpha): string
{
    $fond   = ltrim($fond, '#');
    $dessus = ltrim($dessus, '#');
    $sortie = '';

    for ($i = 0; $i < 3; $i++) {
        $bas   = hexdec(substr($fond, $i * 2, 2));
        $haut  = hexdec(substr($dessus, $i * 2, 2));
        $canal = (int) round($alpha * $haut + (1 - $alpha) * $bas);
        $sortie .= str_pad(dechex($canal), 2, '0', STR_PAD_LEFT);
    }

    return '#' . $sortie;
}

/**
 * Paires de la charte : [intitulé, texte, fond, seuil].
 * Les valeurs proviennent des jetons de assets/css/app.css.
 *
 * Pour les surfaces de verre, le fond est composé par composer(). On prend à
 * chaque fois le point le PLUS DÉFAVORABLE du dégradé de la bande « profil » :
 * un fond plus sombre donne une carte plus sombre, donc un contraste plus
 * faible pour du texte foncé. Tester #2E1065 (--violet-900) revient donc à
 * tester le pire cas.
 */
$verreClairSurProfil = composer('#2E1065', '#FFFFFF', 0.78);   // --verre-fond-fort
$verreFaibleSurProfil = composer('#2E1065', '#FFFFFF', 0.58);  // --verre-fond
$pastilleSurProfil    = composer('#4C1D95', '#FFFFFF', 0.12);  // .defileur__element

$paires = [
    // Texte sur fond clair
    ['Texte principal sur blanc',              '#1A1033', '#FFFFFF', 4.5],
    ['Texte secondaire sur blanc',             '#554B73', '#FFFFFF', 4.5],
    ['Texte discret sur blanc',                '#6F6690', '#FFFFFF', 4.5],
    ['Texte sur fond gris clair',              '#554B73', '#F2EFF7', 4.5],
    ['Texte sur fond violet très clair',       '#4C1D95', '#F5F3FF', 4.5],
    ['Texte sur lilas très clair',             '#4C1D95', '#F0EAFE', 4.5],

    // Liens et accents
    ['Lien violet sur blanc',                  '#4C1D95', '#FFFFFF', 4.5],
    ['Accent lilas sur blanc',                 '#7C3AED', '#FFFFFF', 4.5],

    // Boutons
    ['Blanc sur violet (bouton primaire)',     '#FFFFFF', '#4C1D95', 4.5],
    ['Blanc sur violet moyen (survol)',        '#FFFFFF', '#5B21B6', 4.5],
    ['Blanc sur violet profond',               '#FFFFFF', '#2E1065', 4.5],
    ['Texte foncé sur lilas (bouton secondaire)', '#2E1065', '#C4B5FD', 4.5],

    // Accent doré
    ['Or foncé sur or clair (conditions)',     '#B45309', '#FEF3C7', 4.5],
    ['Or sur violet (mot mis en avant)',       '#FBBF24', '#4C1D95', 3.0],
    ['Or sur violet profond',                  '#FBBF24', '#2E1065', 3.0],
    ['Texte foncé sur or (étiquette offre)',   '#1A1033', '#FBBF24', 4.5],

    // Bande « profil » et cartes de verre clair (section des chiffres)
    ['Nombre violet sur verre clair',          '#4C1D95', $verreClairSurProfil, 4.5],
    ['Étiquette sur verre clair',              '#3A3155', $verreClairSurProfil, 4.5],
    ['Blanc sur bande profil (titres)',        '#FFFFFF', '#2E1065', 4.5],
    ['Blanc sur pastille du défileur',         '#FFFFFF', $pastilleSurProfil, 4.5],
    // Témoin négatif : le verre à 58 % doit ÉCHOUER, sinon c'est que le calcul
    // de composition ne fait rien et que les tests ci-dessus sont faussés.
    ['[témoin] Nombre sur verre trop clair',   '#4C1D95', $verreFaibleSurProfil, 4.5],

    // Éléments d'interface
    ['Indicateur de focus sur blanc',          '#7C3AED', '#FFFFFF', 3.0],
    ['Indicateur de focus sur gris clair',     '#7C3AED', '#F2EFF7', 3.0],

    // États
    ['Succès sur fond succès',                 '#15803D', '#DCFCE7', 4.5],
    ['Alerte sur fond alerte',                 '#B91C1C', '#FEE2E2', 4.5],
    ['Attention sur fond attention',           '#B45309', '#FEF3C7', 4.5],
    ['Info sur fond info',                     '#1D4ED8', '#DBEAFE', 4.5],
    ['Blanc sur WhatsApp',                     '#FFFFFF', '#1A7F4B', 4.5],
    ['Blanc sur WhatsApp (survol)',            '#FFFFFF', '#14663C', 4.5],
];

/*
 * Thème sombre — back-office et page « Découvrir les offres ».
 *
 * Les surfaces sombres sont translucides : le contraste réel dépend de ce qu'il
 * y a derrière. On compose donc sur le halo le PLUS CLAIR du fond « aurore »,
 * qui est le cas le plus défavorable pour du texte clair — un fond plus clair
 * rapproche le texte de la couleur de son fond, donc abaisse le ratio.
 *
 * Ces paires verrouillent le correctif de thème sombre : avant lui, plusieurs
 * surfaces posaient un texte clair sur un pastel resté clair (case cochée,
 * encart, clé partagée), et la couleur de marque --violet servait de couleur de
 * texte sur un fond devenu sombre.
 */
$haloSombre    = composer('#0B0718', '#7C3AED', 0.48);       // halo le plus vif du fond
$panneauSombre = composer($haloSombre, '#160F2E', 0.58);     // --verre-fond sombre, stop le plus transparent
$enteteTableau = composer($panneauSombre, '#221940', 0.72);  // .tableau th
$champSombre   = composer($panneauSombre, '#140F26', 0.72);  // .cle-secret, .codes-secours li
$optionCochee  = composer($panneauSombre, '#7C3AED', 0.30);  // .case / .frequence__option cochée
$encartSombre  = composer($panneauSombre, '#7C3AED', 0.16);  // .encart
$encartAvert   = composer($panneauSombre, '#B45309', 0.20);  // .encart--avertissement
$ligneSurvolee = composer($panneauSombre, '#7C3AED', 0.16);  // .tableau tbody tr:hover

$pairesSombre = [
    // Back-office
    ['Admin sombre — icône de titre de panneau', '#C4B5FD', $panneauSombre, 4.5],
    ['Admin sombre — en-tête de tableau',        '#B9AFD6', $enteteTableau, 4.5],
    ['Admin sombre — onglet actif',              '#C4B5FD', $haloSombre,   4.5],
    ['Admin sombre — onglet inactif',            '#B9AFD6', $haloSombre,   4.5],
    ['Admin sombre — texte sur case cochée',     '#ECE8F6', $optionCochee, 4.5],
    ['Admin sombre — texte d’encart',            '#B9AFD6', $encartSombre, 4.5],
    ['Admin sombre — encart d’avertissement',    '#FCD34D', $encartAvert,  4.5],
    ['Admin sombre — clé partagée',              '#C4B5FD', $champSombre,  4.5],
    ['Admin sombre — ligne de tableau survolée', '#ECE8F6', $ligneSurvolee, 4.5],

    // Page « Découvrir les offres »
    ['Découvrir — nom d’annonceur',              '#ECE8F6', $panneauSombre, 4.5],
    ['Découvrir — résumé',                       '#A79EC4', $panneauSombre, 4.5],
    ['Découvrir — validité',                     '#A79EC4', $panneauSombre, 4.5],
    ['Découvrir — appel à l’action',             '#C4B5FD', $panneauSombre, 4.5],

    // Feuille « Rester informé » du site public
    ['Feuille sombre — option cochée',           '#ECE8F6', $optionCochee, 4.5],
];

/*
 * Écran de connexion du back-office.
 *
 * La carte (88 % opaque) est posée sur le dégradé violet de la page, lui-même
 * éclairé par son halo : on compose donc sur le halo le plus clair, cas le plus
 * défavorable. Ces paires verrouillent une régression observée — des couleurs
 * claires figées (#1A1033, #554B73) laissées sur la carte devenue sombre
 * rendaient les libellés de champ invisibles.
 */
$haloConnexion  = composer('#2E1065', '#A78BFA', 0.45);       // halo violet du fond de page
$carteConnexion = composer($haloConnexion, '#160F2E', 0.88);  // .connexion-carte (thème sombre)
$champConnexion = composer($carteConnexion, '#140F26', 0.72); // .saisie posée sur la carte

$pairesConnexion = [
    ['Connexion sombre — titre',                 '#FFFFFF', $carteConnexion, 4.5],
    ['Connexion sombre — libellé de champ',      '#ECE8F6', $carteConnexion, 4.5],
    ['Connexion sombre — texte d’introduction',  '#B9AFD6', $carteConnexion, 4.5],
    ['Connexion sombre — aide de champ',         '#A79EC4', $carteConnexion, 4.5],
    ['Connexion sombre — saisie',                '#ECE8F6', $champConnexion, 4.5],
    // Jeton --lien en thème sombre : c'est le « ← Retour au site public » du
    // pied de carte. Il héritait de --violet (#4C1D95), soit 1,7:1 — invisible.
    ['Connexion sombre — lien de retour',        '#C4B5FD', $carteConnexion, 4.5],
];

$paires = array_merge($paires, $pairesConnexion);

$paires = array_merge($paires, $pairesSombre);

/*
 * Surfaces de verre du back-office — en-tête collant et barre latérale.
 *
 * Leur fond est un dégradé (--verre-fond-fort, et le dégradé propre à la barre
 * latérale). C'est l'arrêt le plus opaque qui définit la matière de la surface,
 * donc le cas le plus clair — le plus défavorable pour du texte clair. Ces
 * paires verrouillent la contrainte qui a fixé --gris-500 en thème sombre : sur
 * le fond noir seul, #7C729E frôlait le seuil (4,49:1) ; sur ces surfaces, il
 * tombait franchement dessous (4,2:1).
 */
$enteteSombre   = composer('#0B0718', '#2E1E58', 0.92);  // --verre-fond-fort, arrêt le plus opaque
$lateraleSombre = composer('#0B0718', '#2E1065', 0.94);  // dégradé de .admin-laterale

$pairesSurfaces = [
    ['Admin sombre — titre de groupe (barre latérale)', '#9A91BA', $lateraleSombre, 4.5],
    ['Admin sombre — pied de barre latérale',           '#9A91BA', $lateraleSombre, 4.5],
    ['Admin sombre — texte secondaire sur en-tête',     '#9A91BA', $enteteSombre,   4.5],
    ['Admin sombre — colonne secondaire de tableau',    '#9A91BA', $enteteTableau,  4.5],
    ['Admin sombre — pictogramme accentué',             '#C4B5FD', $panneauSombre,  3.0],
];

$paires = array_merge($paires, $pairesSurfaces);

// Le témoin négatif est le seul cas dont on ATTEND l'échec : il ne doit pas
// être compté comme une régression de la charte.
$attenduEnEchec = '[témoin] Nombre sur verre trop clair';

echo "\n=== Contrastes de la charte (WCAG 2.1) ===\n\n";

foreach ($paires as [$intitule, $texte, $fond, $seuil]) {
    $ratio  = contraste($texte, $fond);
    $ok     = $ratio >= $seuil;
    $temoin = $intitule === $attenduEnEchec;

    if ($temoin) {
        $reussis++;
        printf("  [TEMOIN] %-42s %5.2f:1  (échec attendu, composition vérifiée)\n", $intitule, $ratio);
        continue;
    }

    if ($ok) {
        $reussis++;
        printf("  [OK]     %-42s %5.2f:1\n", $intitule, $ratio);
    } else {
        $echecs++;
        printf("  [ECHEC]  %-42s %5.2f:1  (minimum %.1f) — %s sur %s\n",
            $intitule, $ratio, $seuil, $texte, $fond);
    }
}

$total = $reussis + $echecs;
echo "\n" . str_repeat('=', 62) . "\n";
echo sprintf("Contrastes — %d/%d conformes", $reussis, $total) . "\n";
echo str_repeat('=', 62) . "\n";

exit($echecs === 0 ? 0 : 1);
