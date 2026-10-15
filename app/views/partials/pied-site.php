<?php
/**
 * Partial — Pied de page du site public.
 */

declare(strict_types=1);

$annee = date('Y');
?>
<footer class="pied-site">
    <div class="conteneur">
        <div class="pied-site__grille">
            <div>
                <?php
                $variante    = 'blanc';
                $taille      = '';
                $lien        = true;
                $destination = url('/');
                require __DIR__ . '/logo.php';
                ?>
                <p style="margin-top:16px;max-width:38ch"><?= e(APP_SLOGAN) ?> — la régie d’affichage extérieur qui relie chaque panneau à son audience.</p>
            </div>

            <div>
                <h4>La plateforme</h4>
                <ul>
                    <li><a href="<?= e(url('comment-ca-marche')) ?>">Comment ça marche</a></li>
                    <li><a href="<?= e(url('annonceurs')) ?>">Devenir annonceur</a></li>
                    <li><a href="<?= e(url('le-parc')) ?>">Notre parc de panneaux</a></li>
                    <li><a href="<?= e(url('admin/connexion')) ?>">Espace régie</a></li>
                </ul>
            </div>

            <div>
                <h4>Informations</h4>
                <ul>
                    <li><a href="<?= e(url('mentions-legales')) ?>">Mentions légales</a></li>
                    <li><a href="<?= e(url('confidentialite')) ?>">Confidentialité des données</a></li>
                    <li><a href="<?= e(url('accessibilite')) ?>">Accessibilité</a></li>
                    <?php if (WHATSAPP_REGIE !== ''): ?>
                        <li><a href="<?= e(whatsapp_lien(WHATSAPP_REGIE, 'Bonjour Souriane, je souhaite un renseignement.')) ?>" rel="noopener">WhatsApp régie</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="pied-site__bas">
            <span>© <?= e($annee) ?> <?= e(APP_NAME) ?>. Tous droits réservés.</span>
            <span>Plateforme de publicité extérieure connectée · Sénégal</span>
        </div>
    </div>
</footer>
