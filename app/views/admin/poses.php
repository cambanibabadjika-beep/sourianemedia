<?php
/**
 * Vue — Preuves de pose et contrôles de conformité (EF-B06).
 *
 * Variables : $poses, $controle, $campagnes
 */

declare(strict_types=1);

use Souriane\Core\Csrf;
use Souriane\Core\Icone;

?>

<div class="panneau">
    <form class="filtres" method="get" action="<?= e(url('admin/poses')) ?>">
        <div class="champ">
            <label class="champ__etiquette" for="controle">Contrôle</label>
            <select class="liste" id="controle" name="controle" onchange="this.form.submit()">
                <option value="">Toutes les poses</option>
                <?php foreach (['non_fait' => 'Contrôle à faire', 'conforme' => 'Conforme', 'non_conforme' => 'Non conforme'] as $cle => $libelle): ?>
                    <option value="<?= e($cle) ?>"<?= $controle === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button type="submit" class="btn btn--primaire btn--petit">Filtrer</button></noscript>
    </form>

    <?php if ($poses === []): ?>
        <div class="panneau__corps">
            <div class="etat-vide">
                <?= Icone::svg('photo') ?>
                <h3>Aucune preuve de pose</h3>
                <p>Chaque pose d’affiche doit être photographiée et horodatée.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="tableau-enveloppe">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Panneau</th>
                        <th>Campagne</th>
                        <th>Annonceur</th>
                        <th>Pose</th>
                        <th>Agent</th>
                        <th>Coordonnées</th>
                        <th>Contrôle 15 j</th>
                        <th>Contrôle 30 j</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($poses as $pose): ?>
                        <tr>
                            <td class="tableau__principal">
                                <a href="<?= e(url('admin/panneaux/' . $pose['panneau_id'])) ?>"><?= e((string) $pose['reference']) ?></a>
                                <div class="tableau__secondaire"><?= e((string) $pose['libelle']) ?></div>
                            </td>
                            <td><?= e((string) $pose['campagne_nom']) ?></td>
                            <td><?= e((string) $pose['annonceur_nom']) ?></td>
                            <td class="texte-petit"><?= e(date_fr((string) $pose['horodatage'], true)) ?></td>
                            <td><?= e((string) ($pose['agent_nom'] ?? '—')) ?></td>
                            <td class="texte-petit monospace">
                                <?php if (!empty($pose['latitude']) && !empty($pose['longitude'])): ?>
                                    <?= e(number_format((float) $pose['latitude'], 5, '.', '')) ?>,
                                    <?= e(number_format((float) $pose['longitude'], 5, '.', '')) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= etiquette_etat((string) $pose['controle_15j']) ?></td>
                            <td><?= etiquette_etat((string) $pose['controle_30j']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="encart">
    <strong>Qualité de pose.</strong> Le contrôle à quinze jours vérifie la présence et l’état du visuel ;
    le contrôle à trente jours vérifie la lisibilité du QR code dans les conditions réelles (ensoleillement,
    reflets, salissure). Une non-conformité déclenche une repose ou un nettoyage.
</div>

<!-- Dialogue d’enregistrement d’une pose ------------------------------------ -->
<dialog id="dialogue-pose" style="border:0;border-radius:var(--rayon);padding:0;max-width:520px;width:calc(100% - 32px)">
    <form method="post" action="<?= e(url('admin/poses')) ?>" style="padding:24px">
        <?= Csrf::champ() ?>
        <h2 class="panneau__titre" style="margin-bottom:18px"><?= Icone::svg('photo') ?> Enregistrer une pose</h2>

        <div class="champ">
            <label class="champ__etiquette" for="pose_campagne">Campagne <span class="requis">*</span></label>
            <select class="liste" id="pose_campagne" name="campagne_id" required>
                <option value="">— Choisir —</option>
                <?php foreach ($campagnes as $campagne): ?>
                    <option value="<?= e((string) $campagne['id']) ?>"><?= e((string) $campagne['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="champ">
            <label class="champ__etiquette" for="pose_panneau">Panneau <span class="requis">*</span></label>
            <input class="saisie" type="number" id="pose_panneau" name="panneau_id" required min="1" placeholder="Identifiant du panneau">
            <div class="champ__aide">L’identifiant se lit dans l’adresse de la fiche panneau.</div>
        </div>

        <div class="champ">
            <label class="champ__etiquette" for="pose_photo">Chemin de la photo <span class="requis">*</span></label>
            <input class="saisie" type="text" id="pose_photo" name="photo" required
                   placeholder="storage/uploads/pose-2026-10-15.jpg">
        </div>

        <div class="grille-champs">
            <div class="champ">
                <label class="champ__etiquette" for="pose_latitude">Latitude</label>
                <input class="saisie" type="text" id="pose_latitude" name="latitude" inputmode="decimal">
            </div>
            <div class="champ">
                <label class="champ__etiquette" for="pose_longitude">Longitude</label>
                <input class="saisie" type="text" id="pose_longitude" name="longitude" inputmode="decimal">
            </div>
        </div>

        <div class="champ">
            <label class="champ__etiquette" for="pose_note">Note de terrain</label>
            <textarea class="zone-texte" id="pose_note" name="note" rows="2"
                      placeholder="Pose conforme, QR testé à 5 m en plein soleil."></textarea>
        </div>

        <div class="pile pile--droite">
            <button type="button" class="btn btn--contour" data-fermer-dialogue>Annuler</button>
            <button type="submit" class="btn btn--primaire"><?= Icone::svg('valider') ?> Enregistrer</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    var ouvreurs = document.querySelectorAll('[data-ouvrir-dialogue]');
    for (var i = 0; i < ouvreurs.length; i++) {
        ouvreurs[i].addEventListener('click', function () {
            var cible = document.getElementById(this.getAttribute('data-ouvrir-dialogue'));
            if (cible && cible.showModal) { cible.showModal(); }
        });
    }
    var fermeurs = document.querySelectorAll('[data-fermer-dialogue]');
    for (var j = 0; j < fermeurs.length; j++) {
        fermeurs[j].addEventListener('click', function () {
            var d = this.closest('dialog');
            if (d) { d.close(); }
        });
    }
})();
</script>
