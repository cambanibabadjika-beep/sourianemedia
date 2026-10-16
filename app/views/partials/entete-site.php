<?php
/**
 * Partial — En-tête du site public.
 *
 * Variable facultative :
 *   $navigation array<{libelle, url}> — par défaut la navigation de la vitrine
 */

declare(strict_types=1);

$navigation = $navigation ?? [
    ['libelle' => 'Comment ça marche', 'url' => url('comment-ca-marche')],
    ['libelle' => 'Pour les annonceurs', 'url' => url('annonceurs')],
    ['libelle' => 'Le parc', 'url' => url('le-parc')],
];
?>
<header class="entete-site">
    <div class="conteneur entete-site__interieur">
        <?php require __DIR__ . '/logo.php'; ?>

        <nav class="entete-site__nav" aria-label="Navigation principale">
            <?php foreach ($navigation as $item): ?>
                <a href="<?= e($item['url']) ?>"><?= e($item['libelle']) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="pile">
            <a class="btn btn--primaire btn--petit" href="<?= e(url('admin/connexion')) ?>">
                <?= \Souriane\Core\Icone::svg('verrou') ?>
                Espace régie
            </a>
            <?php require __DIR__ . '/bascule-theme.php'; ?>
        </div>
    </div>
</header>
