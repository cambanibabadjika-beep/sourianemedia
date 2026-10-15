<?php
/**
 * Vue — Rédaction et suivi d’une notification.
 *
 * Variables :
 *   $notification ?array
 *   $annonceurs   array
 *   $envois       ?array — {en_attente, envoye, delivre, clique, echec, expire, total}
 *   $journalEnvois array
 *   $plafond      int
 */

declare(strict_types=1);

use Souriane\Core\Csrf;
use Souriane\Core\Icone;

$creation = $notification === null;
$action   = $creation ? url('admin/notifications/nouveau') : url('admin/notifications/' . $notification['id']);
$envoyee  = !$creation && ($notification['statut'] ?? '') === 'envoyee';
?>

<form method="post" action="<?= e($action) ?>">
    <?= Csrf::champ() ?>

    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('cloche') ?> Message</h2>
            <?php if (!$creation): ?>
                <?= etiquette_etat((string) $notification['statut']) ?>
            <?php endif; ?>
        </div>
        <div class="panneau__corps">
            <div class="grille-champs">
                <div class="champ">
                    <label class="champ__etiquette" for="annonceur_id">Annonceur <span class="requis">*</span></label>
                    <select class="liste" id="annonceur_id" name="annonceur_id" required<?= $envoyee ? ' disabled' : '' ?>>
                        <option value="">— Choisir —</option>
                        <?php foreach ($annonceurs as $annonceur): ?>
                            <option value="<?= e((string) $annonceur['id']) ?>"
                                <?= (int) ($notification['annonceur_id'] ?? 0) === (int) $annonceur['id'] ? ' selected' : '' ?>>
                                <?= e((string) $annonceur['nom']) ?> (<?= e((string) $annonceur['nb_abonnes']) ?> abonné(s))
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="statut">Statut</label>
                    <select class="liste" id="statut" name="statut"<?= $envoyee ? ' disabled' : '' ?>>
                        <option value="brouillon"<?= ($notification['statut'] ?? 'brouillon') === 'brouillon' ? ' selected' : '' ?>>
                            Brouillon — non programmé
                        </option>
                        <option value="programmee"<?= ($notification['statut'] ?? '') === 'programmee' ? ' selected' : '' ?>>
                            Programmée — envoi automatique
                        </option>
                    </select>
                </div>
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="titre">Titre <span class="requis">*</span></label>
                    <input class="saisie" type="text" id="titre" name="titre" required maxlength="120"
                           value="<?= e((string) ($notification['titre'] ?? '')) ?>"
                           placeholder="Nouvel arrivage d’outillage"<?= $envoyee ? ' readonly' : '' ?>>
                    <div class="champ__aide">120 caractères maximum. Visible sur l’écran verrouillé.</div>
                </div>
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="corps">Message <span class="requis">*</span></label>
                    <textarea class="zone-texte" id="corps" name="corps" required maxlength="400" rows="3"
                              placeholder="Perceuses, disqueuses et bétonnières viennent d’arriver. −20 % jusqu’au 30 novembre."<?= $envoyee ? ' readonly' : '' ?>><?= e((string) ($notification['corps'] ?? '')) ?></textarea>
                    <div class="champ__aide">400 caractères maximum. Allez droit au but : une offre, une action.</div>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="adresse_ouverture">Page ouverte au clic</label>
                    <input class="saisie" type="text" id="adresse_ouverture" name="adresse_ouverture" maxlength="255"
                           value="<?= e((string) ($notification['adresse_ouverture'] ?? '')) ?>"
                           placeholder="/quincaillerie-bignona"<?= $envoyee ? ' readonly' : '' ?>>
                    <div class="champ__aide">Chemin interne. Laissez vide pour ouvrir l’accueil.</div>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="programmee_le">Date et heure d’envoi</label>
                    <input class="saisie" type="datetime-local" id="programmee_le" name="programmee_le"
                           value="<?= e(($notification['programmee_le'] ?? null) !== null ? date('Y-m-d\TH:i', (int) strtotime((string) $notification['programmee_le'])) : '') ?>"
                           <?= $envoyee ? ' readonly' : '' ?>>
                    <div class="champ__aide">Nécessaire pour le statut « programmée ». La tâche planifiée s’en charge.</div>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="icone">Icône</label>
                    <input class="saisie" type="text" id="icone" name="icone" maxlength="255"
                           value="<?= e((string) ($notification['icone'] ?? '')) ?>"
                           placeholder="assets/img/icons/icone-192.png"<?= $envoyee ? ' readonly' : '' ?>>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$envoyee): ?>
        <div class="pile">
            <button type="submit" class="btn btn--primaire">
                <?= Icone::svg('valider') ?>
                <?= $creation ? 'Créer la notification' : 'Enregistrer' ?>
            </button>
            <a class="btn btn--contour" href="<?= e(url('admin/notifications')) ?>">Annuler</a>
        </div>
    <?php endif; ?>
</form>

<?php if ($creation) { return; } ?>

<!-- Envoi ------------------------------------------------------------------ -->
<div class="panneau ecart-haut--grand">
    <div class="panneau__entete">
        <h2 class="panneau__titre"><?= Icone::svg('envoyer') ?> Envoi</h2>
        <span class="texte-petit">
            <?= $notification['envoyee_le'] !== null
                ? 'Envoyée le ' . e(date_fr((string) $notification['envoyee_le'], true))
                : 'Pas encore envoyée' ?>
        </span>
    </div>
    <div class="panneau__corps">
        <?php if ($notification['statut'] === 'annulee'): ?>
            <div class="encart encart--avertissement" style="margin:0">
                Cette notification a été annulée. Elle ne peut plus être envoyée.
            </div>
        <?php elseif ($notification['statut'] === 'envoyee'): ?>
            <div class="encart" style="margin:0">
                Diffusion terminée. Les compteurs ci-dessous reflètent les accusés reçus du service d’envoi.
                Les abonnements signalés comme expirés ont été automatiquement désactivés.
            </div>
        <?php else: ?>
            <div class="pile">
                <form method="post" action="<?= e(url('admin/notifications/' . $notification['id'] . '/envoyer')) ?>" style="margin:0"
                      onsubmit="return confirm('Envoyer cette notification à tous les abonnés actifs de cet annonceur ?');">
                    <?= Csrf::champ() ?>
                    <button type="submit" class="btn btn--primaire">
                        <?= Icone::svg('envoyer') ?> Envoyer maintenant
                    </button>
                </form>

                <form method="post" action="<?= e(url('admin/notifications/' . $notification['id'] . '/annuler')) ?>" style="margin:0"
                      onsubmit="return confirm('Annuler définitivement cette notification ?');">
                    <?= Csrf::champ() ?>
                    <button type="submit" class="btn btn--contour">
                        <?= Icone::svg('fermer') ?> Annuler la notification
                    </button>
                </form>
            </div>

            <p class="champ__aide ecart-haut">
                Plafond de fréquence : <?= e((string) $plafond) ?> envois par mois et par annonceur.
                L’envoi immédiat n’est possible que si ce plafond n’est pas atteint.
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Statistiques d’envoi --------------------------------------------------- -->
<?php if ($envois !== null && (int) ($envois['total'] ?? 0) > 0): ?>
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('graphique') ?> Accusés d’envoi</h2>
            <?= etiquette_nature('mesure') ?>
        </div>
        <div class="panneau__corps">
            <div class="indicateurs" style="margin-bottom:0">
                <?php
                $cartes = [
                    ['etiquette' => 'En attente', 'cle' => 'en_attente', 'variante' => 'gris'],
                    ['etiquette' => 'Envoyés',    'cle' => 'envoye',    'variante' => ''],
                    ['etiquette' => 'Délivrés',   'cle' => 'delivre',   'variante' => 'lilas'],
                    ['etiquette' => 'Clics',      'cle' => 'clique',    'variante' => 'or'],
                ];
                ?>
                <?php foreach ($cartes as $carte): ?>
                    <div class="indicateur<?= $carte['variante'] !== '' ? ' indicateur--' . e($carte['variante']) : '' ?>">
                        <div class="indicateur__etiquette"><?= e($carte['etiquette']) ?></div>
                        <div class="indicateur__valeur"><?= e((string) ($envois[$carte['cle']] ?? 0)) ?></div>
                        <div class="indicateur__detail">
                            sur <?= e((string) $envois['total']) ?> envoi(s)
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ((int) ($envois['echec'] ?? 0) > 0 || (int) ($envois['expire'] ?? 0) > 0): ?>
                <div class="encart encart--avertissement ecart-haut">
                    <?= e((string) ($envois['echec'] ?? 0)) ?> échec(s) et
                    <?= e((string) ($envois['expire'] ?? 0)) ?> abonnement(s) expiré(s).
                    Les abonnements expirés ont été désactivés automatiquement : ils ne seront plus sollicités.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('archive') ?> Détail des envois</h2>
        </div>
        <div class="tableau-enveloppe">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Abonné</th>
                        <th>Fréquence</th>
                        <th>Statut</th>
                        <th>Date d’envoi</th>
                        <th>Erreur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($journalEnvois as $envoi): ?>
                        <tr>
                            <td class="monospace texte-petit"><?= e(extrait((string) $envoi['endpoint'], 46)) ?></td>
                            <td class="texte-petit">
                                <?= $envoi['frequence'] === 'hebdomadaire' ? 'Résumé hebdomadaire' : 'À chaque offre' ?>
                            </td>
                            <td><?= etiquette_etat((string) $envoi['statut']) ?></td>
                            <td class="texte-petit">
                                <?= $envoi['date_envoi'] !== null ? e(date_fr((string) $envoi['date_envoi'], true)) : '—' ?>
                            </td>
                            <td class="texte-petit"><?= e((string) ($envoi['erreur'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
