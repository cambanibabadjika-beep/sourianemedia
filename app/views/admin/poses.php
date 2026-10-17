<?php
/**
 * Vue — Preuves de pose et contrôles de conformité (EF-B06).
 *
 * Variables : $poses, $controle, $campagnes, $panneauxCampagnes
 */

declare(strict_types=1);

use Souriane\Core\Auth;
use Souriane\Core\Csrf;
use Souriane\Core\Icone;

$role = Auth::role();
$estAnnonceur = $role === 'annonceur';
$peutEnregistrer = in_array($role, ['regie', 'agent'], true);
?>

<?php if ($estAnnonceur): ?>
    <div class="panneau" style="margin-bottom:20px; padding:18px 22px; border-left:4px solid var(--violet-600);">
        <div style="display:flex; align-items:center; gap:16px;">
            <div style="color:var(--violet-600); width:36px; height:36px; flex-shrink:0;">
                <?= Icone::svg('photo') ?>
            </div>
            <div>
                <strong style="font-family:var(--police-titre); font-size:1.05rem; display:block; margin-bottom:2px;">
                    Suivi photographique de vos affiches
                </strong>
                <p style="margin:0; font-size:.875rem; color:var(--texte-discret); line-height:1.4;">
                    Retrouvez ici toutes les photos prises directement sur le terrain par nos équipes pour certifier
                    la mise en place, la conformité et l’excellente visibilité de vos campagnes sur chaque panneau.
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="panneau">
    <form class="filtres" method="get" action="<?= e(url('admin/poses')) ?>">
        <div class="champ">
            <label class="champ__etiquette" for="controle">Contrôle de conformité</label>
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
                <h3>Aucune preuve de pose trouvée</h3>
                <p>
                    <?= $estAnnonceur
                        ? 'Les photos de vos panneaux apparaîtront ici dès que l’équipe terrain aura finalisé l’installation.'
                        : 'Chaque pose d’affiche doit être photographiée, géolocalisée et horodatée.' ?>
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="tableau-enveloppe">
            <table class="tableau">
                <thead>
                    <tr>
                        <th style="width:76px; text-align:center;">Photo</th>
                        <th>Panneau</th>
                        <th>Campagne</th>
                        <?php if (!$estAnnonceur): ?>
                            <th>Annonceur</th>
                        <?php endif; ?>
                        <th>Date de pose</th>
                        <th>Agent</th>
                        <th>Coordonnées GPS</th>
                        <th>Contrôle 15 j</th>
                        <th>Contrôle 30 j</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($poses as $pose): ?>
                        <?php
                        $photoUrl = !empty($pose['photo']) ? url_photo((string) $pose['photo']) : '';
                        $gpsDispo = !empty($pose['latitude']) && !empty($pose['longitude']);
                        $lienGps = $gpsDispo
                            ? 'https://www.google.com/maps?q=' . rawurlencode($pose['latitude'] . ',' . $pose['longitude'])
                            : '#';
                        ?>
                        <tr>
                            <td style="text-align:center; padding:8px 10px;">
                                <?php if ($photoUrl !== ''): ?>
                                    <button type="button" class="vignette-pose"
                                            data-ouvrir-zoom
                                            data-photo="<?= e($photoUrl) ?>"
                                            data-panneau="<?= e((string) $pose['reference'] . ' — ' . $pose['libelle']) ?>"
                                            data-ville="<?= e((string) ($pose['ville'] ?? '')) ?>"
                                            data-format="<?= e((string) ($pose['format'] ?? '')) ?>"
                                            data-campagne="<?= e((string) $pose['campagne_nom']) ?>"
                                            data-annonceur="<?= e((string) $pose['annonceur_nom']) ?>"
                                            data-date="<?= e(date_fr((string) $pose['horodatage'], true)) ?>"
                                            data-agent="<?= e((string) ($pose['agent_nom'] ?? '—')) ?>"
                                            data-lat="<?= e((string) ($pose['latitude'] ?? '')) ?>"
                                            data-lng="<?= e((string) ($pose['longitude'] ?? '')) ?>"
                                            data-note="<?= e((string) ($pose['note'] ?? '')) ?>"
                                            data-c15="<?= e((string) $pose['controle_15j']) ?>"
                                            data-c30="<?= e((string) $pose['controle_30j']) ?>"
                                            title="Cliquer pour agrandir la photo de preuve">
                                        <img src="<?= e($photoUrl) ?>" alt="Pose <?= e((string) $pose['reference']) ?>" loading="lazy">
                                    </button>
                                <?php else: ?>
                                    <span class="vignette-pose vignette-pose--vide" title="Aucune photo disponible">
                                        <?= Icone::svg('photo') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="tableau__principal">
                                <a href="<?= e(url('admin/panneaux/' . $pose['panneau_id'])) ?>"><?= e((string) $pose['reference']) ?></a>
                                <div class="tableau__secondaire"><?= e((string) $pose['libelle']) ?> (<?= e((string) ($pose['ville'] ?? '')) ?>)</div>
                            </td>
                            <td>
                                <a href="<?= e(url('admin/campagnes/' . $pose['campagne_id'])) ?>"><?= e((string) $pose['campagne_nom']) ?></a>
                            </td>
                            <?php if (!$estAnnonceur): ?>
                                <td><?= e((string) $pose['annonceur_nom']) ?></td>
                            <?php endif; ?>
                            <td class="texte-petit"><?= e(date_fr((string) $pose['horodatage'], true)) ?></td>
                            <td><?= e((string) ($pose['agent_nom'] ?? '—')) ?></td>
                            <td class="texte-petit monospace">
                                <?php if ($gpsDispo): ?>
                                    <a href="<?= e($lienGps) ?>" target="_blank" rel="noopener noreferrer" title="Voir sur Google Maps" style="text-decoration:underline;">
                                        <?= e(number_format((float) $pose['latitude'], 4, '.', '')) ?>,
                                        <?= e(number_format((float) $pose['longitude'], 4, '.', '')) ?>
                                    </a>
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
    <strong>Charte de qualité Souriane.</strong> Le contrôle à quinze jours vérifie la tenue du visuel face aux intempéries ;
    le contrôle à trente jours vérifie la lisibilité du QR code dans les conditions réelles d’exposition (soleil, poussière, angles de vue).
    Toute anomalie déclenche immédiatement une reprise d’affiche par nos équipes.
</div>

<!-- Dialogue d'agrandissement photo (Visionneuse HD) ------------------------- -->
<dialog id="dialogue-zoom-photo" class="dialogue-zoom">
    <div style="position:relative; background:#0b0716; text-align:center;">
        <img id="zoom-img" class="dialogue-zoom__image" src="" alt="Preuve de pose">
        <button type="button" class="btn btn--contour btn--petit" data-fermer-dialogue
                style="position:absolute; top:12px; right:12px; background:rgba(0,0,0,.65); color:#fff; border-color:rgba(255,255,255,.3); z-index:2">
            ✕ Fermer
        </button>
    </div>
    <div class="dialogue-zoom__corps">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
            <div>
                <h3 id="zoom-panneau" style="margin:0 0 4px; font-family:var(--police-titre); font-size:1.15rem; color:var(--charbon);"></h3>
                <div id="zoom-campagne" class="texte-petit" style="color:var(--lilas-700); font-weight:600; margin-bottom:4px;"></div>
                <div id="zoom-meta" class="texte-petit" style="color:var(--gris-600);"></div>
            </div>
            <div id="zoom-statuts" class="carte-pose__statuts"></div>
        </div>

        <div id="zoom-note-conteneur" style="display:none;" class="carte-pose__note">
            <strong>Rapport d’installation :</strong> <span id="zoom-note"></span>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px; padding-top:10px; border-top:1px solid var(--bordure); flex-wrap:wrap; gap:12px;">
            <div id="zoom-gps" class="texte-petit monospace"></div>
            <a id="zoom-lien-carte" href="#" target="_blank" rel="noopener noreferrer" class="btn btn--contour btn--petit">
                <?= Icone::svg('site') ?> Voir la localisation satellite
            </a>
        </div>
    </div>
</dialog>

<!-- Dialogue d’enregistrement d’une pose (Régie et Agents) ------------------ -->
<?php if ($peutEnregistrer): ?>
<dialog id="dialogue-pose" style="border:0; border-radius:var(--rayon); padding:0; max-width:540px; width:calc(100% - 32px); box-shadow:var(--ombre-lg); background:var(--surface);">
    <form method="post" action="<?= e(url('admin/poses')) ?>" enctype="multipart/form-data" style="padding:24px">
        <?= Csrf::champ() ?>
        <h2 class="panneau__titre" style="margin-bottom:18px"><?= Icone::svg('photo') ?> Enregistrer une preuve de pose</h2>

        <div class="champ">
            <label class="champ__etiquette" for="pose_campagne">Campagne <span class="requis">*</span></label>
            <select class="liste" id="pose_campagne" name="campagne_id" required>
                <option value="">— Choisir la campagne —</option>
                <?php foreach ($campagnes as $campagne): ?>
                    <option value="<?= e((string) $campagne['id']) ?>"><?= e((string) $campagne['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="champ">
            <label class="champ__etiquette" for="pose_panneau">Panneau associé <span class="requis">*</span></label>
            <select class="liste" id="pose_panneau" name="panneau_id" required>
                <option value="">— Sélectionnez d’abord une campagne —</option>
            </select>
            <div class="champ__aide">Seuls les panneaux réservés pour cette campagne sont affichés.</div>
        </div>

        <div class="champ">
            <label class="champ__etiquette" for="pose_photo_fichier">
                Photo prise sur le terrain <span class="requis">*</span>
            </label>
            <input class="saisie" type="file" id="pose_photo_fichier" name="photo_fichier"
                   accept="image/jpeg,image/png,image/webp" capture="environment">
            <div class="champ__aide">Prenez ou sélectionnez une photo nette du panneau entier avec le visuel et le QR code.</div>
            <div id="zone-apercu-photo" style="display:none; margin-top:10px;">
                <img id="apercu-img" src="" alt="Aperçu photo"
                     style="max-width:100%; max-height:200px; border-radius:var(--rayon-sm); object-fit:cover; border:1px solid var(--bordure); display:block;">
            </div>
        </div>

        <details style="margin-bottom:14px; font-size:.85rem;">
            <summary style="cursor:pointer; color:var(--lilas-700); padding:4px 0;">Ou spécifier un chemin / URL déjà en ligne</summary>
            <div class="champ" style="margin-top:8px;">
                <input class="saisie" type="text" id="pose_photo" name="photo" placeholder="storage/uploads/... ou https://...">
            </div>
        </details>

        <div class="champ">
            <label class="champ__etiquette" for="pose_horodatage">Date et heure d'installation</label>
            <input class="saisie" type="datetime-local" id="pose_horodatage" name="horodatage" value="<?= date('Y-m-d\TH:i') ?>">
        </div>

        <div class="grille-champs">
            <div class="champ">
                <label class="champ__etiquette" for="pose_latitude">Latitude</label>
                <input class="saisie" type="text" id="pose_latitude" name="latitude" inputmode="decimal" placeholder="ex: 12.8150">
            </div>
            <div class="champ">
                <label class="champ__etiquette" for="pose_longitude">Longitude</label>
                <input class="saisie" type="text" id="pose_longitude" name="longitude" inputmode="decimal" placeholder="ex: -16.2280">
            </div>
        </div>

        <div style="margin-top:-6px; margin-bottom:16px;">
            <button type="button" class="btn btn--contour btn--petit" id="btn-geoloc">
                <?= Icone::svg('gps') ?> Géolocaliser ma position actuelle
            </button>
            <div id="statut-geoloc" class="texte-petit" style="margin-top:6px; display:none;"></div>
        </div>

        <div class="champ">
            <label class="champ__etiquette" for="pose_note">Note de terrain</label>
            <textarea class="zone-texte" id="pose_note" name="note" rows="2"
                      placeholder="Pose conforme, QR testé à 5 m sous le soleil, affiche bien tendue."></textarea>
        </div>

        <div class="pile pile--droite">
            <button type="button" class="btn btn--contour" data-fermer-dialogue>Annuler</button>
            <button type="submit" class="btn btn--primaire"><?= Icone::svg('valider') ?> Enregistrer la preuve</button>
        </div>
    </form>
</dialog>

<script id="donnees-panneaux-campagnes" type="application/json">
<?= json_encode($panneauxCampagnes, JSON_HEX_TAG | JSON_HEX_AMP) ?>
</script>
<?php endif; ?>

<script>
(function () {
    // Gestion ouverture / fermeture des dialogues
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

    // Dialogue de Zoom Photo
    var zoomDialog = document.getElementById('dialogue-zoom-photo');
    var zoomImg = document.getElementById('zoom-img');
    var zoomPanneau = document.getElementById('zoom-panneau');
    var zoomCampagne = document.getElementById('zoom-campagne');
    var zoomMeta = document.getElementById('zoom-meta');
    var zoomStatuts = document.getElementById('zoom-statuts');
    var zoomNoteCont = document.getElementById('zoom-note-conteneur');
    var zoomNote = document.getElementById('zoom-note');
    var zoomGps = document.getElementById('zoom-gps');
    var zoomLienCarte = document.getElementById('zoom-lien-carte');

    var boutonsZoom = document.querySelectorAll('[data-ouvrir-zoom]');
    for (var k = 0; k < boutonsZoom.length; k++) {
        boutonsZoom[k].addEventListener('click', function () {
            var btn = this;
            var photo = btn.getAttribute('data-photo');
            var panneau = btn.getAttribute('data-panneau');
            var format = btn.getAttribute('data-format');
            var ville = btn.getAttribute('data-ville');
            var campagne = btn.getAttribute('data-campagne');
            var annonceur = btn.getAttribute('data-annonceur');
            var date = btn.getAttribute('data-date');
            var agent = btn.getAttribute('data-agent');
            var lat = btn.getAttribute('data-lat');
            var lng = btn.getAttribute('data-lng');
            var note = btn.getAttribute('data-note');
            var c15 = btn.getAttribute('data-c15');
            var c30 = btn.getAttribute('data-c30');

            zoomImg.src = photo;
            zoomPanneau.textContent = panneau;
            zoomCampagne.textContent = 'Campagne : ' + campagne + (annonceur ? ' (' + annonceur + ')' : '');

            var metaText = 'Posé le ' + date + ' · Agent : ' + agent;
            if (format || ville) {
                metaText += ' · ' + [format, ville].filter(Boolean).join(' - ');
            }
            zoomMeta.textContent = metaText;

            if (note) {
                zoomNote.textContent = note;
                zoomNoteCont.style.display = 'block';
            } else {
                zoomNoteCont.style.display = 'none';
            }

            if (lat && lng) {
                zoomGps.textContent = 'GPS : ' + parseFloat(lat).toFixed(5) + ', ' + parseFloat(lng).toFixed(5);
                zoomLienCarte.href = 'https://www.google.com/maps?q=' + encodeURIComponent(lat + ',' + lng);
                zoomLienCarte.style.display = 'inline-flex';
            } else {
                zoomGps.textContent = 'Coordonnées GPS non renseignées';
                zoomLienCarte.style.display = 'none';
            }

            // Statuts de conformité
            var htmlStatuts = '';
            var libelles = { non_fait: 'À faire', conforme: 'Conforme', non_conforme: 'Non conforme' };
            var classes = { non_fait: 'etat--reservee', conforme: 'etat--conforme', non_conforme: 'etat--annulee' };
            if (c15) {
                htmlStatuts += '<span class="etat ' + (classes[c15] || '') + '">15 j : ' + (libelles[c15] || c15) + '</span>';
            }
            if (c30) {
                htmlStatuts += '<span class="etat ' + (classes[c30] || '') + '">30 j : ' + (libelles[c30] || c30) + '</span>';
            }
            zoomStatuts.innerHTML = htmlStatuts;

            if (zoomDialog && zoomDialog.showModal) {
                zoomDialog.showModal();
            }
        });
    }

    // Filtrage dynamique des panneaux par campagne
    var tagPanneaux = document.getElementById('donnees-panneaux-campagnes');
    var selectCampagne = document.getElementById('pose_campagne');
    var selectPanneau = document.getElementById('pose_panneau');

    if (tagPanneaux && selectCampagne && selectPanneau) {
        var listePanneaux = JSON.parse(tagPanneaux.textContent || '[]');

        selectCampagne.addEventListener('change', function () {
            var cid = parseInt(this.value, 10);
            selectPanneau.innerHTML = '';

            if (isNaN(cid) || cid <= 0) {
                var optDefaut = document.createElement('option');
                optDefaut.value = '';
                optDefaut.textContent = '— Sélectionnez d’abord une campagne —';
                selectPanneau.appendChild(optDefaut);
                return;
            }

            var filtrés = listePanneaux.filter(function (item) {
                return parseInt(item.campagne_id, 10) === cid;
            });

            if (filtrés.length === 0) {
                var optVide = document.createElement('option');
                optVide.value = '';
                optVide.textContent = '— Aucun panneau réservé sur cette campagne —';
                selectPanneau.appendChild(optVide);
            } else {
                var optChoix = document.createElement('option');
                optChoix.value = '';
                optChoix.textContent = '— Choisir le panneau (' + filtrés.length + ' disponible' + (filtrés.length > 1 ? 's' : '') + ') —';
                selectPanneau.appendChild(optChoix);

                filtrés.forEach(function (p) {
                    var opt = document.createElement('option');
                    opt.value = p.panneau_id;
                    opt.textContent = p.reference + ' — ' + p.libelle + (p.ville ? ' (' + p.ville + ')' : '');
                    selectPanneau.appendChild(opt);
                });
            }
        });
    }

    // Aperçu instantané de la photo sélectionnée
    var inputFichier = document.getElementById('pose_photo_fichier');
    var zoneApercu = document.getElementById('zone-apercu-photo');
    var apercuImg = document.getElementById('apercu-img');

    if (inputFichier && zoneApercu && apercuImg) {
        inputFichier.addEventListener('change', function () {
            var fichier = this.files && this.files[0];
            if (fichier) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    apercuImg.src = e.target.result;
                    zoneApercu.style.display = 'block';
                };
                reader.readAsDataURL(fichier);
            } else {
                zoneApercu.style.display = 'none';
            }
        });
    }

    // Géolocalisation automatique sur le terrain
    var btnGeoloc = document.getElementById('btn-geoloc');
    var statutGeoloc = document.getElementById('statut-geoloc');
    var champLat = document.getElementById('pose_latitude');
    var champLng = document.getElementById('pose_longitude');

    if (btnGeoloc && statutGeoloc && champLat && champLng) {
        btnGeoloc.addEventListener('click', function () {
            if (!navigator.geolocation) {
                alert('La géolocalisation n’est pas supportée par votre navigateur.');
                return;
            }
            statutGeoloc.style.display = 'block';
            statutGeoloc.style.color = 'var(--texte-discret)';
            statutGeoloc.textContent = 'Acquisition de votre position satellite GPS...';

            navigator.geolocation.getCurrentPosition(function (pos) {
                champLat.value = pos.coords.latitude.toFixed(6);
                champLng.value = pos.coords.longitude.toFixed(6);
                statutGeoloc.textContent = '✓ Position capturée (précision ~' + Math.round(pos.coords.accuracy) + ' m)';
                statutGeoloc.style.color = 'var(--vert-600)';
            }, function (err) {
                statutGeoloc.textContent = 'Impossible de récupérer la position : ' + err.message;
                statutGeoloc.style.color = 'var(--rouge-600)';
            }, { enableHighAccuracy: true, timeout: 10000 });
        });
    }
})();
</script>
