/**
 * SOURIANE — Génération des icônes de l'application à partir du logo.
 *
 * Le tracé du « S » est repris tel quel depuis app/views/partials/logo.php :
 * la marque est ainsi identique au pixel près entre le site et l'icône.
 *
 * Usage :
 *   node tests/generer_icones.js
 *
 * Produit dans assets/img/ :
 *   favicon.png, favicon.ico
 *   icons/icone-192.png, icons/icone-512.png
 *   icons/icone-maskable-512.png, icons/apple-touch-icon.png
 */

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const RACINE = path.resolve(__dirname, '..');
const DOSSIER_IMG = path.join(RACINE, 'assets', 'img');
const DOSSIER_ICONES = path.join(DOSSIER_IMG, 'icons');

// Charte : violet profond, lilas, or
const VIOLET = '#4C1D95';
const VIOLET_FONCE = '#2E1065';
const VIOLET_CLAIR = '#5B21B6';

/** Tracé de la marque, partagé avec le gabarit PHP. */
const MARQUE = `
    <g fill="currentColor">
        <path d="M2.00,150.00A58.67,58.67 0 0 0 97.12,79.12Z"/>
        <path d="M25.75,112.75A58.58,58.58 0 0 1 119.88,41.88Z"/>
        <circle cx="97.90" cy="176.06" r="13.85"/>
        <circle cx="23.45" cy="15.70" r="13.84"/>
    </g>`;

/**
 * Construit une page HTML contenant la marque, centrée dans un carré.
 *
 * @param {object} o
 * @param {number} o.taille    Côté du carré en pixels.
 * @param {string} o.couleur   Couleur du tracé.
 * @param {string|null} o.fond Fond du carré (null = transparent).
 * @param {number} o.hauteur   Hauteur du tracé, en fraction du côté.
 */
function page({ taille, couleur, fond, hauteur }) {
    const h = taille * hauteur;
    const l = h * (122 / 192); // la marque est plus haute que large

    return `<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        html, body { margin: 0; padding: 0; }
        .cadre {
            width: ${taille}px; height: ${taille}px;
            display: grid; place-items: center;
            background: ${fond === null ? 'transparent' : fond};
        }
        svg { width: ${l}px; height: ${h}px; display: block; color: ${couleur}; }
    </style></head><body>
        <div class="cadre"><svg viewBox="0 0 122 192">${MARQUE}</svg></div>
    </body></html>`;
}

/** Rend une page et renvoie le PNG sous forme de tampon. */
async function rendre(navigateur, options) {
    const onglet = await navigateur.newPage({
        viewport: { width: options.taille, height: options.taille },
        deviceScaleFactor: 1,
    });
    await onglet.setContent(page(options), { waitUntil: 'load' });
    const tampon = await onglet.screenshot({
        omitBackground: options.fond === null,
        clip: { x: 0, y: 0, width: options.taille, height: options.taille },
    });
    await onglet.close();

    return tampon;
}

/**
 * Assemble un fichier .ico à partir de plusieurs PNG.
 * Format ICONDIR + ICONDIRENTRY (16 octets par image) + données PNG.
 */
function assemblerIco(images) {
    const entete = Buffer.alloc(6);
    entete.writeUInt16LE(0, 0); // réservé
    entete.writeUInt16LE(1, 2); // type 1 = icône
    entete.writeUInt16LE(images.length, 4);

    const repertoire = Buffer.alloc(16 * images.length);
    let decalage = 6 + repertoire.length;

    images.forEach((image, i) => {
        const base = i * 16;
        repertoire.writeUInt8(image.taille >= 256 ? 0 : image.taille, base + 0);
        repertoire.writeUInt8(image.taille >= 256 ? 0 : image.taille, base + 1);
        repertoire.writeUInt8(0, base + 2);  // nombre de couleurs
        repertoire.writeUInt8(0, base + 3);  // réservé
        repertoire.writeUInt16LE(1, base + 4);   // plans
        repertoire.writeUInt16LE(32, base + 6);  // bits par pixel
        repertoire.writeUInt32LE(image.donnees.length, base + 8);
        repertoire.writeUInt32LE(decalage, base + 12);
        decalage += image.donnees.length;
    });

    return Buffer.concat([entete, repertoire, ...images.map((i) => i.donnees)]);
}

(async () => {
    const navigateur = await chromium.launch();

    const cibles = [
        // Icônes « any » : marque violette sur fond blanc
        {
            fichier: path.join(DOSSIER_ICONES, 'icone-192.png'),
            taille: 192, couleur: VIOLET, fond: '#FFFFFF', hauteur: 0.66,
        },
        {
            fichier: path.join(DOSSIER_ICONES, 'icone-512.png'),
            taille: 512, couleur: VIOLET, fond: '#FFFFFF', hauteur: 0.66,
        },
        // Icône maskable : fond violet pleine surface, marque blanche recentrée
        // dans la zone sûre (le système peut rogner jusqu'à 20 % sur les bords).
        {
            fichier: path.join(DOSSIER_ICONES, 'icone-maskable-512.png'),
            taille: 512, couleur: '#FFFFFF',
            fond: `linear-gradient(150deg, ${VIOLET} 0%, ${VIOLET_FONCE} 100%)`,
            hauteur: 0.46,
        },
        // iOS n'accepte pas la transparence : fond blanc opaque
        {
            fichier: path.join(DOSSIER_ICONES, 'apple-touch-icon.png'),
            taille: 180, couleur: VIOLET, fond: '#FFFFFF', hauteur: 0.68,
        },
        // Favicon
        {
            fichier: path.join(DOSSIER_IMG, 'favicon.png'),
            taille: 64, couleur: VIOLET, fond: '#FFFFFF', hauteur: 0.72,
        },
    ];

    for (const cible of cibles) {
        const donnees = await rendre(navigateur, cible);
        fs.writeFileSync(cible.fichier, donnees);
        console.log('✓', path.relative(RACINE, cible.fichier), `(${cible.taille}px, ${donnees.length} o)`);
    }

    // .ico multi-résolution
    const taillesIco = [16, 32, 48];
    const images = [];
    for (const taille of taillesIco) {
        images.push({
            taille,
            donnees: await rendre(navigateur, {
                taille, couleur: VIOLET, fond: '#FFFFFF', hauteur: 0.78,
            }),
        });
    }
    const ico = assemblerIco(images);
    fs.writeFileSync(path.join(DOSSIER_IMG, 'favicon.ico'), ico);
    console.log('✓', path.relative(RACINE, path.join(DOSSIER_IMG, 'favicon.ico')),
        `(${taillesIco.join('/')}px, ${ico.length} o)`);

    // Version or, conservée pour les fonds violets (favicon d'administration)
    const or = await rendre(navigateur, {
        taille: 512, couleur: '#FBBF24', fond: null, hauteur: 0.66,
    });
    fs.writeFileSync(path.join(DOSSIER_ICONES, 'icone-or-512.png'), or);
    console.log('✓', path.relative(RACINE, path.join(DOSSIER_ICONES, 'icone-or-512.png')),
        `(512px, ${or.length} o)`);

    // Logos détachés, déclinés sur les trois fonds de la charte
    const logos = [
        ['logo-marque-violet.png', VIOLET, null],
        ['logo-marque-blanc.png', '#FFFFFF', null],
        ['logo-marque-or.png', '#FBBF24', null],
    ];
    for (const [nom, couleur, fond] of logos) {
        const donnees = await rendre(navigateur, {
            taille: 512, couleur, fond, hauteur: 0.9,
        });
        fs.writeFileSync(path.join(DOSSIER_IMG, nom), donnees);
        console.log('✓', path.relative(RACINE, path.join(DOSSIER_IMG, nom)),
            `(512px, ${donnees.length} o)`);
    }

    await navigateur.close();
    console.log('\nIcônes régénérées dans la charte violette.');
})().catch((e) => {
    console.error('Échec :', e.message);
    process.exit(1);
});
