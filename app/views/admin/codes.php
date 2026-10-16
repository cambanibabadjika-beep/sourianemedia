<?php
/**
 * Vue — QR codes et codes courts.
 *
 * Variables : $codes, $campagnes, $campagneId
 */

declare(strict_types=1);

use Souriane\Core\Csrf;
use Souriane\Core\Icone;
use Souriane\Services\QrService;

$totalScans = 0;
foreach ($codes as $code) {
    $totalScans += (int) $code['scans'];
}
?>

<div class="panneau">
    <form class="filtres" method="get" action="<?= e(url('admin/codes')) ?>">
        <div class="champ">
            <label class="champ__etiquette" for="campagne">Campagne</label>
            <select class="liste" id="campagne" name="campagne" onchange="this.form.submit()">
                <option value="">Toutes les campagnes</option>
                <?php foreach ($campagnes as $campagne): ?>
                    <option value="<?= e((string) $campagne['id']) ?>"<?= (int) $campagne['id'] === (int) $campagneId ? ' selected' : '' ?>>
                        <?= e((string) $campagne['nom']) ?> — <?= e((string) $campagne['annonceur_nom']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button type="submit" class="btn btn--primaire btn--petit">Filtrer</button></noscript>
    </form>

    <div class="panneau__entete">
        <h2 class="panneau__titre"><?= Icone::svg('qr') ?> Codes actifs</h2>
        <span class="texte-petit">
            <?= e((string) count($codes)) ?> code(s) · <?= e(number_format((float) $totalScans, 0, ',', ' ')) ?> scan(s)
        </span>
    </div>

    <?php if ($codes === []): ?>
        <div class="panneau__corps">
            <div class="etat-vide">
                <?= Icone::svg('qr') ?>
                <h3>Aucun code pour ce filtre</h3>
                <p>Les codes sont générés automatiquement lors de la réservation d’une campagne, un par panneau.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="panneau__corps">
            <div class="grille-qr">
                <?php foreach ($codes as $code): ?>
                    <div class="carte-qr">
                        <div class="carte-qr__code"><?= e(strtoupper((string) $code['code'])) ?></div>
                        <div class="carte-qr__image">
                            <?= QrService::svg(url_courte((string) $code['code']), 'H', 4, 4, '#4C1D95') ?>
                        </div>
                        <div class="carte-qr__pied">
                            <?= e((string) $code['campagne_nom']) ?><br>
                            Panneau <?= e((string) $code['panneau_reference']) ?> · <?= e((string) ($code['ville'] ?? '')) ?>
                        </div>
                        <div class="carte-qr__secours"><?= e((string) ($code['code_secours'] ?? '')) ?></div>
                        <div class="carte-qr__pied">
                            <?= e((string) $code['scans']) ?> scan(s) ·
                            <?= e((string) $code['scans_session']) ?> visite(s)
                        </div>

                        <!-- Destination du code (EF-C03, AC-03).
                             Le code court encodé dans le visuel ne change pas :
                             seule la cible de la redirection est modifiée, ce
                             qui évite de réimprimer l’affiche. -->
                        <form class="carte-qr__destination" method="post"
                              action="<?= e(url('admin/codes/' . $code['id'] . '/destination')) ?>">
                            <?= Csrf::champ() ?>
                            <label class="sr-only" for="destination-<?= e((string) $code['id']) ?>">
                                Destination du code <?= e((string) $code['code']) ?>
                            </label>
                            <input class="saisie saisie--petit" type="text"
                                   id="destination-<?= e((string) $code['id']) ?>"
                                   name="destination" maxlength="255"
                                   value="<?= e((string) ($code['destination'] ?? '')) ?>"
                                   placeholder="Page de l’annonceur (par défaut)">
                            <button type="submit" class="btn btn--contour btn--petit">
                                <?= Icone::svg('valider') ?> Rediriger
                            </button>
                        </form>
                        <div class="carte-qr__pied">
                            Mène vers :
                            <code><?= e(\Souriane\Models\CodeCourt::destination($code)) ?></code>
                        </div>

                        <div class="pile" style="justify-content:center;margin-top:12px">
                            <a class="btn btn--contour btn--petit" href="<?= e(url('admin/codes/' . $code['id'] . '/qr.svg')) ?>"
                               download="qr-<?= e((string) $code['code']) ?>.svg">
                                <?= Icone::svg('telecharger') ?> SVG
                            </a>
                            <a class="btn btn--contour btn--petit" target="_blank" rel="noopener"
                               href="<?= e(url('admin/codes/' . $code['id'] . '/affiche')) ?>">
                                <?= Icone::svg('panneau') ?> Affiche
                            </a>
                            <a class="btn btn--contour btn--petit" target="_blank" rel="noopener"
                               href="<?= e(url_courte((string) $code['code'])) ?>">
                                <?= Icone::svg('lien-externe') ?> Tester
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="panneau">
    <div class="panneau__entete">
        <h2 class="panneau__titre"><?= Icone::svg('ajouter') ?> Générer les codes manquants</h2>
    </div>
    <div class="panneau__corps">
        <form method="post" action="<?= e(url('admin/codes/generer')) ?>" class="pile">
            <?= Csrf::champ() ?>
            <select class="liste" name="campagne_id" required style="min-width:300px">
                <option value="">— Choisir une campagne —</option>
                <?php foreach ($campagnes as $campagne): ?>
                    <option value="<?= e((string) $campagne['id']) ?>"<?= (int) $campagne['id'] === (int) $campagneId ? ' selected' : '' ?>>
                        <?= e((string) $campagne['nom']) ?> (<?= e((string) $campagne['nb_codes']) ?>/<?= e((string) $campagne['nb_panneaux']) ?> codes)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn--primaire btn--petit">
                <?= Icone::svg('qr') ?> Générer
            </button>
        </form>

        <div class="encart ecart-haut" style="margin-bottom:0">
            <strong>Règles d’impression.</strong> Un code par couple campagne × panneau : un même visuel posé sur
            deux supports porte deux codes différents, ce qui permet d’attribuer chaque scan au bon emplacement.
            Niveau de correction H (30 % de redondance), marge de silence de 4 modules, contraste sombre sur fond clair.
            La taille d’impression se calcule en divisant la distance de lecture par dix.
        </div>
    </div>
</div>
