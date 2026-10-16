<?php
/**
 * SOURIANE — Helpers globaux
 * Fonctions utilitaires partagées par toute l’application.
 */

declare(strict_types=1);

/** Échappe une chaîne pour un affichage HTML sûr. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Construit une URL absolue à partir du chemin applicatif. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Construit l’URL courte d’un code (celle encodée dans le QR code). */
function url_courte(string $code): string
{
    return rtrim(BASE_URL, '/') . '/c/' . rawurlencode($code);
}

/** Chemin absolu vers un asset versionné. */
function asset(string $path): string
{
    $file = SOURIANE_ROOT . '/assets/' . ltrim($path, '/');
    $v    = is_file($file) ? (string) filemtime($file) : APP_VERSION;

    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/** Adresse complète d’une page publique d’annonceur. */
function url_page(string $adresseCourte): string
{
    return url($adresseCourte);
}

/** Formate un prix en francs CFA. */
function fcfa(float|int|string|null $montant): string
{
    return number_format((float) $montant, 0, ',', ' ') . ' FCFA';
}

/** Formate une date ISO ou MySQL en français court. */
function date_fr(?string $date, bool $avecHeure = false): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '—';
    }
    $mois = [1 => 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
             'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $texte = date('j', $ts) . ' ' . $mois[(int) date('n', $ts)] . ' ' . date('Y', $ts);

    return $avecHeure ? $texte . ' à ' . date('H:i', $ts) : $texte;
}

/** Normalise un numéro de téléphone pour les liens tel: et WhatsApp. */
function tel_lien(?string $numero): string
{
    $n = preg_replace('/[^0-9+]/', '', (string) $numero) ?? '';

    return $n === '' ? '' : $n;
}

/** Construit un lien WhatsApp avec message pré-rempli. */
function whatsapp_lien(?string $numero, string $message = ''): string
{
    $n = ltrim(tel_lien($numero), '+');
    if ($n === '') {
        return '';
    }
    $url = 'https://wa.me/' . $n;
    if ($message !== '') {
        $url .= '?text=' . rawurlencode($message);
    }

    return $url;
}

/** Génère un identifiant de session anonyme (UUID v4). */
function uuid4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/** Empreinte non réversible d’une adresse réseau (ch. 8). */
function empreinte_reseau(?string $ip): ?string
{
    if ($ip === null || $ip === '') {
        return null;
    }

    return substr(hash_hmac('sha256', $ip, HASH_SECRET), 0, 64);
}

/** Nettoie un slug d’URL. */
function slugify(string $texte): string
{
    $texte = trim($texte);
    if (function_exists('iconv')) {
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texte);
        if ($conv !== false) {
            $texte = $conv;
        }
    }
    $texte = strtolower($texte);
    $texte = preg_replace('/[^a-z0-9]+/', '-', $texte) ?? '';

    return trim($texte, '-');
}

/** Tronque proprement un texte. */
function extrait(?string $texte, int $longueur = 140): string
{
    $texte = trim((string) $texte);
    if (mb_strlen($texte) <= $longueur) {
        return $texte;
    }

    return rtrim(mb_substr($texte, 0, $longueur - 1)) . '…';
}

/** Enregistre une ligne dans le journal technique. */
function journal_technique(string $niveau, string $message, array $contexte = []): void
{
    if (!is_dir(LOG_PATH)) {
        @mkdir(LOG_PATH, 0775, true);
    }
    $ligne = sprintf(
        "[%s] %s : %s %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($niveau),
        $message,
        $contexte ? json_encode($contexte, JSON_UNESCAPED_UNICODE) : ''
    );
    @file_put_contents(LOG_PATH . '/app-' . date('Y-m-d') . '.log', $ligne, FILE_APPEND | LOCK_EX);
}

/** Journalise une erreur technique si le mode debug est actif. */
function debug_dump(mixed $valeur): void
{
    if (APP_DEBUG) {
        echo '<pre style="background:#1A1033;color:#FBBF24;padding:12px;border-radius:8px;overflow:auto">';
        echo e(print_r($valeur, true));
        echo '</pre>';
    }
}

/** Indique si l’agent utilisateur correspond à un iOS. */
function est_ios(?string $agent = null): bool
{
    $agent = $agent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');

    return (bool) preg_match('/iPad|iPhone|iPod/', $agent);
}

/** Lit une valeur JSON avec valeur par défaut. */
function json_lire(?string $json, array $defaut = []): array
{
    if ($json === null || $json === '') {
        return $defaut;
    }
    $data = json_decode($json, true);

    return is_array($data) ? $data : $defaut;
}

/**
 * Une offre dont la date de fin est dépassée.
 *
 * Les dates de validité d’une offre ne sont pas des colonnes : elles vivent
 * dans le JSON de la section « offre » ($offre['debut'], $offre['fin']), au
 * format Y-m-d produit par le champ `date` du formulaire. Le contrôle se fait
 * donc ici, en PHP, et non dans la requête SQL.
 *
 * Une date absente ou illisible ne périme rien : seule une échéance
 * franchement dépassée compte. Le début, lui, ne masque pas l’offre — la vue
 * annonce « À partir du … », c’est-à-dire une offre que l’on peut déjà voir.
 */
function offre_expiree(?array $offre, ?string $aujourdhui = null): bool
{
    $fin = trim((string) ($offre['fin'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin) !== 1) {
        return false;
    }

    return $fin < ($aujourdhui ?? date('Y-m-d'));
}

/** Enregistre une opération d’administration dans le journal (EF-G05). */
function journal_operation(
    ?int $utilisateurId,
    string $action,
    ?string $entite = null,
    ?int $entiteId = null,
    ?string $detail = null,
    ?string $ip = null
): void {
    try {
        \Souriane\Core\Database::instance()->insert('journal_operations', [
            'utilisateur_id' => $utilisateurId,
            'action'         => $action,
            'entite'         => $entite,
            'entite_id'      => $entiteId,
            'detail'         => $detail !== null ? mb_substr($detail, 0, 500) : null,
            'adresse_ip'     => $ip,
        ]);
    } catch (\Throwable $e) {
        journal_technique('erreur', 'Journalisation impossible', ['message' => $e->getMessage()]);
    }
}

/** Indique si le site est servi en HTTPS (condition des notifications). */
function est_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }

    return str_starts_with(BASE_URL, 'https://');
}

/** Prépare une valeur pour un attribut de données JSON. */
function json_attr(array $donnees): string
{
    return e(json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
}

/** Libellés français des états rencontrés dans l’application. */
const ETATS_LIBELLES = [
    // Campagnes
    'brouillon'    => 'Brouillon',
    'reservee'     => 'Réservée',
    'en_cours'     => 'En cours',
    'terminee'     => 'Terminée',
    'annulee'      => 'Annulée',
    // Panneaux
    'disponible'   => 'Disponible',
    'reserve'      => 'Réservé',
    'occupe'       => 'Occupé',
    'maintenance'  => 'Maintenance',
    'archive'      => 'Archivé',
    // Pages et annonceurs
    'publiee'      => 'Publiée',
    'depubliee'    => 'Dépubliée',
    'actif'        => 'Actif',
    'inactif'      => 'Inactif',
    // Demandes de proposition
    'nouveau'      => 'Nouveau',
    'contacte'     => 'Contacté',
    'converti'     => 'Converti',
    // Notifications et envois
    'programmee'   => 'Programmée',
    'envoyee'      => 'Envoyée',
    'en_attente'   => 'En attente',
    'envoye'       => 'Envoyé',
    'delivre'      => 'Délivré',
    'clique'       => 'Cliqué',
    'echec'        => 'Échec',
    'expire'       => 'Expiré',
    'desabonne'    => 'Désabonné',
    // Contrôles de pose
    'non_fait'     => 'À faire',
    'conforme'     => 'Conforme',
    'non_conforme' => 'Non conforme',
];

/** Étiquette colorée d’un état. */
function etiquette_etat(?string $etat, ?string $libelle = null): string
{
    $etat = (string) $etat;
    if ($etat === '') {
        return '<span class="etat etat--inactif">—</span>';
    }

    $texte = $libelle ?? (ETATS_LIBELLES[$etat] ?? ucfirst(str_replace('_', ' ', $etat)));

    return '<span class="etat etat--' . e($etat) . '">' . e($texte) . '</span>';
}

/** Nature d’une donnée : mesuré, estimé ou déclaré (EF-F07). */
function etiquette_nature(string $nature): string
{
    $libelles = ['mesure' => 'Mesuré', 'estime' => 'Estimé', 'declare' => 'Déclaré'];
    $nature   = isset($libelles[$nature]) ? $nature : 'declare';

    return '<span class="nature nature--' . e($nature) . '">' . e($libelles[$nature]) . '</span>';
}

/** Formate un pourcentage sans décimale inutile. */
function pourcent(float|int $valeur, int $decimales = 1): string
{
    return number_format((float) $valeur, $decimales, ',', ' ') . ' %';
}

/* ========================================================================
   Internationalisation — EF-D08
   ======================================================================== */

/** Langues disponibles, avec le français comme référence. */
function langues_disponibles(): array
{
    return ['fr' => 'Français', 'wo' => 'Wolof'];
}

/**
 * Charge le catalogue de la langue active.
 *
 * La langue est déterminée dans cet ordre :
 *   1. paramètre GET `?lang=` (si valide) — mémorisé en session
 *   2. clé de session `_langue`
 *   3. langue du navigateur (`Accept-Language`)
 *   4. français par défaut
 */
function catalogue_langue(): array
{
    static $catalogue = null;

    if ($catalogue !== null) {
        return $catalogue;
    }

    $disponibles = array_keys(langues_disponibles());

    // 1. Paramètre GET (et mémorisation)
    if (isset($_GET['lang']) && in_array($_GET['lang'], $disponibles, true)) {
        $_SESSION['_langue'] = $_GET['lang'];
    }

    // 2. Session
    if (isset($_SESSION['_langue']) && in_array($_SESSION['_langue'], $disponibles, true)) {
        $code = $_SESSION['_langue'];
    } else {
        // 3. Navigateur
        $code = 'fr';
        if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $pref = strtolower(substr((string) $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
            if (in_array($pref, $disponibles, true)) {
                $code = $pref;
            }
        }
        $_SESSION['_langue'] = $code;
    }

    $chemin = __DIR__ . '/lang/' . $code . '.php';
    $catalogue = file_exists($chemin) ? require $chemin : [];

    return $catalogue;
}

/**
 * Traduit une clé sémantique.
 *
 * @param string $cle    Clé du catalogue (ex. 'nav.accueil')
 * @param string $defaut Valeur de repli si la clé n'existe pas
 */
function __(string $cle, string $defaut = ''): string
{
    $catalogue = catalogue_langue();

    return $catalogue[$cle] ?? ($defaut !== '' ? $defaut : $cle);
}

/** Code de la langue active (fr, wo…). */
function langue_active(): string
{
    return $_SESSION['_langue'] ?? 'fr';
}
