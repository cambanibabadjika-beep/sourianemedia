/**
 * SOURIANE — Captures d'écran du site public et du back-office.
 *
 * Usage : node tests/capturer_site.js [dossier-de-sortie]
 */

const { chromium } = require('playwright');
const crypto = require('crypto');
const os = require('os');
const path = require('path');

const BASE = 'http://localhost/sourianemedia';
const SORTIE = process.argv[2] || os.tmpdir();

/**
 * Clé partagée des comptes de démonstration, telle que la pose
 * cron/preparer_2fa_demo.php — au même titre que le mot de passe de
 * démonstration, saisi en clair quelques lignes plus bas.
 */
const SECRET_DEMO = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

/**
 * Code à deux facteurs (RFC 6238), avec les mêmes paramètres que le site :
 * SHA-1, six chiffres, pas de trente secondes.
 *
 * Le recalculer ici plutôt que de le demander au site est délibéré : un
 * navigateur n'a aucune raison de savoir produire ce code, et c'est bien la
 * preuve que l'implémentation est interopérable avec une application
 * d'authentification ordinaire.
 */
function code2fa(secretBase32, horodatage = Math.floor(Date.now() / 1000)) {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  const propre = String(secretBase32).toUpperCase().replace(/[^A-Z2-7]/g, '');

  let bits = '';
  for (const caractere of propre) {
    bits += alphabet.indexOf(caractere).toString(2).padStart(5, '0');
  }

  const octets = [];
  for (let i = 0; i + 8 <= bits.length; i += 8) {
    octets.push(parseInt(bits.slice(i, i + 8), 2));
  }

  const pas = Math.floor(horodatage / 30);
  const compteur = Buffer.alloc(8);
  compteur.writeUInt32BE(Math.floor(pas / 2 ** 32), 0);
  compteur.writeUInt32BE(pas >>> 0, 4);

  const empreinte = crypto
    .createHmac('sha1', Buffer.from(octets))
    .update(compteur)
    .digest();

  const offset = empreinte[19] & 0x0f;
  const binaire =
    ((empreinte[offset] & 0x7f) << 24) |
    ((empreinte[offset + 1] & 0xff) << 16) |
    ((empreinte[offset + 2] & 0xff) << 8) |
    (empreinte[offset + 3] & 0xff);

  return String(binaire % 1000000).padStart(6, '0');
}

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
    const fichier = path.join(SORTIE, `souriane-${nom}.png`);
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
  await page.screenshot({ path: path.join(SORTIE, 'souriane-public-annonceur.png'), fullPage: true });
  console.log('capture :', path.join(SORTIE, 'souriane-public-annonceur.png'));

  // La feuille s'annonce après 8 s ou 40 % de défilement. On déclenche le
  // défilement : la feuille s'ouvre d'elle-même, il n'y a plus qu'à capturer.
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight * 0.55));
  const feuille = await page.waitForSelector('.feuille--ouverte', { timeout: 10000 }).catch(() => null);

  if (feuille) {
    await page.waitForTimeout(900);
    await page.screenshot({ path: path.join(SORTIE, 'souriane-public-feuille.png') });
    console.log('capture :', path.join(SORTIE, 'souriane-public-feuille.png'));
  } else {
    console.log('note : la feuille « Rester informé » ne s’est pas ouverte');
  }

  // --- Back-office --------------------------------------------------------
  await page.goto(BASE + '/admin/connexion', { waitUntil: 'domcontentloaded' });
  await page.screenshot({ path: path.join(SORTIE, 'souriane-admin-connexion.png') });
  console.log('capture :', path.join(SORTIE, 'souriane-admin-connexion.png'));

  await page.fill('#email', 'admin@souriane.sn');
  await page.fill('#mot_de_passe', 'Souriane2026!');

  await Promise.all([
    page.waitForURL((u) => !u.pathname.endsWith('/admin/connexion'), { timeout: 15000 }).catch(() => {}),
    page.click('button[type="submit"]'),
  ]);

  // Second facteur (ch. 8) : le compte d'administration est protégé, le mot
  // de passe seul n'ouvre plus la session. On franchit l'écran de code comme
  // le ferait l'utilisateur.
  if (page.url().includes('/admin/connexion/code')) {
    await page.waitForSelector('#code', { timeout: 10000 });
    await page.waitForTimeout(400);
    await page.screenshot({ path: path.join(SORTIE, 'souriane-admin-2fa.png') });
    console.log('capture :', path.join(SORTIE, 'souriane-admin-2fa.png'));

    await page.fill('#code', code2fa(SECRET_DEMO));
    await Promise.all([
      page.waitForURL('**/admin', { timeout: 15000 }),
      page.click('button[type="submit"]'),
    ]);
  } else {
    console.log('note : le second facteur n’a pas été demandé — compte non enrôlé ?');
  }

  await capturer('/admin', 'admin-tableau-de-bord');
  await capturer('/admin/codes', 'admin-codes');
  await capturer('/admin/securite', 'admin-securite');

  await navigateur.close();
})().catch((e) => {
  console.error('échec :', e.message);
  process.exit(1);
});
