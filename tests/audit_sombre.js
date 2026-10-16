/**
 * Audit du thème sombre — contraste réel de chaque élément visible.
 *
 * Parcourt les vingt pages du site (publiques et back-office) en thème sombre,
 * relève pour chaque élément porteur de texte ou de pictogramme sa couleur et
 * son fond effectif — en composant les couches translucides des ancêtres — puis
 * signale tout ce qui passe sous le seuil WCAG AA (4,5:1 pour le texte, 3:1
 * pour les éléments non textuels).
 *
 * Quatre pièges sont traités explicitement, faute de quoi l'audit produit des
 * faux positifs — et un faux positif coûte plus cher qu'un silence, puisqu'il
 * fait « corriger » du code sain :
 *   - le texte en dégradé (`-webkit-text-fill-color: transparent` + fond découpé
 *     sur le texte) n'est pas un défaut : il est ignoré ;
 *   - une icône ne se juge pas sur `color` mais sur les `fill`/`stroke`
 *     réellement posés sur ses formes (le QR code est violet sur blanc, les
 *     épingles de la carte ont leur propre palette) ;
 *   - un fond n'est pas toujours uni : les surfaces de verre du site sont des
 *     dégradés, et `backgroundColor` vaut alors « transparent » ;
 *   - les panneaux de la carte Leaflet sont posés sur des tuiles, c'est-à-dire
 *     sur des images : aucun fond CSS ne décrit ce qui s'y trouve. Ils sont donc
 *     comptés à part et déclarés hors périmètre, jamais passés sous silence.
 *
 * Usage : node tests/audit_sombre.js [sortie.json]
 *         node tests/audit_sombre.js --code    (affiche le TOTP courant, pour
 *         comparer l'implémentation JS à celle de app/core/Totp.php)
 */

const { chromium } = require('playwright');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const BASE = 'http://localhost/sourianemedia';

const COMPTE = {
    email: 'admin@souriane.sn',
    motDePasse: 'Souriane2026!',
    secret: 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
};

const PAGES = [
    { nom: 'accueil',          chemin: '/',                      auth: false },
    { nom: 'decouvrir',        chemin: '/decouvrir',             auth: false },
    { nom: 'le-parc',          chemin: '/le-parc',               auth: false },
    { nom: 'comment',          chemin: '/comment-ca-marche',     auth: false },
    { nom: 'demande',          chemin: '/demande',               auth: false },
    { nom: 'connexion',        chemin: '/admin/connexion',       auth: false },
    { nom: 'admin-bord',       chemin: '/admin',                 auth: true  },
    { nom: 'admin-annonceurs', chemin: '/admin/annonceurs',      auth: true  },
    { nom: 'admin-campagnes',  chemin: '/admin/campagnes',       auth: true  },
    { nom: 'admin-panneaux',   chemin: '/admin/panneaux',        auth: true  },
    { nom: 'admin-pages',      chemin: '/admin/pages',           auth: true  },
    { nom: 'admin-notifs',     chemin: '/admin/notifications',   auth: true  },
    { nom: 'admin-codes',      chemin: '/admin/codes',           auth: true  },
    { nom: 'admin-poses',      chemin: '/admin/poses',           auth: true  },
    { nom: 'admin-demande',    chemin: '/admin/demandes',        auth: true  },
    { nom: 'admin-journal',    chemin: '/admin/journal',         auth: true  },
    { nom: 'admin-stats',      chemin: '/admin/statistiques',    auth: true  },
    { nom: 'admin-securite',   chemin: '/admin/securite',        auth: true  },
    { nom: 'admin-params',     chemin: '/admin/parametres',      auth: true  },
    { nom: 'admin-utilis',     chemin: '/admin/utilisateurs',    auth: true  },
];

/** Base32 -> Buffer (RFC 4648, sans padding exige). */
function base32Decode(s) {
    const alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = 0, valeur = 0;
    const octets = [];
    for (const c of String(s).replace(/=+$/, '').toUpperCase()) {
        const i = alpha.indexOf(c);
        if (i === -1) continue;
        valeur = (valeur << 5) | i;
        bits += 5;
        if (bits >= 8) {
            octets.push((valeur >>> (bits - 8)) & 0xff);
            bits -= 8;
        }
    }
    return Buffer.from(octets);
}

/** Code TOTP courant (RFC 6238, SHA-1, 6 chiffres, pas de 30 s). */
function totp(secret) {
    const cle = base32Decode(secret);
    const compteur = Math.floor(Date.now() / 1000 / 30);
    const buf = Buffer.alloc(8);
    buf.writeUInt32BE(Math.floor(compteur / 0x100000000), 0);
    buf.writeUInt32BE(compteur >>> 0, 4);
    const h = crypto.createHmac('sha1', cle).update(buf).digest();
    const o = h[h.length - 1] & 0x0f;
    const brut = ((h[o] & 0x7f) << 24) | (h[o + 1] << 16) | (h[o + 2] << 8) | h[o + 3];
    return String(brut % 1000000).padStart(6, '0');
}

/** Script exécuté dans la page. */
const SONDE = () => {
    const parse = (c) => {
        const m = String(c).match(/rgba?\(([^)]+)\)/);
        if (!m) return null;
        const p = m[1].split(',').map((x) => parseFloat(x.trim()));
        return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 };
    };
    const sur = (d, s) => ({
        r: d.r * d.a + s.r * (1 - d.a),
        g: d.g * d.a + s.g * (1 - d.a),
        b: d.b * d.a + s.b * (1 - d.a),
        a: 1,
    });
    const lum = (c) => {
        const f = (v) => {
            v /= 255;
            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        };
        return 0.2126 * f(c.r) + 0.7152 * f(c.g) + 0.0722 * f(c.b);
    };
    const ratio = (a, b) => {
        const l1 = lum(a), l2 = lum(b);
        return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
    };
    /**
     * Fonds effectifs candidats pour un élément — un tableau, pas une couleur.
     *
     * Un fond n'est pas toujours uni : les surfaces de verre du site peignent
     * des dégradés (jeton --verre-fond-fort). `backgroundColor` vaut alors
     * « transparent » et ne décrit plus ce qui est réellement peint — s'y fier
     * seul ferait conclure, à tort, que la légende de la carte repose sur le
     * gris clair par défaut de Leaflet.
     *
     * On relève donc l'arrêt le plus opaque du dégradé le plus proche : c'est
     * lui qui définit la matière de la surface. Les autres arrêts sont des
     * halos partiels (le radial de --verre-fond-fort n'éclaire qu'un coin) ;
     * les prendre pour le fond du texte ferait échouer du texte situé à
     * l'opposé, ce qui serait un faux positif. Le contraste sur dégradé
     * dépend de la position : cette mesure est donc une estimation ponctuelle,
     * pas une garantie sur toute la surface.
     */
    const fondsEffectifs = (el) => {
        const couches = [];
        let noeud = el, opaque = null, arrets = null;
        while (noeud && noeud.nodeType === 1) {
            const st = getComputedStyle(noeud);
            const image = st.backgroundImage || '';
            if (arrets === null && image.indexOf('gradient') !== -1) {
                let meilleur = null;
                const re = /rgba?\([^)]*\)/g;
                let m;
                while ((m = re.exec(image)) !== null) {
                    const c = parse(m[0]);
                    if (c && c.a > 0.05 && (meilleur === null || c.a > meilleur.a)) meilleur = c;
                }
                if (meilleur) arrets = [meilleur];
            }
            const bg = parse(st.backgroundColor);
            if (bg && bg.a > 0) {
                couches.push(bg);
                if (bg.a >= 0.999) { opaque = bg; break; }
            }
            noeud = noeud.parentElement;
        }
        if (!opaque) opaque = { r: 255, g: 255, b: 255, a: 1 };
        let fond = opaque;
        for (let i = couches.length - 1; i >= 0; i--) {
            if (couches[i].a < 0.999) fond = sur(couches[i], fond);
        }
        if (arrets === null) return [fond];
        return arrets.map((a) => (a.a < 0.999 ? sur(a, fond) : a));
    };
    const decrire = (el) => {
        let s = el.tagName.toLowerCase();
        if (el.id) s += '#' + el.id;
        if (el.className && typeof el.className === 'string') {
            const cls = el.className.trim().split(/\s+/).slice(0, 2).join('.');
            if (cls) s += '.' + cls;
        }
        return s;
    };

    // Les tuiles de la carte sont des images : le fond CSS de leurs conteneurs
    // ne décrit pas ce qui est réellement affiché sous les épingles. Tout
    // élément posé sur ces couches raster est donc hors du périmètre d'une
    // mesure fondée sur les styles calculés — on le signale au lieu de le taire.
    const PANES_RASTER = '.leaflet-tile-pane, .leaflet-marker-pane, .leaflet-shadow-pane, .leaflet-overlay-pane';

    const resultats = [];
    const horsPerimetre = [];
    const elements = document.querySelectorAll('body *');

    for (const el of elements) {
        const cs = getComputedStyle(el);
        if (cs.display === 'none' || cs.visibility === 'hidden' || parseFloat(cs.opacity) < 0.1) continue;

        // Couches raster de la carte : on consigne le panneau lui-même, une
        // fois, puis on ignore tout son contenu. Le test doit porter sur le
        // panneau et non sur ses enfants : un panneau Leaflet est en position
        // absolue sans dimensions propres, il passerait donc sous le contrôle
        // de taille ci-dessous et ne serait jamais signalé.
        if (el.matches(PANES_RASTER)) {
            horsPerimetre.push(decrire(el));
            continue;
        }
        if (el.closest(PANES_RASTER)) continue;

        const rect = el.getBoundingClientRect();
        if (rect.width < 4 || rect.height < 4) continue;

        // Texte en dégradé : la couleur est transparente, le rendu vient du
        // fond découpé sur le texte. Ce n'est pas un défaut de contraste.
        const remplissage = cs.webkitTextFillColor || cs.getPropertyValue('-webkit-text-fill-color');
        const degrade = remplissage && parse(remplissage) && parse(remplissage).a < 0.05;
        if (degrade) continue;

        const estSvg = el.tagName.toLowerCase() === 'svg';

        let texte = '';
        if (!estSvg) {
            for (const n of el.childNodes) {
                if (n.nodeType === 3) texte += n.nodeValue;
            }
            texte = texte.replace(/\s+/g, ' ').trim();
            if (texte.length < 2) continue;
        }

        const couleur = parse(cs.color);
        if (!couleur || couleur.a < 0.05) continue;

        let fonds = fondsEffectifs(el);

        // Peintures à évaluer. Pour un SVG, `color` n'est pas forcément ce qui
        // est peint : le QR code pose un rect blanc de fond puis des modules
        // violets, les épingles de la carte portent leur propre palette. Juger
        // un tel pictogramme sur `color` produirait un faux positif.
        //
        // Règle : on relève les peintures réellement posées sur les formes. Un
        // rect opaque qui couvre toute la zone utile EST la surface de l'icône ;
        // il sert alors de fond local au lieu d'être comparé à lui-même.
        let peintures = null;
        if (estSvg) {
            const vb = (el.getAttribute('viewBox') || '').split(/[\s,]+/).map(Number);
            const aireUtile = vb.length === 4 && vb[2] > 0 && vb[3] > 0 ? vb[2] * vb[3] : 0;
            peintures = [];

            for (const forme of el.querySelectorAll('path, rect, circle, ellipse, polygon, polyline, line')) {
                const fc = getComputedStyle(forme);
                const boite = typeof forme.getBBox === 'function' ? forme.getBBox() : null;
                const couvreTout = aireUtile > 0 && boite !== null
                    && (boite.width * boite.height) >= aireUtile * 0.9
                    && forme.tagName.toLowerCase() === 'rect';

                for (const valeur of [fc.fill, fc.stroke]) {
                    if (!valeur || valeur === 'none') continue;
                    const p = parse(valeur);
                    if (!p || p.a <= 0.05) continue;
                    if (couvreTout && valeur === fc.fill && p.a >= 0.999) {
                        fonds = [p];              // aplat de fond : c'est la surface
                        continue;
                    }
                    peintures.push(p);
                }
            }

            if (peintures.length === 0) continue;   // icône sans peinture mesurable
        }

        let seuil;
        if (estSvg) {
            seuil = 3.0;                                  // éléments non textuels
        } else {
            const taille = parseFloat(cs.fontSize);
            const gras = parseInt(cs.fontWeight, 10) >= 700;
            seuil = (taille >= 24 || (taille >= 18.66 && gras)) ? 3.0 : 4.5;
        }

        // Une icône peut mêler plusieurs peintures, et une surface plusieurs
        // fonds candidats : on retient la pire combinaison.
        const candidates = peintures || [couleur];
        let pire = null;
        for (const p of candidates) {
            for (const f of fonds) {
                const pEff = p.a < 0.999 ? sur(p, f) : p;
                const r = ratio(pEff, f);
                if (r < seuil && (pire === null || r < pire.ratio)) pire = { r, p, f };
            }
        }
        if (pire === null) continue;

        const brut = pire.p;
        const fondRetenu = pire.f;
        resultats.push({
            selecteur: decrire(el),
            extrait: estSvg ? '(pictogramme)' : texte.slice(0, 44),
            couleur: brut.a < 0.999
                ? `rgba(${Math.round(brut.r)}, ${Math.round(brut.g)}, ${Math.round(brut.b)}, ${brut.a})`
                : `rgb(${Math.round(brut.r)}, ${Math.round(brut.g)}, ${Math.round(brut.b)})`,
            fond: `rgb(${Math.round(fondRetenu.r)}, ${Math.round(fondRetenu.g)}, ${Math.round(fondRetenu.b)})`,
            ratio: Math.round(pire.r * 100) / 100,
            seuil,
        });
    }

    const uniques = [], vus = new Set();
    for (const x of resultats) {
        const k = x.selecteur + '|' + x.couleur + '|' + x.fond;
        if (vus.has(k)) continue;
        vus.add(k);
        uniques.push(x);
    }
    uniques.sort((a, b) => a.ratio - b.ratio);
    return { trouvailles: uniques, horsPerimetre };
};

/** Connexion au back-office, en franchissant l'écran de code si nécessaire. */
async function connecter(page) {
    await page.goto(`${BASE}/admin/connexion?theme=sombre`, { waitUntil: 'networkidle' });
    await page.fill('#email', COMPTE.email);
    await page.fill('#mot_de_passe', COMPTE.motDePasse);
    await Promise.all([
        page.waitForLoadState('networkidle'),
        page.click('button[type="submit"]'),
    ]);

    if (page.url().includes('/connexion/code')) {
        const code = totp(COMPTE.secret);
        const champ = await page.$('#code, input[name="code"]');
        if (!champ) return { ok: false, motif: 'champ de code introuvable' };
        await champ.fill(code);
        await Promise.all([
            page.waitForLoadState('networkidle'),
            page.click('button[type="submit"]'),
        ]);
    }

    const arrivee = page.url();
    return { ok: !arrivee.includes('/connexion'), url: arrivee };
}

(async () => {
    const navigateur = await chromium.launch();
    const page = await navigateur.newPage({ viewport: { width: 1440, height: 1000 } });
    const rapport = {};
    let total = 0;

    // Le code TOTP calculé ici est comparé à celui de PHP pour éviter tout
    // décalage silencieux entre les deux implémentations.
    if (process.argv.includes('--code')) {
        console.log('Code TOTP (JS) :', totp(COMPTE.secret));
        await navigateur.close();
        return;
    }

    const connexion = await connecter(page);
    console.log('Connexion back-office :', connexion.ok ? 'OK -> ' + connexion.url : 'ECHEC (' + connexion.motif + ')');
    if (!connexion.ok) {
        console.log('Les pages du back-office seront ignorées.');
    }

    for (const p of PAGES) {
        if (p.auth && !connexion.ok) continue;

        const url = `${BASE}${p.chemin}?theme=sombre`;
        try {
            await page.goto(url, { waitUntil: 'networkidle', timeout: 20000 });
        } catch (e) {
            console.log(`\n### ${p.nom} — ERREUR ${e.message}`);
            continue;
        }
        await page.waitForTimeout(300);

        const theme = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
        const souci = await page.evaluate(SONDE);
        rapport[p.nom] = souci.trouvailles;
        total += souci.trouvailles.length;

        console.log(`\n### ${p.nom} (${p.chemin}) — thème=${theme} — ${souci.trouvailles.length} sous le seuil`);
        for (const x of souci.trouvailles.slice(0, 10)) {
            console.log(
                `   ${String(x.ratio).padStart(6)}:1  (min ${x.seuil})  ${x.selecteur}  ` +
                `texte=${x.couleur} fond=${x.fond}  "${x.extrait}"`
            );
        }
        if (souci.trouvailles.length > 10) console.log(`   ... et ${souci.trouvailles.length - 10} autre(s)`);

        if (souci.horsPerimetre.length) {
            console.log(
                `   hors périmètre : ${souci.horsPerimetre.length} élément(s) sur une couche raster ` +
                `(${[...new Set(souci.horsPerimetre)].join(', ')}) — fond image, non mesurable`
            );
        }
    }

    const sortie = process.argv[2] && !process.argv[2].startsWith('--')
        ? process.argv[2]
        : path.join(process.cwd(), 'audit-sombre.json');
    fs.writeFileSync(sortie, JSON.stringify(rapport, null, 2), 'utf8');
    console.log(`\nTOTAL : ${total} élément(s) sous le seuil — rapport -> ${sortie}`);

    await navigateur.close();
})();
