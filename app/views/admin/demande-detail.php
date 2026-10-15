<?php
/**
 * Vue — Détail d’une demande de proposition.
 *
 * Variables :
 *   $demande array
 *   $formats array<string>
 *   $statuts array<string>
 */

declare(strict_types=1);

use Souriane\Core\Csrf;
use Souriane\Core\Icone;

$demande = $demande ?? [];
$formats = $formats ?? [];
$statuts = $statuts ?? [];
?>
<div class="pile pile--entre" style="margin-bottom:20px">
    <a class="btn btn--contour btn--petit" href="<?= e(url('admin/demandes')) ?>">
        <?= Icone::svg('suivant') ?> Retour aux demandes
    </a>
    <a class="btn btn--primaire btn--petit" href="<?= e(url('admin/annonceurs/nouveau')) ?>">
        <?= Icone::svg('ajouter') ?> Créer un annonceur
    </a>
</div>

<div class="panneau">
    <div class="panneau__entete">
        <h2 class="panneau__titre"><?= Icone::svg('courriel') ?> <?= e((string) $demande['enseigne']) ?></h2>
        <?= etiquette_etat((string) $demande['statut']) ?>
    </div>
    <div class="panneau__corps">
        <div class="grille-champs grille-champs--3">
            <div class="champ">
                <span class="champ__etiquette">Contact</span>
                <div class="texte-petit"><?= e((string) $demande['contact']) ?></div>
            </div>
            <div class="champ">
                <span class="champ__etiquette">E-mail</span>
                <div class="texte-petit">
                    <a class="monospace" href="mailto:<?= e($demande['email']) ?>"><?= e((string) $demande['email']) ?></a>
                </div>
            </div>
            <div class="champ">
                <span class="champ__etiquette">Téléphone</span>
                <div class="texte-petit">
                    <?php if (!empty($demande['telephone'])): ?>
                        <a class="monospace" href="tel:<?= e(tel_lien((string) $demande['telephone'])) ?>"><?= e((string) $demande['telephone']) ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
            </div>
            <div class="champ">
                <span class="champ__etiquette">Ville visée</span>
                <div class="texte-petit"><?= e((string) $demande['ville']) ?></div>
            </div>
            <div class="champ">
                <span class="champ__etiquette">Période souhaitée</span>
                <div class="texte-petit"><?= $demande['periode'] !== '' ? e((string) $demande['periode']) : '—' ?></div>
            </div>
            <div class="champ">
                <span class="champ__etiquette">Enveloppe envisagée</span>
                <div class="texte-petit"><?= $demande['budget'] !== '' ? e((string) $demande['budget']) : '—' ?></div>
            </div>
            <div class="champ pleine-largeur">
                <span class="champ__etiquette">Formats souhaités</span>
                <div class="texte-petit"><?= $formats !== [] ? e(implode(', ', $formats)) : '—' ?></div>
            </div>
            <div class="champ pleine-largeur">
                <span class="champ__etiquette">Message</span>
                <p style="margin:0"><?= $demande['message'] !== '' ? e((string) $demande['message']) : '—' ?></p>
            </div>
            <div class="champ pleine-largeur">
                <span class="champ__etiquette">Reçue le</span>
                <div class="texte-petit"><?= e(date_fr((string) $demande['cree_le'], true)) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="panneau ecart-haut--grand">
    <div class="panneau__entete">
        <h2 class="panneau__titre"><?= Icone::svg('reglages') ?> Suivi</h2>
    </div>
    <div class="panneau__corps">
        <form method="post" action="<?= e(url('admin/demandes/' . $demande['id'])) ?>">
            <?= Csrf::champ() ?>
            <div class="pile">
                <div class="champ" style="margin:0">
                    <label class="champ__etiquette" for="statut">Statut de la demande</label>
                    <select class="liste" id="statut" name="statut">
                        <?php foreach ($statuts as $valeur): ?>
                            <option value="<?= e($valeur) ?>"<?= (string) $demande['statut'] === $valeur ? ' selected' : '' ?>>
                                <?= e(ETATS_LIBELLES[$valeur] ?? $valeur) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn--primaire">
                    <?= Icone::svg('valider') ?> Mettre à jour
                </button>
            </div>
        </form>
    </div>
</div>
