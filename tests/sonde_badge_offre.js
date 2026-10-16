/**
 * Capture jetable — badge « Expirée » du formulaire d'offre (proposition C1).
 *
 * Se connecte à la régie, ouvre le formulaire d'une page, puis photographie
 * le panneau « 1 · Offre en cours ». Sert de preuve visuelle : le badge et le
 * rappel de conséquence ne se lisent pas dans un diff.
 *
 * Usage : node tests/sonde_badge_offre.js <id_page> <sortie.png>
 */

const { chromium } = require('playwright');
const path = require('path');

const BASE   = 'http://localhost/sourianemedia';
const ID     = process.argv[2] || '17';
const SORTIE = process.argv[3] || path.join(process.cwd(), 'badge-expiree.png');

(async () => {
    const navigateur = await chromium.launch();
    const page = await navigateur.newPage({
        viewport: { width: 1100, height: 950 },
        colorScheme: 'dark',
    });

    await page.goto(BASE + '/admin/connexion', { waitUntil: 'networkidle' });
    await page.fill('#email', 'admin@souriane.sn');
    await page.fill('#mot_de_passe', 'Souriane2026!');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button[type="submit"]'),
    ]);
    console.log('connecté ->', page.url());

    await page.goto(BASE + '/admin/pages/' + ID, { waitUntil: 'networkidle' });

    const badge = await page.evaluate(() => {
        const el = document.querySelector('.etat--expire');
        return el ? el.textContent.trim() : null;
    });
    console.log('badge relevé =', badge);

    const panneau = await page.evaluateHandle(() => {
        const el = document.querySelector('.etat--expire');
        return el ? el.closest('.panneau') : null;
    });
    const element = panneau.asElement();
    if (!element) {
        console.log('panneau de l’offre introuvable');
        await navigateur.close();
        process.exit(1);
    }

    await element.screenshot({ path: SORTIE });
    console.log('Capture -> ' + SORTIE);

    await navigateur.close();
})();
