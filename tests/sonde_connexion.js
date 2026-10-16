/**
 * Sonde de diagnostic — écran de connexion en mode sombre.
 *
 * Rend la page, relève les couleurs réellement calculées (fond de la carte,
 * couleur du logo, lien de retour) et calcule le ratio de contraste WCAG du
 * lien de retour contre le fond réellement composité de la carte. Produit
 * une capture.
 *
 * Sert à prouver un défaut avant de le corriger, puis à vérifier la
 * correction. La capture seule ne suffit pas : un lien lisible à l'œil peut
 * rester sous 4.5:1, et un lien invisible peut ne pas se voir sur une image
 * réduite. Le ratio, lui, ne triche pas.
 *
 * Usage : node tests/sonde_connexion.js [url] [sortie.png]
 */

const { chromium } = require('playwright');
const path = require('path');

const URL_PAGE = process.argv[2] || 'http://localhost/sourianemedia/admin/connexion?theme=sombre';
const SORTIE   = process.argv[3] || path.join(process.cwd(), 'connexion-sombre.png');

/**
 * Analyse une couleur CSS « rgb(...) / rgba(...) » en [r, g, b, a].
 * Renvoie null si la chaîne n'est pas exploitable.
 */
function analyser(texte) {
    const m = String(texte).match(/rgba?\(([^)]+)\)/);
    if (!m) return null;
    const parts = m[1].split(',').map((v) => parseFloat(v.trim()));
    return [parts[0], parts[1], parts[2], parts.length > 3 ? parts[3] : 1];
}

/** Compose une couleur semi-transparente sur un fond opaque. */
function composer(couleur, fond) {
    const a = couleur[3];
    return [
        Math.round(couleur[0] * a + fond[0] * (1 - a)),
        Math.round(couleur[1] * a + fond[1] * (1 - a)),
        Math.round(couleur[2] * a + fond[2] * (1 - a)),
        1,
    ];
}

/** Luminance relative WCAG 2.1. */
function luminance(c) {
    const canal = (v) => {
        v /= 255;
        return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * canal(c[0]) + 0.7152 * canal(c[1]) + 0.0722 * canal(c[2]);
}

/** Ratio de contraste entre deux couleurs opaques. */
function contraste(a, b) {
    const la = luminance(a);
    const lb = luminance(b);
    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
}

(async () => {
    const navigateur = await chromium.launch();
    const page = await navigateur.newPage({ viewport: { width: 720, height: 900 } });

    await page.goto(URL_PAGE, { waitUntil: 'networkidle' });

    const theme = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));

    const releves = await page.evaluate(() => {
        const lire = (sel) => {
            const el = document.querySelector(sel);
            if (!el) return { selecteur: sel, absent: true };
            const cs = getComputedStyle(el);
            return {
                selecteur: sel,
                fond: cs.backgroundColor,
                couleur: cs.color,
                remplissage: cs.fill,
            };
        };
        return [
            lire('.connexion-page'),
            lire('.connexion-carte'),
            lire('.connexion-carte__marque'),
            lire('.connexion-carte .logo__marque'),
            lire('.connexion-carte .logo__mot'),
            lire('.connexion-carte h1'),
            lire('.connexion-carte__intro'),
            lire('.connexion-carte .champ__etiquette'),
            lire('.connexion-carte .saisie'),
            lire('.connexion-carte .texte-petit a'),
        ];
    });

    console.log('data-theme =', theme);
    console.log('');
    for (const r of releves) {
        if (r.absent) { console.log(r.selecteur.padEnd(38), '(absent)'); continue; }
        console.log(
            r.selecteur.padEnd(38),
            'fond=' + (r.fond || '-').padEnd(24),
            'texte=' + (r.couleur || '-').padEnd(22),
            'fill=' + (r.remplissage || '-')
        );
    }

    // --- Contraste réel du lien de retour sur le fond composité de la carte ---
    const donnees = await page.evaluate(() => {
        const carte = document.querySelector('.connexion-carte');
        const lien  = document.querySelector('.connexion-carte .texte-petit a');
        if (!carte || !lien) return null;
        // Remonte les fonds jusqu'à en trouver un opaque, en compositant.
        const fonds = [];
        let el = carte;
        while (el) {
            const bg = getComputedStyle(el).backgroundColor;
            if (bg && bg !== 'rgba(0, 0, 0, 0)') fonds.push(bg);
            el = el.parentElement;
        }
        return { fonds, couleur: getComputedStyle(lien).color };
    });

    if (donnees) {
        // Le fond le plus profond d'abord (body), on compose vers le haut.
        const pile = donnees.fonds.map(analyser).filter(Boolean).reverse();
        let surface = [255, 255, 255, 1];
        for (const c of pile) surface = composer(c, surface);
        const encre = composer(analyser(donnees.couleur), surface);
        const ratio = contraste(encre, surface);
        const seuil = 4.5;
        console.log('');
        console.log('Lien « Retour au site public »');
        console.log('  couleur    :', donnees.couleur);
        console.log('  surface    :', 'rgb(' + surface.slice(0, 3).join(', ') + ')');
        console.log('  contraste  :', ratio.toFixed(2) + ':1');
        console.log('  verdict    :', ratio >= seuil ? 'CONFORME (>= 4.5:1)' : 'NON CONFORME (< 4.5:1)');
    }

    const carte = await page.$('.connexion-carte');
    if (carte) {
        await carte.screenshot({ path: SORTIE });
        console.log('\nCapture carte -> ' + SORTIE);
    }

    await navigateur.close();
})();
