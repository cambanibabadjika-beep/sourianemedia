<?php
/**
 * SOURIANE — Jeu d’icônes SVG intégré
 *
 * Aucune dépendance externe : les icônes sont embarquées et héritent de la
 * couleur du texte (currentColor). Trace 24×24, contour de 1,8 px.
 */

declare(strict_types=1);

namespace Souriane\Core;

final class Icone
{
    private const TRACES = [
        'telephone'   => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>',
        'whatsapp'    => '<path d="M20.5 3.5A11.8 11.8 0 0 0 3.2 17.6L2 22l4.5-1.2A11.8 11.8 0 1 0 20.5 3.5Zm-8.4 18.2a9.8 9.8 0 0 1-5-1.4l-.4-.2-3 .8.8-2.9-.3-.4a9.8 9.8 0 1 1 7.9 4.1Zm5.4-7.3c-.3-.2-1.8-.9-2-1s-.5-.2-.7.1l-.9 1.2c-.2.2-.4.3-.7.1a8 8 0 0 1-2.3-1.4 8.7 8.7 0 0 1-1.6-2c-.2-.3 0-.5.1-.7l.5-.5.3-.5v-.6l-.9-2.1c-.2-.5-.5-.5-.7-.5h-.6a1.2 1.2 0 0 0-.9.4 3.6 3.6 0 0 0-1.1 2.6 6.2 6.2 0 0 0 1.3 3.3 13.9 13.9 0 0 0 5.5 4.9c2.3 1 2.8.8 3.3.7a3.2 3.2 0 0 0 2.1-1.5 2.6 2.6 0 0 0 .2-1.5c-.1-.2-.3-.3-.6-.4Z"/>',
        'carte'       => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'horloge'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'partage'     => '<path d="M12 3v12"/><path d="M8 7l4-4 4 4"/><path d="M5 13v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6"/>',
        'cloche'      => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 8-3 8h18s-3-1-3-8Z"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'telecharger' => '<path d="M12 3v12"/><path d="M8 11l4 4 4-4"/><path d="M5 19h14"/>',
        'calendrier'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        'utilisateurs'=> '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
        'panneau'     => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M12 16v5M8 21h8"/>',
        'qr'          => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v3M17 20h4M14 20h.01"/>',
        'graphique'   => '<path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/>',
        'reglages'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 9 19.4a1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
        'deconnexion' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'ajouter'     => '<path d="M12 5v14M5 12h14"/>',
        'modifier'    => '<path d="M17 3a2.8 2.8 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
        'supprimer'   => '<path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
        'valider'     => '<path d="M20 6L9 17l-5-5"/>',
        'fermer'      => '<path d="M18 6L6 18M6 6l12 12"/>',
        'alerte'      => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
        'info'        => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
        'suivant'     => '<path d="M9 18l6-6-6-6"/>',
        'menu'        => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'hors-ligne'  => '<path d="M1 1l22 22"/><path d="M16.7 13.7A5 5 0 0 0 19 9.5c0-.6-.1-1.1-.3-1.6"/><path d="M5 12.5a5 5 0 0 1 3-4.6"/><path d="M10.7 5.1A11 11 0 0 1 12 5c4.5 0 8.3 2.7 10 6.6"/><path d="M2 11.6a15 15 0 0 1 4-3.5"/><path d="M8.5 15.5a4 4 0 0 1 7 0"/><path d="M12 19h.01"/>',
        'lien-externe'=> '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14L21 3"/>',
        'facebook'    => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3Z"/>',
        'instagram'   => '<rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>',
        'tiktok'      => '<path d="M16 3v9.5a4.5 4.5 0 1 1-4-4.5"/><path d="M16 3c.4 2.4 2.2 4.2 4.5 4.5"/>',
        'youtube'     => '<path d="M22 8.5a3 3 0 0 0-2.1-2.1C18.1 6 12 6 12 6s-6.1 0-7.9.4A3 3 0 0 0 2 8.5 31 31 0 0 0 2 12a31 31 0 0 0 .1 3.5 3 3 0 0 0 2.1 2.1C6 18 12 18 12 18s6.1 0 7.9-.4a3 3 0 0 0 2.1-2.1A31 31 0 0 0 22 12a31 31 0 0 0-.1-3.5Z"/><path d="M10 15V9l5 3Z"/>',
        'site'        => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/>',
        'recherche'   => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
        'filtre'      => '<path d="M3 5h18l-7 8v6l-4 2v-8Z"/>',
        'fleche'      => '<path d="M5 12h14"/><path d="M13 6l6 6-6 6"/>',
        'telephone-mobile' => '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/>',
        'verrou'      => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'courriel'    => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 6 10-6"/>',
        'oeil'        => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'etincelle'   => '<path d="M12 3l2 5.5 5.5 2-5.5 2L12 18l-2-5.5-5.5-2 5.5-2Z"/><path d="M19 3v3M20.5 4.5h-3"/>',
        'itineraire'  => '<path d="M9 20l-5 2V6l5-2 6 2 5-2v16l-5 2-6-2Z"/><path d="M9 4v16M15 6v16"/>',
        'photo'       => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
        'envoyer'     => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4Z"/>',
        'archive'     => '<rect x="2" y="4" width="20" height="5" rx="1"/><path d="M4 9v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9"/><path d="M10 13h4"/>',
        'coche-cercle'=> '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>',
        'point'       => '<circle cx="12" cy="12" r="4"/>',
        'telephone-appel' => '<path d="M15.5 2h3a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1h-3"/><path d="M8 12h8"/><path d="M12 8l-4 4 4 4"/>',
        /* Bascules de thème */
        'soleil' => '<circle cx="12" cy="12" r="4.2"/><path d="M12 2.4v2.3M12 19.3v2.3M2.4 12h2.3M19.3 12h2.3M4.9 4.9l1.6 1.6M17.5 17.5l1.6 1.6M4.9 19.1l1.6-1.6M17.5 6.5l1.6-1.6"/>',
        'lune'   => '<path d="M20.5 14.5A8.6 8.6 0 0 1 9.5 3.5a8.4 8.4 0 1 0 11 11Z"/>',
    ];

    /** Retourne le SVG d’une icône. */
    public static function svg(string $nom, string $classe = '', int $taille = 0): string
    {
        $trace = self::TRACES[$nom] ?? self::TRACES['info'];

        // Les icônes de marque sont pleines, les autres sont des contours
        $pleines = ['whatsapp', 'facebook', 'youtube', 'etincelle'];
        $attributs = in_array($nom, $pleines, true)
            ? 'fill="currentColor" stroke="none"'
            : 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"';

        $dim = $taille > 0 ? sprintf(' width="%1$d" height="%1$d"', $taille, $taille) : '';
        $cls = $classe !== '' ? ' class="' . e($classe) . '"' : '';

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"%s%s %s>%s</svg>',
            $dim,
            $cls,
            $attributs,
            $trace
        );
    }

    /** Icône de réseau social à partir de son nom. */
    public static function reseau(string $nom): string
    {
        $connus = ['facebook', 'instagram', 'tiktok', 'youtube'];

        return self::svg(in_array($nom, $connus, true) ? $nom : 'site');
    }

    public static function existe(string $nom): bool
    {
        return isset(self::TRACES[$nom]);
    }
}
