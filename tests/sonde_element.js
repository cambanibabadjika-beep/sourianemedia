/**
 * Sonde ciblee — chaine d'ancetres et fonds effectifs d'un element.
 *
 * Usage : node tests/sonde_element.js <url> <selecteur>
 * Exemple : node tests/sonde_element.js "http://localhost/sourianemedia/le-parc?theme=sombre" ".leaflet-control-attribution a"
 */

const { chromium } = require('playwright');

const URL_PAGE = process.argv[2];
const SELECTEUR = process.argv[3];

if (!URL_PAGE || !SELECTEUR) {
    console.error('Usage : node tests/sonde_element.js <url> <selecteur>');
    process.exit(1);
}

(async () => {
    const navigateur = await chromium.launch();
    const page = await navigateur.newPage({ viewport: { width: 1280, height: 900 } });
    await page.goto(URL_PAGE, { waitUntil: 'networkidle', timeout: 20000 });
    await page.waitForTimeout(500);

    const infos = await page.evaluate((sel) => {
        const el = document.querySelector(sel);
        if (!el) return { absent: true };

        const chaine = [];
        let noeud = el;
        while (noeud && noeud.nodeType === 1) {
            const cs = getComputedStyle(noeud);
            chaine.push({
                balise: noeud.tagName.toLowerCase(),
                classe: typeof noeud.className === 'string' ? noeud.className : '',
                fond: cs.backgroundColor,
                couleur: cs.color,
                opacite: cs.opacity,
            });
            noeud = noeud.parentElement;
        }

        const cs = getComputedStyle(el);
        return {
            absent: false,
            couleur: cs.color,
            remplissageTexte: cs.webkitTextFillColor || cs.getPropertyValue('-webkit-text-fill-color'),
            fondPropre: cs.backgroundColor,
            chaine,
        };
    }, SELECTEUR);

    if (infos.absent) {
        console.log('Element introuvable :', SELECTEUR);
    } else {
        console.log('couleur            =', infos.couleur);
        console.log('text-fill-color    =', infos.remplissageTexte);
        console.log('fond propre        =', infos.fondPropre);
        console.log('\nChaine d ancetres (du plus proche au plus lointain) :');
        for (const c of infos.chaine) {
            console.log(`  <${c.balise}> ${c.classe}`.padEnd(52), 'fond=' + c.fond);
        }
    }

    await navigateur.close();
})();
