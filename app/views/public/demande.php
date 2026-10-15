<?php
/**
 * Vue — Formulaire public de demande de proposition (prospect).
 *
 * Variables :
 *   $villes  array<string>   — villes couvertes par le parc
 *   $formats array<string>   — formats de panneaux disponibles
 *   $valeurs array           — valeurs saisies (repli en cas d’erreur)
 *   $erreurs array<string>   — messages d’erreur par champ
 */

declare(strict_types=1);

use Souriane\Core\Csrf;
use Souriane\Core\Flash;

$villes  = $villes ?? [];
$formats = $formats ?? [];
$valeurs = $valeurs ?? [];
$erreurs = $erreurs ?? [];

$messages = Flash::recuperer();

$valeurs['formats'] = $valeurs['formats'] ?? [];

/** Récupère une valeur saisie ou une chaîne vide. */
function valeur(array $v, string $cle): string
{
    return is_string($v[$cle] ?? null) ? $v[$cle] : '';
}
?>
<section class="section section--serree section--teinte">
    <div class="conteneur conteneur-etroit">
        <span class="entete-section__surtitre">Pour les annonceurs</span>
        <h1>Demande de proposition</h1>
        <p style="color:var(--gris-600);font-size:1.05rem;max-width:64ch">
            Décrivez votre projet d’affichage extérieur. La régie étudie votre demande
            et revient vers vous avec une proposition chiffrée, sans engagement.
        </p>
    </div>
</section>

<section class="section">
    <div class="conteneur conteneur-etroit">
        <?php foreach ($messages as $m): ?>
            <div class="message message--<?= e($m['type']) ?>" role="status">
                <?= e($m['message']) ?>
            </div>
        <?php endforeach; ?>

        <?php if ($erreurs !== []): ?>
            <div class="message message--erreur" role="alert">
                Le formulaire comporte des erreurs. Corrigez les champs signalés ci-dessous.
            </div>
        <?php endif; ?>

        <form class="formulaire" method="post" action="<?= e(url('annonceurs/demande')) ?>" novalidate>
            <?= Csrf::champ() ?>

            <div class="formulaire__groupe">
                <h2 class="formulaire__titre">Votre enseigne</h2>
                <div class="grille-champs">
                    <div class="champ">
                        <label class="champ__etiquette" for="enseigne">Enseigne / entreprise <span class="requis">*</span></label>
                        <input class="saisie" type="text" id="enseigne" name="enseigne" required maxlength="180"
                               value="<?= e(valeur($valeurs, 'enseigne')) ?>" aria-describedby="err-enseigne">
                        <?php if (isset($erreurs['enseigne'])): ?>
                            <p class="champ__erreur" id="err-enseigne"><?= e($erreurs['enseigne']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="champ">
                        <label class="champ__etiquette" for="contact">Personne à contacter <span class="requis">*</span></label>
                        <input class="saisie" type="text" id="contact" name="contact" required maxlength="180"
                               value="<?= e(valeur($valeurs, 'contact')) ?>" aria-describedby="err-contact">
                        <?php if (isset($erreurs['contact'])): ?>
                            <p class="champ__erreur" id="err-contact"><?= e($erreurs['contact']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="champ">
                        <label class="champ__etiquette" for="email">Adresse e-mail <span class="requis">*</span></label>
                        <input class="saisie" type="email" id="email" name="email" required maxlength="180"
                               value="<?= e(valeur($valeurs, 'email')) ?>" placeholder="nom@entreprise.sn" aria-describedby="err-email">
                        <?php if (isset($erreurs['email'])): ?>
                            <p class="champ__erreur" id="err-email"><?= e($erreurs['email']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="champ">
                        <label class="champ__etiquette" for="telephone">Téléphone <span class="champ__aide">(facultatif)</span></label>
                        <input class="saisie" type="tel" id="telephone" name="telephone" maxlength="40"
                               value="<?= e(valeur($valeurs, 'telephone')) ?>" placeholder="+221 77 123 45 67">
                    </div>
                </div>
            </div>

            <div class="formulaire__groupe">
                <h2 class="formulaire__titre">Votre projet</h2>
                <div class="grille-champs">
                    <div class="champ">
                        <label class="champ__etiquette" for="ville">Ville visée <span class="requis">*</span></label>
                        <select class="liste" id="ville" name="ville" required aria-describedby="err-ville">
                            <option value="">— Choisir une ville —</option>
                            <?php foreach ($villes as $ville): ?>
                                <option value="<?= e($ville) ?>"<?= valeur($valeurs, 'ville') === $ville ? ' selected' : '' ?>>
                                    <?= e($ville) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($erreurs['ville'])): ?>
                            <p class="champ__erreur" id="err-ville"><?= e($erreurs['ville']) ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if ($formats !== []): ?>
                        <div class="champ pleine-largeur">
                            <span class="champ__etiquette">Formats souhaités <span class="champ__aide">(facultatif)</span></span>
                            <div class="choix">
                                <?php foreach ($formats as $format): ?>
                                    <label class="choix__element">
                                        <input type="checkbox" name="formats[]" value="<?= e($format) ?>"
                                            <?= in_array($format, $valeurs['formats'], true) ? ' checked' : '' ?>>
                                        <span><?= e($format) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="champ">
                        <label class="champ__etiquette" for="periode">Période souhaitée <span class="champ__aide">(facultatif)</span></label>
                        <input class="saisie" type="text" id="periode" name="periode" maxlength="120"
                               value="<?= e(valeur($valeurs, 'periode')) ?>" placeholder="ex. Février 2026, 3 semaines">
                    </div>
                    <div class="champ">
                        <label class="champ__etiquette" for="budget">Enveloppe envisagée <span class="champ__aide">(facultatif)</span></label>
                        <input class="saisie" type="text" id="budget" name="budget" maxlength="80"
                               value="<?= e(valeur($valeurs, 'budget')) ?>" placeholder="ex. 500 000 FCFA">
                    </div>
                    <div class="champ pleine-largeur">
                        <label class="champ__etiquette" for="message">Précisions sur votre projet <span class="champ__aide">(facultatif)</span></label>
                        <textarea class="zone-texte" id="message" name="message" rows="4" maxlength="2000"><?= e(valeur($valeurs, 'message')) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="pile">
                <button type="submit" class="btn btn--primaire">
                    <?= \Souriane\Core\Icone::svg('envoyer') ?>
                    Envoyer ma demande
                </button>
                <a class="btn btn--contour" href="<?= e(url('annonceurs')) ?>">En savoir plus</a>
            </div>
        </form>
    </div>
</section>
