<?php
/**
 * Partial — Initialisation du thème (anti-clignotement).
 *
 * À placer dans le <head>, avant le premier paint. Lit la préférence
 * enregistrée (localStorage) ou, à défaut, la préférence du système
 * (prefers-color-scheme), et pose data-theme sur <html> immédiatement.
 * Évite un flash de thème clair au chargement.
 */
declare(strict_types=1);
?>
<meta name="color-scheme" content="light dark">
<script>
(function () {
    try {
        var t = localStorage.getItem('souriane-theme');
        if (t !== 'light' && t !== 'dark') {
            t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', t);
    } catch (e) {}
})();
</script>
