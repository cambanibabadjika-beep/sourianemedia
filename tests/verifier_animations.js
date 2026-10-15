/**
 * SOURIANE — Vérification des animations de la vitrine.
 *
 * Ce script contrôle, dans un vrai navigateur, ce que les tests PHP ne peuvent
 * pas voir : que le bloc « chiffres du parc » est bien stylé, que la révélation
 * au défilement se déclenche, que les compteurs atteignent leur valeur finale
 * et que le mode « mouvement réduit » neutralise tout correctement.
 *
 * Il produit aussi deux captures d'écran de contrôle.
 *
 * Usage : node tests/verifier_animations.js [dossier-de-sortie]
 */

const { chromium } = require('playwright');

const BASE   = 'http://localhost/sourianemedia';
const SORTIE = process.argv[2] || 'C:/Users/khali/AppData/Local/Temp';

let reussis = 0;
let echecs  = 0;

function verifier(intitule, condition, detail) {
  if (condition) {
    reussis++;
    console.log(`  [OK]     ${intitule}`);
  } else {
    echecs++;
    console.log(`  [ECHEC]  ${intitule}${detail ? ' — ' + detail : ''}`);
  }
}

/** Convertit "rgb(76, 29, 149)" en tableau de nombres. */
function canaux(couleur) {
  const nombres = (couleur || '').match(/[\d.]+/g);
  return nombres ? nombres.slice(0, 3).map(Number) : null;
}

(async () => {
  const navigateur = await chromium.launch();
  const contexte   = await navigateur.newContext({
    viewport: { width: 1440, height: 950 },
    deviceScaleFactor: 2,
    locale: 'fr-FR',
  });
  const page = await contexte.newPage();

  // Les erreurs JavaScript sont collectées : une animation qui plante ne doit
  // pas passer inaperçue sous couvert d'un rendu correct.
  const erreursConsole = [];
  page.on('pageerror', (e) => erreursConsole.push(String(e)));
  page.on('console', (m) => {
    if (m.type() === 'error') {
      erreursConsole.push(m.text());
    }
  });

  console.log('\n=== Animations de la vitrine ===\n');

  await page.goto(BASE + '/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(900);

  // --- 1. Le drapeau d'animation est posé et validé ------------------------
  const drapeau = await page.evaluate(() => ({
    anim: document.documentElement.getAttribute('data-anim'),
    pret: document.documentElement.getAttribute('data-anim-pret'),
  }));
  verifier('Le drapeau data-anim est actif', drapeau.anim === 'actif', `reçu : ${drapeau.anim}`);
  verifier('Le drapeau est validé par le script', drapeau.pret === '1', `reçu : ${drapeau.pret}`);

  // --- 2. Le bloc « chiffres » est réellement stylé ------------------------
  const chiffres = await page.evaluate(() => {
    const bloc = document.querySelector('.chiffres');
    const carte = document.querySelector('.chiffre');
    const valeur = document.querySelector('.chiffre__valeur');
    const etiquette = document.querySelector('.chiffre__etiquette');
    if (!bloc || !carte || !valeur || !etiquette) {
      return null;
    }
    const styleBloc = getComputedStyle(bloc);
    const styleCarte = getComputedStyle(carte);
    return {
      colonnes: styleBloc.gridTemplateColumns.split(' ').length,
      cartes: document.querySelectorAll('.chiffre').length,
      fondCarte: styleCarte.backgroundColor,
      flouCarte: styleCarte.backdropFilter || styleCarte.webkitBackdropFilter,
      rayonCarte: parseFloat(styleCarte.borderTopLeftRadius),
      couleurValeur: getComputedStyle(valeur).color,
      couleurEtiquette: getComputedStyle(etiquette).color,
      tailleValeur: parseFloat(getComputedStyle(valeur).fontSize),
    };
  });

  verifier('Le conteneur .chiffres existe', chiffres !== null);

  if (chiffres) {
    verifier('Quatre cartes sont présentes', chiffres.cartes === 4, `reçu : ${chiffres.cartes}`);
    verifier('La grille a quatre colonnes', chiffres.colonnes === 4, `reçu : ${chiffres.colonnes}`);
    verifier('La carte a un rayon d’angle', chiffres.rayonCarte >= 12, `reçu : ${chiffres.rayonCarte}px`);

    // Le verre doit être clair ET flouté : c'est tout l'effet demandé.
    const fond = canaux(chiffres.fondCarte);
    verifier(
      'La carte est un verre translucide (alpha < 1)',
      fond !== null && (chiffres.fondCarte.includes('rgba') || chiffres.fondCarte.includes('0.')),
      `reçu : ${chiffres.fondCarte}`
    );
    verifier(
      'Le flou d’arrière-plan est appliqué',
      /blur\(/.test(chiffres.flouCarte || ''),
      `reçu : ${chiffres.flouCarte}`
    );

    // Le bug d'origine : étiquette blanche sur fond blanc. On vérifie que la
    // couleur de l'étiquette est SOMBRE, donc lisible sur le verre clair.
    const etiquette = canaux(chiffres.couleurEtiquette);
    const clarteEtiquette = etiquette ? (etiquette[0] + etiquette[1] + etiquette[2]) / 3 : 255;
    verifier(
      'L’étiquette est en texte foncé (lisible sur verre clair)',
      clarteEtiquette < 120,
      `reçu : ${chiffres.couleurEtiquette} (moyenne ${clarteEtiquette.toFixed(0)})`
    );

    const valeur = canaux(chiffres.couleurValeur);
    const clarteValeur = valeur ? (valeur[0] + valeur[1] + valeur[2]) / 3 : 255;
    verifier(
      'Le nombre est en violet foncé',
      clarteValeur < 120,
      `reçu : ${chiffres.couleurValeur}`
    );

    verifier(
      'Le nombre est en grand corps de texte',
      chiffres.tailleValeur >= 30,
      `reçu : ${chiffres.tailleValeur}px`
    );
  }

  // --- 3. Le bandeau défilant --------------------------------------------
  const defileur = await page.evaluate(() => {
    const piste = document.querySelector('.defileur__piste');
    const copie = document.querySelectorAll('.defileur__element--copie');
    if (!piste) {
      return null;
    }
    return {
      animation: getComputedStyle(piste).animationName,
      duree: getComputedStyle(piste).animationDuration,
      copies: copie.length,
    };
  });

  verifier('Le bandeau défilant existe', defileur !== null);
  if (defileur) {
    verifier('La piste est animée', defileur.animation === 'defilement', `reçu : ${defileur.animation}`);
    verifier('La piste a des copies pour boucler', defileur.copies > 0, `reçu : ${defileur.copies}`);
  }

  // --- 4. La barre de progression ----------------------------------------
  verifier(
    'La barre de progression est créée',
    (await page.locator('.barre-progression').count()) === 1
  );

  // --- 5. La révélation au défilement ------------------------------------
  const avantDefilement = await page.evaluate(() => {
    const elements = Array.from(document.querySelectorAll('.reveler'));
    const visibles = elements.filter((e) => e.classList.contains('reveler--visible')).length;
    return { total: elements.length, visibles };
  });

  verifier(
    'Des éléments sont en attente de révélation',
    avantDefilement.total - avantDefilement.visibles > 0,
    `${avantDefilement.visibles}/${avantDefilement.total} déjà visibles`
  );

  // On descend jusqu'au bloc des chiffres.
  await page.locator('.chiffres').scrollIntoViewIfNeeded();
  await page.waitForTimeout(1800);

  const apresDefilement = await page.evaluate(() => {
    const cartes = Array.from(document.querySelectorAll('.chiffre'));
    return {
      revelees: cartes.filter((c) => c.classList.contains('reveler--visible')).length,
      opacites: cartes.map((c) => parseFloat(getComputedStyle(c).opacity)),
    };
  });

  verifier(
    'Les quatre cartes sont révélées après défilement',
    apresDefilement.revelees === 4,
    `reçu : ${apresDefilement.revelees}`
  );
  verifier(
    'Les cartes sont pleinement opaques',
    apresDefilement.opacites.every((o) => o > 0.99),
    `reçu : ${apresDefilement.opacites.join(', ')}`
  );

  // --- 6. Les compteurs atteignent leur cible ----------------------------
  const compteurs = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('[data-compteur]')).map((e) => ({
      cible: e.getAttribute('data-compteur'),
      affiche: e.textContent.trim(),
    }));
  });

  verifier('Quatre compteurs sont présents', compteurs.length === 4, `reçu : ${compteurs.length}`);
  const compteursOk = compteurs.every((c) => c.affiche === c.cible);
  verifier(
    'Chaque compteur affiche sa valeur finale',
    compteursOk,
    compteurs.map((c) => `${c.affiche}/${c.cible}`).join(' · ')
  );

  await page.screenshot({ path: `${SORTIE}/souriane-anim-chiffres.png` });
  console.log('  capture :', `${SORTIE}/souriane-anim-chiffres.png`);

  // --- 7. La page entière, pour contrôle visuel --------------------------
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(400);
  await page.screenshot({ path: `${SORTIE}/souriane-anim-accueil.png`, fullPage: true });
  console.log('  capture :', `${SORTIE}/souriane-anim-accueil.png`);

  // --- 8. Aucune erreur JavaScript ---------------------------------------
  verifier(
    'Aucune erreur JavaScript',
    erreursConsole.length === 0,
    erreursConsole.slice(0, 3).join(' | ')
  );

  // --- 9. Mode « mouvement réduit » --------------------------------------
  const contexteReduit = await navigateur.newContext({
    viewport: { width: 1440, height: 950 },
    reducedMotion: 'reduce',
    locale: 'fr-FR',
  });
  const pageReduite = await contexteReduit.newPage();
  await pageReduite.goto(BASE + '/', { waitUntil: 'networkidle' });
  await pageReduite.waitForTimeout(700);

  const reduit = await pageReduite.evaluate(() => {
    const cartes = Array.from(document.querySelectorAll('.chiffre'));
    const piste = document.querySelector('.defileur__piste');
    return {
      drapeau: document.documentElement.getAttribute('data-anim'),
      opacites: cartes.map((c) => parseFloat(getComputedStyle(c).opacity)),
      animationPiste: piste ? getComputedStyle(piste).animationName : 'aucune',
      barre: document.querySelectorAll('.barre-progression').length,
      // Les valeurs restent lisibles : le texte de repli est dans le HTML.
      valeurs: Array.from(document.querySelectorAll('[data-compteur]')).map((e) => e.textContent.trim()),
    };
  });

  verifier(
    'Mouvement réduit : aucun élément masqué',
    reduit.opacites.every((o) => o > 0.99),
    `reçu : ${reduit.opacites.join(', ')}`
  );
  verifier(
    'Mouvement réduit : le bandeau ne défile plus',
    reduit.animationPiste === 'none',
    `reçu : ${reduit.animationPiste}`
  );
  verifier(
    'Mouvement réduit : aucune barre de progression',
    reduit.barre === 0,
    `reçu : ${reduit.barre}`
  );
  verifier(
    'Mouvement réduit : les chiffres restent lisibles',
    reduit.valeurs.every((v) => v !== '' && v !== '0'),
    reduit.valeurs.join(' · ')
  );

  await pageReduite.screenshot({ path: `${SORTIE}/souriane-anim-mouvement-reduit.png` });
  console.log('  capture :', `${SORTIE}/souriane-anim-mouvement-reduit.png`);

  // --- 10. Sans JavaScript -----------------------------------------------
  // Le cas le plus important : si le script ne tourne pas, le contenu doit
  // rester intégralement visible. On bloque app.js et on recharge.
  const contexteSansJs = await navigateur.newContext({
    viewport: { width: 1440, height: 950 },
    javaScriptEnabled: false,
    locale: 'fr-FR',
  });
  const pageSansJs = await contexteSansJs.newPage();
  await pageSansJs.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
  await pageSansJs.waitForTimeout(500);

  const sansJs = await pageSansJs.evaluate(() => {
    const cartes = Array.from(document.querySelectorAll('.chiffre'));
    return {
      opacites: cartes.map((c) => parseFloat(getComputedStyle(c).opacity)),
      valeurs: Array.from(document.querySelectorAll('[data-compteur]')).map((e) => e.textContent.trim()),
    };
  });

  verifier(
    'Sans JavaScript : les cartes restent visibles',
    sansJs.opacites.length === 4 && sansJs.opacites.every((o) => o > 0.99),
    `reçu : ${sansJs.opacites.join(', ')}`
  );
  verifier(
    'Sans JavaScript : les chiffres restent affichés',
    sansJs.valeurs.length === 4 && sansJs.valeurs.every((v) => v !== ''),
    sansJs.valeurs.join(' · ')
  );

  // --- 11. Page du parc : cartes de ville et jauges ------------------------
  await page.goto(BASE + '/le-parc', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1900);

  // Note : sur cet écran, les cartes de ville sont déjà dans la fenêtre au
  // chargement — l'observateur les révèle donc immédiatement. On ne peut pas
  // tester « la jauge part de zéro » ici ; on vérifie à la place que la jauge
  // est correctement proportionnée, ce qui est la propriété qui compte.
  const jauges = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('.carte-ville')).map((carte) => {
      const piste = carte.querySelector('.jauge');
      const remplissage = carte.querySelector('.jauge__remplissage');
      const declare = parseFloat(
        remplissage.style.getPropertyValue('--jauge').replace('%', '')
      );
      const largeurPiste = piste.getBoundingClientRect().width;
      const largeurRemplissage = remplissage.getBoundingClientRect().width;
      return {
        declare,
        attendu: largeurPiste * declare / 100,
        obtenu: largeurRemplissage,
      };
    });
  });

  verifier('Parc : des jauges sont présentes', jauges.length > 0, `reçu : ${jauges.length}`);

  const jaugesJustes = jauges.every((j) => Math.abs(j.obtenu - j.attendu) <= 2);
  verifier(
    'Parc : chaque jauge occupe la proportion annoncée',
    jaugesJustes,
    jauges.map((j) => `${j.declare}%→${j.obtenu.toFixed(0)}px/${j.attendu.toFixed(0)}px`).join(' · ')
  );

  const apresParc = await page.evaluate(() => {
    const cartes = Array.from(document.querySelectorAll('.carte-ville'));
    return {
      revelees: cartes.filter((c) => c.classList.contains('reveler--visible')).length,
      total: cartes.length,
    };
  });

  verifier(
    'Parc : toutes les cartes de ville sont révélées',
    apresParc.revelees === apresParc.total && apresParc.total > 0,
    `reçu : ${apresParc.revelees}/${apresParc.total}`
  );

  await page.screenshot({ path: `${SORTIE}/souriane-anim-parc.png`, fullPage: true });
  console.log('  capture :', `${SORTIE}/souriane-anim-parc.png`);

  verifier(
    'Aucune erreur JavaScript sur la page du parc',
    erreursConsole.length === 0,
    erreursConsole.slice(0, 3).join(' | ')
  );

  await navigateur.close();

  const total = reussis + echecs;
  console.log('\n' + '='.repeat(62));
  console.log(`Animations — ${reussis}/${total} conformes`);
  console.log('='.repeat(62) + '\n');

  process.exit(echecs === 0 ? 0 : 1);
})();