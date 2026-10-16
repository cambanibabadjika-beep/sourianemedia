/* ==========================================================================
   SOURIANE — Carte du parc (EF-A06)
   --------------------------------------------------------------------------
   La carte est construite à partir des lignes du tableau du parc, et non
   d'un second jeu de données : ce que le filtre serveur a retenu est
   exactement ce que la carte affiche. Un second filtrage côté navigateur
   finirait par diverger de celui du serveur — c'est précisément ce que la
   vue du parc évite depuis EF-A05.

   Sans JavaScript, le tableau reste seul affiché et la carte n'apparaît
   pas : le bouton de bascule n'est révélé que par ce script.
   ========================================================================== */

(function () {
    'use strict';

    var carte       = document.getElementById('parc-carte');
    var liste       = document.getElementById('parc-liste');
    var tableau     = document.getElementById('parc-tableau');
    var bascule     = document.getElementById('parc-bascule');
    var avertissement = document.getElementById('parc-carte-avertissement');

    if (!carte || !tableau || !bascule) {
        return;
    }

    /* ---------------------------------------------------------------------
       Lecture des supports depuis le tableau
       --------------------------------------------------------------------- */
    function lireSupports() {
        var lignes = tableau.querySelectorAll('tbody tr');
        var supports = [];

        for (var i = 0; i < lignes.length; i++) {
            var ligne = lignes[i];
            var lat = parseFloat(ligne.getAttribute('data-lat'));
            var lng = parseFloat(ligne.getAttribute('data-lng'));

            if (!isFinite(lat) || !isFinite(lng)) {
                continue;
            }

            supports.push({
                ligne:     ligne,
                lat:       lat,
                lng:       lng,
                reference: ligne.getAttribute('data-reference') || '',
                libelle:   ligne.getAttribute('data-libelle') || '',
                adresse:   ligne.getAttribute('data-adresse') || '',
                ville:     ligne.getAttribute('data-ville') || '',
                format:    ligne.getAttribute('data-format') || '',
                trafic:    ligne.getAttribute('data-trafic') || '',
                tarif:     ligne.getAttribute('data-tarif') || '',
                statut:    ligne.getAttribute('data-statut') || ''
            });
        }

        return supports;
    }

    var supports = lireSupports();

    /* Aucune coordonnée renseignée : la carte n'a rien à montrer. On le dit,
       plutôt que d'afficher un fond de carte vide qui laisserait croire à une
       panne. Le tableau reste la seule vue. */
    if (supports.length === 0) {
        carte.hidden = true;
        bascule.hidden = true;
        if (avertissement) {
            avertissement.hidden = false;
        }
        return;
    }

    /* Leaflet est embarqué dans le projet, mais il peut manquer : fichier non
       déployé, script bloqué. Dans ce cas la bascule n'est pas révélée et le
       tableau reste affiché — la page reste entièrement utilisable. */
    if (typeof window.L === 'undefined') {
        carte.hidden = true;
        bascule.hidden = true;
        if (avertissement) {
            avertissement.hidden = false;
        }
        return;
    }

    /* ---------------------------------------------------------------------
       Épingles
       ---------------------------------------------------------------------
       Une épingle dessinée en SVG plutôt que l'image par défaut de Leaflet :
       aucun fichier binaire à déployer, et la couleur suit le statut du
       support, comme les étiquettes du tableau.
       --------------------------------------------------------------------- */
    var COULEURS = {
        disponible:  '#1B7F5A',
        reserve:     '#B8860B',
        occupe:      '#5B2E8C',
        maintenance: '#8A5A00',
        archive:     '#6B7280'
    };

    function epingle(statut) {
        var couleur = COULEURS[statut] || '#5B2E8C';

        return window.L.divIcon({
            className: 'epingle',
            iconSize:    [26, 34],
            iconAnchor:  [13, 34],
            popupAnchor: [0, -30],
            html: '<svg viewBox="0 0 26 34" width="26" height="34" aria-hidden="true">'
                + '<path d="M13 33C13 33 25 19.5 25 12.5A12 12 0 0 0 1 12.5C1 19.5 13 33 13 33Z" '
                + 'fill="' + couleur + '" stroke="#FFFFFF" stroke-width="2"/>'
                + '<circle cx="13" cy="12.5" r="4.2" fill="#FFFFFF"/></svg>'
        });
    }

    /* ---------------------------------------------------------------------
       Contenu des bulles
       ---------------------------------------------------------------------
       Construit par nœuds et textContent : les valeurs viennent de la base et
       ne sont jamais interprétées comme du balisage.
       --------------------------------------------------------------------- */
    function ligneBulle(etiquette, valeur) {
        if (!valeur) {
            return null;
        }

        var bloc = document.createElement('p');
        bloc.className = 'bulle__ligne';

        var nom = document.createElement('span');
        nom.className = 'bulle__etiquette';
        nom.textContent = etiquette;

        var contenu = document.createElement('span');
        contenu.className = 'bulle__valeur';
        contenu.textContent = valeur;

        bloc.appendChild(nom);
        bloc.appendChild(contenu);

        return bloc;
    }

    function bulle(support) {
        var boite = document.createElement('div');
        boite.className = 'bulle';

        var titre = document.createElement('strong');
        titre.className = 'bulle__titre';
        titre.textContent = support.reference;
        boite.appendChild(titre);

        if (support.libelle) {
            var sous = document.createElement('span');
            sous.className = 'bulle__sous-titre';
            sous.textContent = support.libelle;
            boite.appendChild(sous);
        }

        /* Mêmes classes que l'étiquette du tableau (etat etat--<statut>) :
           la carte et la liste doivent se lire de la même façon. */
        var etat = document.createElement('span');
        etat.className = 'etat etat--' + (support.statut || 'inconnu');
        etat.textContent = support.ligne.getAttribute('data-etat') || support.statut;
        boite.appendChild(etat);

        var details = [
            ['Ville', support.ville],
            ['Format', support.format],
            ['Trafic / jour', support.trafic],
            ['Tarif', support.tarif],
            ['Adresse', support.adresse]
        ];

        for (var i = 0; i < details.length; i++) {
            var bloc = ligneBulle(details[i][0], details[i][1]);
            if (bloc) {
                boite.appendChild(bloc);
            }
        }

        /* Renvoi vers la ligne correspondante du tableau : la carte et la
           liste sont deux vues du même support, et l'une doit mener à
           l'autre. */
        var lien = document.createElement('button');
        lien.type = 'button';
        lien.className = 'bulle__lien';
        lien.textContent = 'Voir la ligne dans la liste';
        lien.addEventListener('click', function () {
            afficher('tableau');
            support.ligne.scrollIntoView({ block: 'center' });
            support.ligne.classList.add('ligne--mise-en-avant');
            window.setTimeout(function () {
                support.ligne.classList.remove('ligne--mise-en-avant');
            }, 2200);
        });
        boite.appendChild(lien);

        return boite;
    }

    /* ---------------------------------------------------------------------
       Carte
       --------------------------------------------------------------------- */
    var objetCarte = null;

    function construireCarte() {
        if (objetCarte) {
            return objetCarte;
        }

        objetCarte = window.L.map(carte, {
            scrollWheelZoom: false,   /* le défilement de la page prime */
            attributionControl: true
        });

        window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(objetCarte);

        var groupe = window.L.featureGroup().addTo(objetCarte);
        var marqueurs = [];

        for (var i = 0; i < supports.length; i++) {
            var support = supports[i];
            var marqueur = window.L.marker([support.lat, support.lng], {
                icon: epingle(support.statut),
                title: support.reference + ' — ' + support.libelle,
                alt: support.reference + ', ' + support.ville
            });

            marqueur.bindPopup(bulle(support));
            marqueur.addTo(groupe);
            marqueurs.push(marqueur);
        }

        /* Un seul support : le cadrage automatique produirait un zoom maximal
           sur un point, peu lisible. On fixe un niveau raisonnable. */
        if (marqueurs.length === 1) {
            objetCarte.setView([supports[0].lat, supports[0].lng], 14);
        } else {
            objetCarte.fitBounds(groupe.getBounds(), { padding: [36, 36] });
        }

        return objetCarte;
    }

    /* ---------------------------------------------------------------------
       Bascule liste / carte
       --------------------------------------------------------------------- */
    var boutons = bascule.querySelectorAll('[data-vue]');

    function afficher(vue) {
        var surCarte = vue === 'carte';

        carte.hidden   = !surCarte;
        if (liste) {
            liste.hidden = surCarte;
        }

        for (var i = 0; i < boutons.length; i++) {
            var bouton = boutons[i];
            var actif = bouton.getAttribute('data-vue') === vue;
            bouton.setAttribute('aria-pressed', actif ? 'true' : 'false');
            bouton.classList.toggle('est-actif', actif);
        }

        if (surCarte) {
            construireCarte();
            /* La carte vient d'être affichée : sa taille était nulle tant que
               le conteneur restait masqué. Sans cette mesure, Leaflet dessine
               une carte tronquée ou grise. */
            objetCarte.invalidateSize();
        }
    }

    for (var b = 0; b < boutons.length; b++) {
        boutons[b].addEventListener('click', function () {
            afficher(this.getAttribute('data-vue'));
        });
    }

    /* La bascule n'est révélée qu'ici : sans JavaScript, le tableau reste
       seul, et aucune commande inerte n'est proposée au visiteur. */
    bascule.hidden = false;

    /* Vue initiale : la carte, puisque c'est l'objet de cette section. Le
       tableau reste à un clic, et son contenu reste dans le document. */
    afficher('carte');
})();
