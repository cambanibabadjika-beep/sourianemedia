<?php
/**
 * Vue — Liste des notifications.
 *
 * Variables : $notifications, $statut, $plafond
 */

declare(strict_types=1);

use Souriane\Core\Icone;

$compteParStatut = [];
foreach ($notifications as $notification) {
    $compteParStatut[$notification['statut']] = ($compteParStatut[$notification['statut']] ?? 0) + 1;
}
?>

<div class="encart">
    <strong>Plafond de fréquence.</strong> Un annonceur ne peut pas envoyer plus de
    <?= e((string) $plafond) ?> notifications par mois, tous supports confondus. La limite est contrôlée
    à la programmation puis de nouveau au moment de l’envoi.
</div>

<div class="panneau">
    <form class="filtres" method="get" action="<?= e(url('admin/notifications')) ?>">
        <div class="champ">
            <label class="champ__etiquette" for="statut">Statut</label>
            <select class="liste" id="statut" name="statut" onchange="this.form.submit()">
                <option value="">Toutes</option>
                <?php foreach (\Souriane\Models\Notification::STATUTS as $valeur): ?>
                    <option value="<?= e($valeur) ?>"<?= $statut === $valeur ? ' selected' : '' ?>>
                        <?= e(ETATS_LIBELLES[$valeur] ?? $valeur) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button type="submit" class="btn btn--primaire btn--petit">Filtrer</button></noscript>
    </form>

    <?php if ($notifications === []): ?>
        <div class="panneau__corps">
            <div class="etat-vide">
                <?= Icone::svg('cloche') ?>
                <h3>Aucune notification</h3>
                <p>Rédigez un message pour tenir les abonnés informés des prochaines offres.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="tableau-enveloppe">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Message</th>
                        <th>Annonceur</th>
                        <th>Programmée</th>
                        <th class="tableau__numerique">Envoyés</th>
                        <th class="tableau__numerique">Délivrés</th>
                        <th class="tableau__numerique">Clics</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $notification): ?>
                        <tr>
                            <td>
                                <a class="tableau__principal" href="<?= e(url('admin/notifications/' . $notification['id'])) ?>">
                                    <?= e((string) $notification['titre']) ?>
                                </a>
                                <div class="tableau__secondaire"><?= e(extrait((string) $notification['corps'], 70)) ?></div>
                            </td>
                            <td><?= e((string) $notification['annonceur_nom']) ?></td>
                            <td class="texte-petit">
                                <?= $notification['programmee_le'] !== null
                                    ? e(date_fr((string) $notification['programmee_le'], true))
                                    : '—' ?>
                            </td>
                            <td class="tableau__numerique"><?= e((string) $notification['nb_envois']) ?></td>
                            <td class="tableau__numerique"><?= e((string) $notification['nb_delivres']) ?></td>
                            <td class="tableau__numerique"><?= e((string) $notification['nb_clics']) ?></td>
                            <td><?= etiquette_etat((string) $notification['statut']) ?></td>
                            <td>
                                <div class="tableau__actions">
                                    <a class="btn btn--contour btn--petit" href="<?= e(url('admin/notifications/' . $notification['id'])) ?>">
                                        <?= Icone::svg('suivant') ?>
                                        <span class="sr-only">Ouvrir</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
