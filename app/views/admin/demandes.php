<?php
/**
 * Vue — Liste des demandes de proposition (prospects).
 *
 * Variables :
 *   $demandes array
 *   $totaux   array<string,int> — nombre de demandes par statut
 *   $filtre   string           — statut filtré éventuel
 */

declare(strict_types=1);

use Souriane\Core\Icone;

$totaux = $totaux ?? [];
$filtre = $filtre ?? '';

/** Construit l’URL d’un filtre de statut. */
function filtreDemande(string $statut): string
{
    return url('admin/demandes' . ($statut !== '' ? '?statut=' . rawurlencode($statut) : ''));
}
?>
<div class="panneau">
    <div class="panneau__entete">
        <h2 class="panneau__titre"><?= Icone::svg('courriel') ?> Demandes de proposition</h2>
        <a class="btn btn--contour btn--petit" href="<?= e(url('annonceurs/demande')) ?>" target="_blank" rel="noopener">
            <?= Icone::svg('lien-externe') ?> Voir le formulaire public
        </a>
    </div>
    <div class="panneau__corps">
        <nav class="filtres" aria-label="Filtrer par statut">
            <a class="filtre<?= $filtre === '' ? ' actif' : '' ?>" href="<?= e(filtreDemande('')) ?>">
                Toutes <span class="filtre__compte"><?= e((string) array_sum($totaux)) ?></span>
            </a>
            <?php foreach (['nouveau', 'contacte', 'converti', 'archive'] as $statut): ?>
                <a class="filtre<?= $filtre === $statut ? ' actif' : '' ?>" href="<?= e(filtreDemande($statut)) ?>">
                    <?= e(ETATS_LIBELLES[$statut] ?? $statut) ?>
                    <span class="filtre__compte"><?= e((string) ($totaux[$statut] ?? 0)) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($demandes === []): ?>
            <div class="etat-vide">
                <?= Icone::svg('courriel') ?>
                <h3>Aucune demande</h3>
                <p>Les demandes déposées depuis le site public apparaîtront ici.</p>
            </div>
        <?php else: ?>
            <div class="tableau-enveloppe">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>Enseigne</th>
                            <th>Contact</th>
                            <th>Ville</th>
                            <th>Formats</th>
                            <th>Reçue le</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($demandes as $demande): ?>
                            <tr>
                                <td>
                                    <a class="tableau__principal" href="<?= e(url('admin/demandes/' . $demande['id'])) ?>">
                                        <?= e((string) $demande['enseigne']) ?>
                                    </a>
                                </td>
                                <td class="texte-petit">
                                    <?= e((string) $demande['contact']) ?><br>
                                    <span class="monospace"><?= e((string) $demande['email']) ?></span>
                                </td>
                                <td><?= e((string) $demande['ville']) ?></td>
                                <td class="texte-petit">
                                    <?php
                                    $formats = json_lire($demande['formats'] ?? null);
                                    echo $formats !== [] ? e(implode(', ', $formats)) : '—';
                                    ?>
                                </td>
                                <td class="texte-petit"><?= e(date_fr((string) $demande['cree_le'])) ?></td>
                                <td><?= etiquette_etat((string) $demande['statut']) ?></td>
                                <td class="tableau__action">
                                    <a class="btn btn--primaire btn--petit" href="<?= e(url('admin/demandes/' . $demande['id'])) ?>">
                                        <?= Icone::svg('oeil') ?> Ouvrir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
