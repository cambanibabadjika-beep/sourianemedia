/**
 * SOURIANE — Captures d'écran du site public et du back-office.
 *
 * Usage : node tests/capturer_site.js [dossier-de-sortie]
 */

const { chromium } = require('playwright');

const BASE = 'http://localhost/sourianemedia';
const SORTIE = process.argv[2] || 'C:/Users/khali/AppData/Local/Temp';

(async () => {
  const navigateur = await chromium.launch();
  const contexte = await navigateur.newContext({
    viewport: { width: 1440, height: 950 },
    deviceScaleFactor: 2,
    locale: 'fr-FR',
  });
  const page = await contexte.newPage();

  const capturer = async (chemin, nom, plein = true) => {
    await page.goto(BASE + chemin, { waitUntil: 'networkidle' });
    await page.waitForTimeout(700);
    const fichier = `${SORTIE}/souriane-${nom}.png`;
    await page.screenshot({ path: fichier, fullPage: plein });
    console.log('capture :', fichier);
  };

  // --- Site public --------------------------------------------------------
  await capturer('/', 'public-accueil');
  await capturer('/', 'public-heros', false);
  await capturer('/le-parc', 'public-parc');

  // --- Page annonceur, avec la feuille « Rester informé » ouverte ---------
  await page.goto(BASE + '/quincaillerie-bignona', { waitUntil: 'networkidle' });
  await page.waitForTimeout(700);
  await page.screenshot({ path: `${SORTIE}/souriane-public-annonceur.png`, fullPage: true });
  console.log('capture :', `${SORTIE}/souriane-public-annonceur.png`);

  // La feuille s'annonce après 8 s ou 40 % de défilement. On déclenche le
  // défilement : la feuille s'ouvre d'elle-même, il n'y a plus qu'à capturer.
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight * 0.55));
  const feuille = await page.waitForSelector('.feuille--ouverte', { timeout: 10000 }).catch(() => null);

  if (feuille) {
    await page.waitForTimeout(900);
    await page.screenshot({ path: `${SORTIE}/souriane-public-feuille.png` });
    console.log('capture :', `${SORTIE}/souriane-public-feuille.png`);
  } else {
    console.log('note : la feuille « Rester informé » ne s’est pas ouverte');
  }

  // --- Back-office --------------------------------------------------------
  await page.goto(BASE + '/admin/connexion', { waitUntil: 'domcontentloaded' });
  await page.screenshot({ path: `${SORTIE}/souriane-admin-connexion.png` });
  console.log('capture :', `${SORTIE}/souriane-admin-connexion.png`);

  await page.fill('#email', 'admin@souriane.sn');
  await page.fill('#mot_de_passe', 'Souriane2026!');
  await Promise.all([
    page.waitForURL('**/admin', { timeout: 15000 }),
    page.click('button[type="submit"]'),
  ]);

  await capturer('/admin', 'admin-tableau-de-bord');
  await capturer('/admin/codes', 'admin-codes');

  await navigateur.close();
})().catch((e) => {
  console.error('échec :', e.message);
  process.exit(1);
});
