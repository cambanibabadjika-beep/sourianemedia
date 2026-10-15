<?php
/**
 * Vue — Fiche annonceur (création et modification).
 *
 * Variables :
 *   $annonceur ?array
 *   $page      ?array — page publique associée
 *   $campagnes array
 *   $abonnes   int
 */

declare(strict_types=1);

use Souriane\Core\Csrf;
use Souriane\Core\Icone;

$creation = $annonceur === null;
$action   = $creation ? url('admin/annonceurs/nouveau') : url('admin/annonceurs/' . $annonceur['id']);

$reseaux  = json_lire($annonceur['reseaux'] ?? null);
$horaires = json_lire($annonceur['horaires'] ?? null);

$jours = [
    'lun' => 'Lundi', 'mar' => 'Mardi', 'mer' => 'Mercredi', 'jeu' => 'Jeudi',
    'ven' => 'Vendredi', 'sam' => 'Samedi', 'dim' => 'Dimanche',
];
?>

<form method="post" action="<?= e($action) ?>">
    <?= Csrf::champ() ?>

    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('utilisateurs') ?> Identité</h2>
            <?php if (!$creation): ?>
                <span class="texte-petit">
                    Créé le <?= e(date_fr((string) $annonceur['cree_le'])) ?> ·
                    <?= e((string) $abonnes) ?> abonné(s)
                </span>
            <?php endif; ?>
        </div>
        <div class="panneau__corps">
            <div class="grille-champs">
                <div class="champ">
                    <label class="champ__etiquette" for="nom">Nom <span class="requis">*</span></label>
                    <input class="saisie" type="text" id="nom" name="nom" required maxlength="180"
                           value="<?= e((string) ($annonceur['nom'] ?? '')) ?>">
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="adresse_courte">Adresse courte (URL)</label>
                    <input class="saisie" type="text" id="adresse_courte" name="adresse_courte" maxlength="80"
                           value="<?= e((string) ($annonceur['adresse_courte'] ?? '')) ?>"
                           placeholder="ex. quincaillerie-bignona">
                    <div class="champ__aide">
                        Laissez vide pour générer automatiquement.
                        <?php if (!$creation): ?>
                            Page publique : <span class="monospace">/<?= e((string) $annonceur['adresse_courte']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="description">Description</label>
                    <textarea class="zone-texte" id="description" name="description" rows="3"><?= e((string) ($annonceur['description'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('telephone') ?> Contact et localisation</h2>
        </div>
        <div class="panneau__corps">
            <div class="grille-champs">
                <div class="champ">
                    <label class="champ__etiquette" for="telephone">Téléphone</label>
                    <input class="saisie" type="tel" id="telephone" name="telephone" maxlength="30"
                           value="<?= e((string) ($annonceur['telephone'] ?? '')) ?>" placeholder="+221 77 123 45 67">
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="whatsapp">WhatsApp</label>
                    <input class="saisie" type="tel" id="whatsapp" name="whatsapp" maxlength="30"
                           value="<?= e((string) ($annonceur['whatsapp'] ?? '')) ?>">
                    <div class="champ__aide">Utilisé par le bouton WhatsApp de la page publique.</div>
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="email">Adresse e-mail</label>
                    <input class="saisie" type="email" id="email" name="email" maxlength="180"
                           value="<?= e((string) ($annonceur['email'] ?? '')) ?>">
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="ville">Ville</label>
                    <input class="saisie" type="text" id="ville" name="ville" maxlength="120"
                           value="<?= e((string) ($annonceur['ville'] ?? '')) ?>">
                </div>
                <div class="champ pleine-largeur">
                    <label class="champ__etiquette" for="adresse">Adresse</label>
                    <input class="saisie" type="text" id="adresse" name="adresse" maxlength="255"
                           value="<?= e((string) ($annonceur['adresse'] ?? '')) ?>">
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="latitude">Latitude</label>
                    <input class="saisie" type="text" id="latitude" name="latitude" inputmode="decimal"
                           value="<?= e((string) ($annonceur['latitude'] ?? '')) ?>" placeholder="12.8083000">
                </div>
                <div class="champ">
                    <label class="champ__etiquette" for="longitude">Longitude</label>
                    <input class="saisie" type="text" id="longitude" name="longitude" inputmode="decimal"
                           value="<?= e((string) ($annonceur['longitude'] ?? '')) ?>" placeholder="-16.2333000">
                </div>
            </div>
        </div>
    </div>

    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('horloge') ?> Horaires d’ouverture</h2>
        </div>
        <div class="panneau__corps">
            <div class="grille-champs grille-champs--3">
                <?php foreach ($jours as $cle => $libelle): ?>
                    <div class="champ">
                        <label class="champ__etiquette" for="horaire_<?= e($cle) ?>"><?= e($libelle) ?></label>
                        <input class="saisie" type="text" id="horaire_<?= e($cle) ?>" name="horaire_<?= e($cle) ?>"
                               maxlength="40" value="<?= e((string) ($horaires[$cle] ?? '')) ?>"
                               placeholder="08:00-19:00">
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="champ__aide">Saisissez « fermé » pour un jour sans ouverture. Le jour courant est mis en évidence sur la page publique.</p>
        </div>
    </div>

    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('partage') ?> Réseaux sociaux</h2>
        </div>
        <div class="panneau__corps">
            <div class="grille-champs">
                <?php foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'site' => 'Site web'] as $cle => $libelle): ?>
                    <div class="champ">
                        <label class="champ__etiquette" for="reseau_<?= e($cle) ?>"><?= e($libelle) ?></label>
                        <input class="saisie" type="url" id="reseau_<?= e($cle) ?>" name="<?= e($cle) ?>"
                               value="<?= e((string) ($reseaux[$cle] ?? '')) ?>" placeholder="https://">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="panneau">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('reglages') ?> Publication</h2>
        </div>
        <div class="panneau__corps">
            <div class="grille-champs">
                <div class="champ">
                    <label class="champ__etiquette" for="statut">Statut de l’annonceur</label>
                    <select class="liste" id="statut" name="statut">
                        <?php foreach (['actif', 'inactif', 'archive'] as $valeur): ?>
                            <option value="<?= e($valeur) ?>"<?= ($annonceur['statut'] ?? 'actif') === $valeur ? ' selected' : '' ?>>
                                <?= e(ETATS_LIBELLES[$valeur]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="champ__aide">Un annonceur inactif ou archivé ne peut plus recevoir d’abonnements.</div>
                </div>
            </div>

            <?php if (!$creation && $page !== null): ?>
                <hr class="separateur">
                <div class="pile pile--entre">
                    <div>
                        <strong>Page publique</strong>
                        <div class="texte-petit">
                            <?= etiquette_etat((string) $page['statut']) ?>
                            <span class="monospace">/<?= e((string) $page['adresse_courte']) ?></span>
                        </div>
                    </div>
                    <div class="pile">
                        <a class="btn btn--contour btn--petit" target="_blank" rel="noopener"
                           href="<?= e(url_page((string) $page['adresse_courte'])) ?>">
                            <?= Icone::svg('lien-externe') ?> Voir
                        </a>
                        <a class="btn btn--primaire btn--petit" href="<?= e(url('admin/pages/' . $page['id'])) ?>">
                            <?= Icone::svg('modifier') ?> Modifier la page
                        </a>
                    </div>
                </div>
            <?php elseif (!$creation): ?>
                <hr class="separateur">
                <div class="encart encart--avertissement" style="margin:0">
                    Cet annonceur n’a pas encore de page publique. Créez-la depuis la rubrique
                    <strong>Pages publiques</strong> après avoir enregistré sa fiche.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="pile">
        <button type="submit" class="btn btn--primaire">
            <?= Icone::svg('valider') ?>
            <?= $creation ? 'Créer l’annonceur' : 'Enregistrer les modifications' ?>
        </button>
        <a class="btn btn--contour" href="<?= e(url('admin/annonceurs')) ?>">Annuler</a>
    </div>
</form>

<?php if (!$creation && $campagnes !== []): ?>
    <div class="panneau ecart-haut--grand">
        <div class="panneau__entete">
            <h2 class="panneau__titre"><?= Icone::svg('etincelle') ?> Campagnes de cet annonceur</h2>
            <a class="btn btn--contour btn--petit" href="<?= e(url('admin/campagnes?q=' . rawurlencode((string) $annonceur['nom']))) ?>">Tout voir</a>
        </div>
        <div class="tableau-enveloppe">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Campagne</th>
                        <th>Période</th>
                        <th class="tableau__numerique">Panneaux</th>
                        <th class="tableau__numerique">Prix</th>
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
                            <td class="tableau__numerique"><?= e(fcfa($campagne['prix'])) ?></td>
                            <td><?= etiquette_etat((string) $campagne['statut']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
