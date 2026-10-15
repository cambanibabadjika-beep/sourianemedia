<?php
/**
 * Vue — Accès refusé au back-office.
 */

declare(strict_types=1);

use Souriane\Core\Auth;
use Souriane\Core\Icone;

$utilisateur = Auth::utilisateur();
?>

<div class="panneau">
    <div class="panneau__corps" style="text-align:center;padding:52px 24px">
        <div style="color:var(--alerte);margin-bottom:14px"><?= Icone::svg('verrou', '', 44) ?></div>
        <h2 style="margin-bottom:.4em">Accès refusé</h2>
        <p style="color:var(--gris-600);max-width:48ch;margin:0 auto 24px">
            Votre rôle ne vous permet pas d’ouvrir cette rubrique.
            <?php if ($utilisateur !== null): ?>
                Vous êtes connecté en tant que <strong><?= e((string) $utilisateur['nom']) ?></strong>
                (<?= e(\Souriane\Models\Utilisateur::ROLES[$utilisateur['role']] ?? $utilisateur['role']) ?>).
            <?php endif; ?>
        </p>
        <a class="btn btn--primaire" href="<?= e(url('admin')) ?>">
            <?= Icone::svg('fleche') ?>
            Revenir au tableau de bord
        </a>
    </div>
</div>
