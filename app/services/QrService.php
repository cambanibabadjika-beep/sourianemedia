<?php
/**
 * SOURIANE — Générateur de QR codes (ch. 5.1)
 *
 * Implémentation autonome, sans dépendance externe : encodage en mode octet,
 * correction d’erreur Reed-Solomon, sélection du masque optimal, sortie SVG.
 *
 * Le niveau de correction visé est Q ou H (25 à 30 % de redondance), requis
 * pour rester lisible malgré une salissure, un coin arraché ou un reflet.
 */

declare(strict_types=1);

namespace Souriane\Services;

use InvalidArgumentException;

final class QrService
{
    /** Niveaux de correction acceptés. */
    private const NIVEAUX = ['L' => 0b01, 'M' => 0b00, 'Q' => 0b11, 'H' => 0b10];

    /** Version maximale prise en charge (largement suffisant pour une URL courte). */
    private const VERSION_MAX = 10;

    /**
     * Nombre de mots de correction et répartition en blocs.
     * version => niveau => [mots EC par bloc, [[nb blocs, mots de données par bloc], …]]
     */
    private const BLOCS = [
        1  => ['L' => [7,  [[1, 19]]],        'M' => [10, [[1, 16]]],       'Q' => [13, [[1, 13]]],        'H' => [17, [[1, 9]]]],
        2  => ['L' => [10, [[1, 34]]],        'M' => [16, [[1, 28]]],       'Q' => [22, [[1, 22]]],        'H' => [28, [[1, 16]]]],
        3  => ['L' => [15, [[1, 55]]],        'M' => [26, [[1, 44]]],       'Q' => [18, [[2, 17]]],        'H' => [22, [[2, 13]]]],
        4  => ['L' => [20, [[1, 80]]],        'M' => [18, [[2, 32]]],       'Q' => [26, [[2, 24]]],        'H' => [16, [[4, 9]]]],
        5  => ['L' => [26, [[1, 108]]],       'M' => [24, [[2, 43]]],       'Q' => [18, [[2, 15], [2, 16]]], 'H' => [22, [[2, 11], [2, 12]]]],
        6  => ['L' => [18, [[2, 68]]],        'M' => [16, [[4, 27]]],       'Q' => [24, [[4, 19]]],        'H' => [28, [[4, 15]]]],
        7  => ['L' => [20, [[2, 78]]],        'M' => [18, [[4, 31]]],       'Q' => [18, [[2, 14], [4, 15]]], 'H' => [26, [[4, 13], [1, 14]]]],
        8  => ['L' => [24, [[2, 97]]],        'M' => [22, [[2, 38], [2, 39]]], 'Q' => [22, [[4, 18], [2, 19]]], 'H' => [26, [[4, 14], [2, 15]]]],
        9  => ['L' => [30, [[2, 116]]],       'M' => [22, [[3, 36], [2, 37]]], 'Q' => [20, [[4, 16], [4, 17]]], 'H' => [24, [[4, 12], [4, 13]]]],
        10 => ['L' => [18, [[2, 68], [2, 69]]], 'M' => [26, [[4, 43], [1, 44]]], 'Q' => [24, [[6, 19], [2, 20]]], 'H' => [28, [[6, 15], [2, 16]]]],
    ];

    /** Coordonnées des motifs d’alignement. */
    private const ALIGNEMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    /** Informations de version (v >= 7). */
    private const INFO_VERSION = [
        7 => 0x07C94, 8 => 0x085BC, 9 => 0x09A99, 10 => 0x0A4D3,
    ];

    private static ?array $expTable = null;
    private static ?array $logTable = null;

    /**
     * Retourne la matrice de modules (true = sombre).
     *
     * @return array<int, array<int, bool>>
     */
    public static function matrice(string $donnees, string $niveau = 'Q'): array
    {
        $niveau = strtoupper($niveau);
        if (!isset(self::NIVEAUX[$niveau])) {
            throw new InvalidArgumentException('Niveau de correction inconnu : ' . $niveau);
        }
        if ($donnees === '') {
            throw new InvalidArgumentException('Aucune donnée à encoder.');
        }

        $version = self::choisirVersion(strlen($donnees), $niveau);
        $taille  = $version * 4 + 17;

        // --- 1. Encodage des données -------------------------------------
        $flux = self::encoderDonnees($donnees, $version, $niveau);

        // --- 2. Correction d’erreur --------------------------------------
        $final = self::entrelacer($flux, $version, $niveau);

        // --- 3. Construction de la matrice -------------------------------
        $modules   = array_fill(0, $taille, array_fill(0, $taille, false));
        $reserves  = array_fill(0, $taille, array_fill(0, $taille, false));

        self::poserMotifsFixes($modules, $reserves, $version, $taille);
        self::poserDonnees($modules, $reserves, $final, $taille);

        // --- 4. Sélection du masque optimal ------------------------------
        $meilleur = null;
        $meilleurScore = PHP_INT_MAX;

        for ($masque = 0; $masque < 8; $masque++) {
            $essai = self::appliquerMasque($modules, $reserves, $masque, $taille);
            self::poserInfosFormat($essai, $niveau, $masque, $taille);
            if ($version >= 7) {
                self::poserInfosVersion($essai, $version, $taille);
            }
            $score = self::penalite($essai, $taille);
            if ($score < $meilleurScore) {
                $meilleurScore = $score;
                $meilleur = $essai;
            }
        }

        return $meilleur;
    }

    /**
     * Rend le QR code en SVG.
     *
     * @param int $moduleSize Taille d’un module en pixels
     * @param int $zoneSilence Marge blanche en modules (4 minimum, ch. 5.1)
     */
    public static function svg(
        string $donnees,
        string $niveau = 'Q',
        int $moduleSize = 6,
        int $zoneSilence = 4,
        string $couleur = '#4C1D95'
    ): string {
        $modules = self::matrice($donnees, $niveau);
        $n       = count($modules);
        $total   = ($n + $zoneSilence * 2) * $moduleSize;

        $rects = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($modules[$i][$j]) {
                    $x = ($j + $zoneSilence) * $moduleSize;
                    $y = ($i + $zoneSilence) * $moduleSize;
                    $rects[] = sprintf(
                        '<rect x="%d" y="%d" width="%d" height="%d"/>',
                        $x, $y, $moduleSize, $moduleSize
                    );
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d" role="img" aria-label="QR code">'
            . '<rect width="%1$d" height="%1$d" fill="#FFFFFF"/>'
            . '<g fill="%2$s" shape-rendering="crispEdges">%3$s</g></svg>',
            $total,
            $couleur,
            implode('', $rects)
        );
    }

    /** Retourne la matrice sous forme de chaînes « 0 » / « 1 ». */
    public static function matriceTexte(string $donnees, string $niveau = 'Q'): array
    {
        return array_map(
            static fn (array $ligne): string => implode('', array_map(
                static fn (bool $m): string => $m ? '1' : '0',
                $ligne
            )),
            self::matrice($donnees, $niveau)
        );
    }

    // =======================================================================
    // Encodage
    // =======================================================================

    private static function choisirVersion(int $longueurDonnees, string $niveau): int
    {
        for ($version = 1; $version <= self::VERSION_MAX; $version++) {
            $capacite = self::capaciteDonnees($version, $niveau);
            $bitsCompteur = $version <= 9 ? 8 : 16;
            // 4 bits de mode + compteur + données + jusqu’à 4 bits de bourrage
            if (($longueurDonnees * 8 + 4 + $bitsCompteur) <= $capacite * 8) {
                return $version;
            }
        }

        throw new InvalidArgumentException(
            'Données trop longues pour un QR code de version ' . self::VERSION_MAX . ' : ' . $longueurDonnees . ' octets.'
        );
    }

    private static function capaciteDonnees(int $version, string $niveau): int
    {
        $total = 0;
        foreach (self::BLOCS[$version][$niveau][1] as [$nbBlocs, $motsDonnees]) {
            $total += $nbBlocs * $motsDonnees;
        }

        return $total;
    }

    /** Construit le flux binaire : mode + compteur + données + bourrage. */
    private static function encoderDonnees(string $donnees, int $version, string $niveau): array
    {
        $bits = '';

        // Indicateur de mode : octet = 0100
        $bits .= '0100';

        // Compteur de caractères
        $longueur = strlen($donnees);
        $bits .= str_pad(decbin($longueur), $version <= 9 ? 8 : 16, '0', STR_PAD_LEFT);

        // Données
        foreach (str_split($donnees) as $octet) {
            $bits .= str_pad(decbin(ord($octet)), 8, '0', STR_PAD_LEFT);
        }

        $capaciteBits = self::capaciteDonnees($version, $niveau) * 8;

        // Terminateur (jusqu’à 4 zéros)
        $bits .= str_repeat('0', min(4, $capaciteBits - strlen($bits)));

        // Alignement sur l’octet
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        }

        // Octets de bourrage alternés 0xEC / 0x11
        $bourrage = ['11101100', '00010001'];
        $i = 0;
        while (strlen($bits) < $capaciteBits) {
            $bits .= $bourrage[$i % 2];
            $i++;
        }

        // Découpage en octets
        $octets = [];
        foreach (str_split($bits, 8) as $octet) {
            $octets[] = bindec($octet);
        }

        return $octets;
    }

    /** Calcule les mots de correction et entrelace les blocs. */
    private static function entrelacer(array $donnees, int $version, string $niveau): array
    {
        [$motsEc, $repartition] = self::BLOCS[$version][$niveau];

        $blocs = [];
        $position = 0;

        foreach ($repartition as [$nbBlocs, $motsDonnees]) {
            for ($b = 0; $b < $nbBlocs; $b++) {
                $bloc = array_slice($donnees, $position, $motsDonnees);
                $position += $motsDonnees;

                $blocs[] = [
                    'donnees' => $bloc,
                    'ec'      => self::reedSolomon($bloc, $motsEc),
                ];
            }
        }

        $maxDonnees = max(array_map(static fn (array $b): int => count($b['donnees']), $blocs));

        $resultat = [];
        // Entrelacement des mots de données
        for ($i = 0; $i < $maxDonnees; $i++) {
            foreach ($blocs as $bloc) {
                if (isset($bloc['donnees'][$i])) {
                    $resultat[] = $bloc['donnees'][$i];
                }
            }
        }
        // Entrelacement des mots de correction
        for ($i = 0; $i < $motsEc; $i++) {
            foreach ($blocs as $bloc) {
                if (isset($bloc['ec'][$i])) {
                    $resultat[] = $bloc['ec'][$i];
                }
            }
        }

        return $resultat;
    }

    /** Codage Reed-Solomon sur GF(256). */
    private static function reedSolomon(array $donnees, int $nbEc): array
    {
        $generateur = self::polynomeGenerateur($nbEc);
        $reste = array_merge($donnees, array_fill(0, $nbEc, 0));

        for ($i = 0; $i < count($donnees); $i++) {
            $coefficient = $reste[$i];
            if ($coefficient !== 0) {
                foreach ($generateur as $j => $terme) {
                    $reste[$i + $j] ^= self::gfMul($terme, $coefficient);
                }
            }
        }

        return array_slice($reste, count($donnees), $nbEc);
    }

    private static function polynomeGenerateur(int $degre): array
    {
        $poly = [1];
        for ($i = 0; $i < $degre; $i++) {
            $nouveau = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $j => $coef) {
                $nouveau[$j]     ^= self::gfMul($coef, 1);
                $nouveau[$j + 1] ^= self::gfMul($coef, self::gfExp($i));
            }
            $poly = $nouveau;
        }

        return $poly;
    }

    private static function initTables(): void
    {
        if (self::$expTable !== null) {
            return;
        }

        self::$expTable = array_fill(0, 512, 0);
        self::$logTable = array_fill(0, 256, 0);

        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$expTable[$i] = $x;
            self::$logTable[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D; // polynôme primitif x^8 + x^4 + x^3 + x^2 + 1
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$expTable[$i] = self::$expTable[$i - 255];
        }
    }

    private static function gfExp(int $puissance): int
    {
        self::initTables();

        return self::$expTable[$puissance % 255];
    }

    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        self::initTables();

        return self::$expTable[(self::$logTable[$a] + self::$logTable[$b]) % 255];
    }

    // =======================================================================
    // Construction de la matrice
    // =======================================================================

    private static function poserMotifsFixes(array &$modules, array &$reserves, int $version, int $taille): void
    {
        // Motifs de repérage (finder) et séparateurs
        foreach ([[0, 0], [$taille - 7, 0], [0, $taille - 7]] as [$ligne, $col]) {
            for ($i = -1; $i <= 7; $i++) {
                for ($j = -1; $j <= 7; $j++) {
                    $r = $ligne + $i;
                    $c = $col + $j;
                    if ($r < 0 || $r >= $taille || $c < 0 || $c >= $taille) {
                        continue;
                    }
                    $dedans = ($i >= 0 && $i <= 6 && $j >= 0 && $j <= 6);
                    $sombre = $dedans && (
                        $i === 0 || $i === 6 || $j === 0 || $j === 6 ||
                        ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4)
                    );
                    $modules[$r][$c]  = $sombre;
                    $reserves[$r][$c] = true;
                }
            }
        }

        // Motifs de synchronisation
        for ($i = 8; $i < $taille - 8; $i++) {
            $sombre = ($i % 2 === 0);
            $modules[6][$i] = $sombre;
            $reserves[6][$i] = true;
            $modules[$i][6] = $sombre;
            $reserves[$i][6] = true;
        }

        // Motifs d’alignement
        foreach (self::ALIGNEMENT[$version] as $ligne) {
            foreach (self::ALIGNEMENT[$version] as $col) {
                // Ignore ceux qui chevauchent les motifs de repérage
                if (($ligne === 6 && $col === 6)
                    || ($ligne === 6 && $col === $taille - 7)
                    || ($ligne === $taille - 7 && $col === 6)) {
                    continue;
                }
                for ($i = -2; $i <= 2; $i++) {
                    for ($j = -2; $j <= 2; $j++) {
                        $sombre = (max(abs($i), abs($j)) !== 1);
                        $modules[$ligne + $i][$col + $j]  = $sombre;
                        $reserves[$ligne + $i][$col + $j] = true;
                    }
                }
            }
        }

        // Module sombre
        $modules[$taille - 8][8]  = true;
        $reserves[$taille - 8][8] = true;

        // Réservation des informations de format
        for ($i = 0; $i < 9; $i++) {
            $reserves[8][$i] = true;
            $reserves[$i][8] = true;
        }
        for ($i = 0; $i < 8; $i++) {
            $reserves[8][$taille - 1 - $i] = true;
            $reserves[$taille - 1 - $i][8] = true;
        }

        // Réservation des informations de version
        if ($version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $reserves[$taille - 11 + $j][$i] = true;
                    $reserves[$i][$taille - 11 + $j] = true;
                }
            }
        }
    }

    private static function poserDonnees(array &$modules, array $reserves, array $octets, int $taille): void
    {
        $bits = '';
        foreach ($octets as $octet) {
            $bits .= str_pad(decbin($octet), 8, '0', STR_PAD_LEFT);
        }

        $index = 0;
        $montee = true;

        for ($col = $taille - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col = 5; // saute la colonne de synchronisation
            }
            for ($compteur = 0; $compteur < $taille; $compteur++) {
                $ligne = $montee ? ($taille - 1 - $compteur) : $compteur;
                for ($k = 0; $k < 2; $k++) {
                    $c = $col - $k;
                    if ($reserves[$ligne][$c]) {
                        continue;
                    }
                    $modules[$ligne][$c] = ($index < strlen($bits)) && ($bits[$index] === '1');
                    $index++;
                }
            }
            $montee = !$montee;
        }
    }

    private static function appliquerMasque(array $modules, array $reserves, int $masque, int $taille): array
    {
        for ($i = 0; $i < $taille; $i++) {
            for ($j = 0; $j < $taille; $j++) {
                if ($reserves[$i][$j]) {
                    continue;
                }
                if (self::masqueActif($masque, $i, $j)) {
                    $modules[$i][$j] = !$modules[$i][$j];
                }
            }
        }

        return $modules;
    }

    private static function masqueActif(int $masque, int $i, int $j): bool
    {
        return match ($masque) {
            0 => ($i + $j) % 2 === 0,
            1 => $i % 2 === 0,
            2 => $j % 3 === 0,
            3 => ($i + $j) % 3 === 0,
            4 => (intdiv($i, 2) + intdiv($j, 3)) % 2 === 0,
            5 => (($i * $j) % 2) + (($i * $j) % 3) === 0,
            6 => ((($i * $j) % 2) + (($i * $j) % 3)) % 2 === 0,
            7 => ((($i + $j) % 2) + (($i * $j) % 3)) % 2 === 0,
            default => false,
        };
    }

    private static function poserInfosFormat(array &$modules, string $niveau, int $masque, int $taille): void
    {
        $donnee = (self::NIVEAUX[$niveau] << 3) | $masque;
        $bits   = self::bch15($donnee) ^ 0x5412;

        for ($i = 0; $i < 15; $i++) {
            $bit = (($bits >> $i) & 1) === 1;

            // Copie verticale près du motif de repérage supérieur gauche
            if ($i < 6) {
                $modules[$i][8] = $bit;
            } elseif ($i < 8) {
                $modules[$i + 1][8] = $bit;
            } else {
                $modules[$taille - 15 + $i][8] = $bit;
            }

            // Copie horizontale
            if ($i < 8) {
                $modules[8][$taille - 1 - $i] = $bit;
            } elseif ($i < 9) {
                $modules[8][15 - $i - 1 + 1] = $bit;
            } else {
                $modules[8][15 - $i - 1] = $bit;
            }
        }

        // Module sombre fixe
        $modules[$taille - 8][8] = true;
    }

    private static function poserInfosVersion(array &$modules, int $version, int $taille): void
    {
        $bits = self::INFO_VERSION[$version];

        for ($i = 0; $i < 18; $i++) {
            $bit = (($bits >> $i) & 1) === 1;
            $modules[intdiv($i, 3)][$taille - 11 + ($i % 3)] = $bit;
            $modules[$taille - 11 + ($i % 3)][intdiv($i, 3)] = $bit;
        }
    }

    /** Code BCH(15,5) de l’information de format. */
    private static function bch15(int $donnee): int
    {
        $valeur = $donnee << 10;
        for ($i = 4; $i >= 0; $i--) {
            if (($valeur >> (10 + $i)) & 1) {
                $valeur ^= 0x537 << $i;
            }
        }

        return (($donnee << 10) | $valeur) & 0x7FFF;
    }

    // =======================================================================
    // Évaluation du masque
    // =======================================================================

    private static function penalite(array $modules, int $taille): int
    {
        $score = 0;

        // Règle 1 : séries de 5 modules ou plus de même couleur
        for ($i = 0; $i < $taille; $i++) {
            $score += self::penaliteLigne(array_column($modules, $i));
            $score += self::penaliteLigne($modules[$i]);
        }

        // Règle 2 : blocs 2×2 de même couleur
        for ($i = 0; $i < $taille - 1; $i++) {
            for ($j = 0; $j < $taille - 1; $j++) {
                $v = $modules[$i][$j];
                if ($v === $modules[$i][$j + 1] && $v === $modules[$i + 1][$j] && $v === $modules[$i + 1][$j + 1]) {
                    $score += 3;
                }
            }
        }

        // Règle 3 : motif 1:1:3:1:1 précédé ou suivi de 4 modules clairs
        $motif1 = [true, false, true, true, true, false, true, false, false, false, false];
        $motif2 = [false, false, false, false, true, false, true, true, true, false, true];

        for ($i = 0; $i < $taille; $i++) {
            $ligne = $modules[$i];
            $colonne = array_column($modules, $i);
            for ($j = 0; $j <= $taille - 11; $j++) {
                if (array_slice($ligne, $j, 11) === $motif1 || array_slice($ligne, $j, 11) === $motif2) {
                    $score += 40;
                }
                if (array_slice($colonne, $j, 11) === $motif1 || array_slice($colonne, $j, 11) === $motif2) {
                    $score += 40;
                }
            }
        }

        // Règle 4 : proportion de modules sombres
        $sombres = 0;
        foreach ($modules as $ligne) {
            foreach ($ligne as $module) {
                if ($module) {
                    $sombres++;
                }
            }
        }
        $pourcentage = ($sombres * 100) / ($taille * $taille);
        $ecart = abs($pourcentage - 50);
        $score += (int) floor($ecart / 5) * 10;

        return $score;
    }

    private static function penaliteLigne(array $ligne): int
    {
        $score = 0;
        $taille = count($ligne);
        $i = 0;

        while ($i < $taille) {
            $courant = $ligne[$i];
            $longueur = 1;
            while ($i + $longueur < $taille && $ligne[$i + $longueur] === $courant) {
                $longueur++;
            }
            if ($longueur >= 5) {
                $score += 3 + ($longueur - 5);
            }
            $i += $longueur;
        }

        return $score;
    }
}
