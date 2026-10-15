<?php
/**
 * Vue — Journal des opérations d’administration (EF-G05).
 *
 * Variables : $entrees, $actions, $action
 */

declare(strict_types=1);

use Souriane\Core\Icone;

$libellesActions = [
    'connexion'          => 'Connexion',
    'deconnexion'        => 'Déconnexion',
    'creation'           => 'Création',
    'modification'       => 'Modification',
    'suppression'        => 'Suppression',
    'reservation'        => 'Réservation',
    'retrait'            => 'Retrait',
    'generation_codes'   => 'Génération de codes',
    'pose'               => 'Pose',
    'envoi_notification' => 'Envoi de notification',
    'annulation'         => 'Annulation',
];
?>

<div class="panneau">
    <form class="filtres" method="get" action="<?= e(url('admin/journal')) ?>">
        <div class="champ">
            <label class="champ__etiquette" for="action">Type d’action</label>
            <select class="liste" id="action" name="action" onchange="this.form.submit()">
                <option value="">Toutes les actions</option>
                <?php foreach ($actions as $valeur): ?>
                    <option value="<?= e((string) $valeur) ?>"<?= $action === (string) $valeur ? ' selected' : '' ?>>
                        <?= e($libellesActions[$valeur] ?? (string) $valeur) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button type="submit" class="btn btn--primaire btn--petit">Filtrer</button></noscript>
    </form>

    <?php if ($entrees === []): ?>
        <div class="panneau__corps">
            <div class="etat-vide">
                <?= Icone::svg('archive') ?>
                <h3>Journal vide</h3>
                <p>Les opérations d’administration apparaîtront ici.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="tableau-enveloppe">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Utilisateur</th>
                        <th>Action</th>
                        <th>Entité</th>
                        <th>Détail</th>
                        <th>Adresse</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entrees as $entree): ?>
                        <tr>
                            <td class="texte-petit"><?= e(date_fr((string) $entree['date'], true)) ?></td>
                            <td>
                                <?= e((string) ($entree['utilisateur_nom'] ?? 'Système')) ?>
                                <?php if (!empty($entree['role'])): ?>
                                    <div class="tableau__secondaire">
                                        <?= e(\Souriane\Models\Utilisateur::ROLES[$entree['role']] ?? (string) $entree['role']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="etat etat--<?= $entree['action'] === 'suppression' ? 'annulee' : 'en_cours' ?>">
                                    <?= e($libellesActions[$entree['action']] ?? (string) $entree['action']) ?>
                                </span>
                            </td>
                            <td class="texte-petit monospace">
                                <?= e((string) ($entree['entite'] ?? '—')) ?>
                                <?= $entree['entite_id'] !== null ? '#' . e((string) $entree['entite_id']) : '' ?>
                            </td>
                            <td class="texte-petit"><?= e((string) ($entree['detail'] ?? '—')) ?></td>
                            <td class="texte-petit monospace"><?= e((string) ($entree['adresse_ip'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="encart">
    <strong>Traçabilité.</strong> Chaque opération d’administration est enregistrée avec son auteur, son horodatage
    et son objet. Le journal est en lecture seule : aucune entrée ne peut être modifiée ni supprimée depuis
    l’interface.
</div>
