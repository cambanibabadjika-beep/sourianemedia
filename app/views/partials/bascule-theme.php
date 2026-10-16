<?php
/**
 * Partial — Bouton de bascule du thème clair / sombre.
 *
 * Affiche l’icône de la cible (lune pour passer en sombre, soleil pour
 * repasser en clair) ; la visibilité est gérée en CSS selon data-theme,
 * donc aucun flash au chargement. La préférence est persistée dans
 * localStorage par le script ci-dessous.
 */
declare(strict_types=1);

use Souriane\Core\Icone;
?>
<button type="button" class="btn btn--contour btn--petit bascule-theme" data-theme-toggle
        aria-label="Basculer entre le thème clair et sombre" title="Thème clair / sombre">
    <span class="bascule-theme__icone bascule-theme__icone--lune" aria-hidden="true"><?= Icone::svg('lune') ?></span>
    <span class="bascule-theme__icone bascule-theme__icone--soleil" aria-hidden="true"><?= Icone::svg('soleil') ?></span>
</button>
<script>
(function () {
    function themeCourant() {
        var t = document.documentElement.getAttribute('data-theme');
        if (t === 'light' || t === 'dark') { return t; }
        return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
    }
    function basculer() {
        var prochain = themeCourant() === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', prochain);
        try { localStorage.setItem('souriane-theme', prochain); } catch (e) {}
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) { meta.setAttribute('content', prochain === 'dark' ? '#0F0B1E' : '#4C1D95'); }
    }
    function installer() {
        var btn = document.querySelector('[data-theme-toggle]');
        if (btn && !btn.dataset.themeInstalle) {
            btn.dataset.themeInstalle = '1';
            btn.addEventListener('click', basculer);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', installer);
    } else { installer(); }
})();
</script>
