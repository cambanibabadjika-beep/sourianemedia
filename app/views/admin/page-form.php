<?php
/**
 * Vue — Édition d’une page publique et de ses sections de contenu.
 *
 * Variables :
 *   $page      array
 *   $annonceur array
 *   $sections  array — toutes les sections, actives ou non
 *   $campagnes array
 */

declare(strict_types=1);

use Souriane\Core\Csrf;
use Souriane\Core\Icone;

$parType = [];
foreach ($sections as $section) {
    $parType[$section['type']] = $section;
}

/** Contenu décodé d’une section. */
$contenu = static function (string $type) use ($parType): array {
    return isset($parType[$type]) ? json_lire($parType[$type]['contenu'] ?? null) : [];
};

$titreSection = static function (string $type, string $defaut) use ($parType): string {
    return (string) ($parType[$type]['titre'] ?? '') !== '' ? (string) $parType[$type]['titre'] : $defaut;
};

$active = static function (string $type) use ($parType): bool {
    return isset($parType[$type]) && (int) $parType[$type]['actif'] === 1;
};

$offre       = $contenu('offre');
$presentation = $contenu('presentation');
$infos       = $contenu('infos_complementaires');
$galerie     = $contenu('galerie');
$personnalise = $contenu('personnalise');
$avenir      = $contenu('offres_a_venir');

$images = [];
foreach (($galerie['images'] ?? []) as $image) {
    $images[] = is_array($image) ? (string) ($image['url'] ?? $image['src'] ?? '') : (string) $image;
}

$videos = [];
foreach (($galerie['videos'] ?? []) as $video) {
    $videos[] = is_array($video) ? (string) ($video['url'] ?? $video['src'] ?? '') : (string) $video;
}
?>

<div class="encart">
    <strong>Adresse publique :</strong>
    <span class="monospace"><?= e(url_page((string) $page['adresse_courte'])) ?></span>
    — les horaires, l’adresse, le téléphone et les réseaux sociaux proviennent de la
    <a href="<?= e(url('admin/annonceurs/' . $annonceur['id'])) ?>">fiche annonceur</a>.
</div>

<form method="post" action="<?= e(url('admin/pages/' . $page['id'])) ?>">
    <?= Csrf::champ() ?>

    <!-- Paramètres de la page ------------------------------------------------- -->
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('site') ?> Paramètres de la page</h2>
            <?= etiquette_etat((string) $page['statut']) ?>
        </div>
        <div class="panneau__corps">
            <div class="grille-champs">
                <div class="champ">
                    <label class="champ__etiquette" for="titre">Titre de la page</label>
                    <input class="saisie" type="text" id="titre" name="titre" maxlength="180"
                           value="<?= e((string) ($page['titre'] ?? '')) ?>">
                    <div class="champ__aide">Apparaît dans l’onglet du navigateur et les partages.</div>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="adresse_courte">Adresse courte</label>
                    <input class="saisie" type="text" id="adresse_courte" name="adresse_courte" maxlength="80"
                           value="<?= e((string) $page['adresse_courte']) ?>">
                </div>
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="bandeau_offre">Bandeau d’offre</label>
                    <input class="saisie" type="text" id="bandeau_offre" name="bandeau_offre" maxlength="255"
                           value="<?= e((string) ($page['bandeau_offre'] ?? '')) ?>"
                           placeholder="−20 % sur l’outillage de chantier jusqu’au 30 novembre">
                    <div class="champ__aide">Repris du panneau. C’est la première chose que lit le passant.</div>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="statut">Statut de publication</label>
                    <select class="liste" id="statut" name="statut">
                        <?php foreach (['brouillon', 'publiee', 'depubliee'] as $valeur): ?>
                            <option value="<?= e($valeur) ?>"<?= $page['statut'] === $valeur ? ' selected' : '' ?>>
                                <?= e(ETATS_LIBELLES[$valeur]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="champ__aide">Seule une page publiée est accessible depuis un QR code.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 1. Offre --------------------------------------------------------------- -->
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('etincelle') ?> 1 · Offre en cours</h2>
            <label class="case" style="border:0;padding:0;gap:8px">
                <input type="hidden" name="section_offre_present" value="1">
                <input type="checkbox" name="section_offre_actif" value="1"<?= $active('offre') ? ' checked' : '' ?>>
                <span>Section affichée</span>
            </label>
        </div>
        <div class="panneau__corps">
            <div class="grille-champs">
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="offre_titre">Titre de la section</label>
                    <input class="saisie" type="text" id="offre_titre" name="offre_titre" maxlength="180"
                           value="<?= e($titreSection('offre', 'Offre en cours')) ?>">
                </div>
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="offre_texte">Texte de l’offre</label>
                    <textarea class="zone-texte" id="offre_texte" name="offre_texte" rows="3"><?= e((string) ($offre['texte'] ?? '')) ?></textarea>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="offre_debut">Début de validité</label>
                    <input class="saisie" type="date" id="offre_debut" name="offre_debut"
                           value="<?= e((string) ($offre['debut'] ?? '')) ?>">
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="offre_fin">Fin de validité</label>
                    <input class="saisie" type="date" id="offre_fin" name="offre_fin"
                           value="<?= e((string) ($offre['fin'] ?? '')) ?>">
                </div>
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="offre_restrictions">Restrictions</label>
                    <input class="saisie" type="text" id="offre_restrictions" name="offre_restrictions"
                           value="<?= e((string) ($offre['restrictions'] ?? '')) ?>"
                           placeholder="Dans la limite des stocks disponibles. Non cumulable avec d’autres remises.">
                    <div class="champ__aide">Obligatoire dès qu’une offre est chiffrée : conditions de l’offre (EF-D05).</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Présentation -------------------------------------------------------- -->
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('info') ?> 2 · Présentation</h2>
            <label class="case" style="border:0;padding:0;gap:8px">
                <input type="hidden" name="section_presentation_present" value="1">
                <input type="checkbox" name="section_presentation_actif" value="1"<?= $active('presentation') ? ' checked' : '' ?>>
                <span>Section affichée</span>
            </label>
        </div>
        <div class="panneau__corps">
            <div class="grille-champs">
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="presentation_titre">Titre de la section</label>
                    <input class="saisie" type="text" id="presentation_titre" name="presentation_titre" maxlength="180"
                           value="<?= e($titreSection('presentation', 'Qui sommes-nous')) ?>">
                </div>
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="presentation_texte">Texte</label>
                    <textarea class="zone-texte" id="presentation_texte" name="presentation_texte" rows="4"><?= e((string) ($presentation['texte'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Informations complémentaires ---------------------------------------- -->
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('coche-cercle') ?> 3 · Informations complémentaires</h2>
            <label class="case" style="border:0;padding:0;gap:8px">
                <input type="hidden" name="section_infos_complementaires_present" value="1">
                <input type="checkbox" name="section_infos_complementaires_actif" value="1"<?= $active('infos_complementaires') ? ' checked' : '' ?>>
                <span>Section affichée</span>
            </label>
        </div>
        <div class="panneau__corps">
            <div class="grille-champs">
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="infos_complementaires_titre">Titre de la section</label>
                    <input class="saisie" type="text" id="infos_complementaires_titre" name="infos_complementaires_titre" maxlength="180"
                           value="<?= e($titreSection('infos_complementaires', 'Informations complémentaires')) ?>">
                </div>
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="infos_texte">Texte</label>
                    <textarea class="zone-texte" id="infos_texte" name="infos_texte" rows="3"><?= e((string) ($infos['texte'] ?? '')) ?></textarea>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="infos_conditions">Conditions</label>
                    <input class="saisie" type="text" id="infos_conditions" name="infos_conditions"
                           value="<?= e((string) ($infos['conditions'] ?? '')) ?>">
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="infos_validite">Validité</label>
                    <input class="saisie" type="text" id="infos_validite" name="infos_validite"
                           value="<?= e((string) ($infos['validite'] ?? '')) ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Galerie ------------------------------------------------------------- -->
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('photo') ?> 4 · Galerie</h2>
            <label class="case" style="border:0;padding:0;gap:8px">
                <input type="hidden" name="section_galerie_present" value="1">
                <input type="checkbox" name="section_galerie_actif" value="1"<?= $active('galerie') ? ' checked' : '' ?>>
                <span>Section affichée</span>
            </label>
        </div>
        <div class="panneau__corps">
            <div class="grille-champs">
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="galerie_titre">Titre de la section</label>
                    <input class="saisie" type="text" id="galerie_titre" name="galerie_titre" maxlength="180"
                           value="<?= e($titreSection('galerie', 'En images')) ?>">
                </div>
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="galerie_images">Adresses des images</label>
                    <textarea class="zone-texte" id="galerie_images" name="galerie_images" rows="4"
                              placeholder="storage/uploads/photo-1.jpg"><?= e(implode("\n", array_filter($images))) ?></textarea>
                    <div class="champ__aide">Une adresse par ligne. Les images sont servies en 300 × 300 px maximum (neuf images affichées).</div>
                </div>
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="galerie_videos">Adresses des vidéos courtes</label>
                    <textarea class="zone-texte" id="galerie_videos" name="galerie_videos" rows="3"
                              placeholder="storage/uploads/video-1.mp4"><?= e(implode("\n", array_filter($videos))) ?></textarea>
                    <div class="champ__aide">Une adresse par ligne. Format recommandé : MP4 (H.264), 720 px de large, moins de 30 Mo. Les vidéos s’affichent avec les images dans la même grille.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Bloc personnalisé --------------------------------------------------- -->
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('etincelle') ?> 5 · Bloc personnalisé</h2>
            <label class="case" style="border:0;padding:0;gap:8px">
                <input type="hidden" name="section_personnalise_present" value="1">
                <input type="checkbox" name="section_personnalise_actif" value="1"<?= $active('personnalise') ? ' checked' : '' ?>>
                <span>Section affichée</span>
            </label>
        </div>
        <div class="panneau__corps">
            <div class="grille-champs">
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="personnalise_titre">Titre de la section</label>
                    <input class="saisie" type="text" id="personnalise_titre" name="personnalise_titre" maxlength="180"
                           value="<?= e($titreSection('personnalise', 'Bon à savoir')) ?>">
                </div>
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="personnalise_texte">Texte</label>
                    <textarea class="zone-texte" id="personnalise_texte" name="personnalise_texte" rows="3"><?= e((string) ($personnalise['texte'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Offres à venir ------------------------------------------------------ -->
    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('calendrier') ?> 6 · Prochainement</h2>
            <label class="case" style="border:0;padding:0;gap:8px">
                <input type="hidden" name="section_offres_a_venir_present" value="1">
                <input type="checkbox" name="section_offres_a_venir_actif" value="1"<?= $active('offres_a_venir') ? ' checked' : '' ?>>
                <span>Section affichée</span>
            </label>
        </div>
        <div class="panneau__corps">
            <div class="grille-champs">
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="offres_a_venir_titre">Titre de la section</label>
                    <input class="saisie" type="text" id="offres_a_venir_titre" name="offres_a_venir_titre" maxlength="180"
                           value="<?= e($titreSection('offres_a_venir', 'Prochainement')) ?>">
                </div>
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="avenir_texte">Texte</label>
                    <textarea class="zone-texte" id="avenir_texte" name="avenir_texte" rows="3"><?= e((string) ($avenir['texte'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="pile">
        <button type="submit" class="btn btn--primaire">
            <?= Icone::svg('valider') ?>
            Enregistrer la page
        </button>
        <a class="btn btn--contour" target="_blank" rel="noopener"
           href="<?= e(url_page((string) $page['adresse_courte'])) ?>">
            <?= Icone::svg('lien-externe') ?>
            Prévisualiser
        </a>
        <a class="btn btn--contour" href="<?= e(url('admin/pages')) ?>">Retour à la liste</a>
    </div>
</form>

<?php if ($campagnes !== []): ?>
    <div class="panneau ecart-haut--grand">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('etincelle') ?> Campagnes liées à cet annonceur</h2>
        </div>
        <div class="tableau-enveloppe">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Campagne</th>
                        <th>Période</th>
                        <th class="tableau__numerique">Panneaux</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campagnes as $campagne): ?>
                        <tr>
                            <td>
                                <a class="tableau__principal" href="<?= e(url('admin/campagnes/' . $campagne['id'])) ?>">
                                    <?= e((string) $campagne['nom']) ?>
                                </a>
                            </td>
                            <td class="texte-petit">
                                <?= e(date_fr((string) $campagne['date_debut'])) ?> → <?= e(date_fr((string) $campagne['date_fin'])) ?>
                            </td>
                            <td class="tableau__numerique"><?= e((string) $campagne['nb_panneaux']) ?></td>
                            <td><?= etiquette_etat((string) $campagne['statut']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
