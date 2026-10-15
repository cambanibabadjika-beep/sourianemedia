/* ==========================================================================
   SOURIANE — Logique du site public
   Sans dépendance : mesure des interactions, installation de l’application,
   abonnement aux notifications, panneau glissant « Rester informé ».
   ========================================================================== */

(function () {
    'use strict';

    var C = window.SOURIANE || {};

    /* ---------------------------------------------------------------------
       Utilitaires
       --------------------------------------------------------------------- */
    function $(selecteur, racine) {
        return (racine || document).querySelector(selecteur);
    }

    function sur(cible, evenement, action, options) {
        if (cible) {
            cible.addEventListener(evenement, action, options || false);
        }
    }

    function base64UrlVersOctets(texte) {
        var reste   = texte.length % 4;
        var marge   = reste ? '='.repeat(4 - reste) : '';
        var base64  = (texte + marge).replace(/-/g, '+').replace(/_/g, '/');
        var brut    = window.atob(base64);
        var octets  = new Uint8Array(brut.length);

        for (var i = 0; i < brut.length; i++) {
            octets[i] = brut.charCodeAt(i);
        }

        return octets;
    }

    function requete(chemin, donnees, methode) {
        return fetch((C.api || '') + chemin, {
            method: methode || 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': C.csrf || '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(donnees || {})
        }).then(function (reponse) {
            return reponse.json().catch(function () {
                return {};
            }).then(function (charge) {
                if (!reponse.ok) {
                    var erreur = new Error(charge.erreur || 'Erreur ' + reponse.status);
                    erreur.statut = reponse.status;
                    throw erreur;
                }
                return charge;
            });
        });
    }

    /* ---------------------------------------------------------------------
       1. État du réseau
       --------------------------------------------------------------------- */
    function initReseau() {
        var bandeau = $('#bandeau-hors-ligne');

        function rafraichir() {
            if (!bandeau) {
                return;
            }
            bandeau.classList.toggle('bandeau-hors-ligne--visible', !navigator.onLine);
        }

        sur(window, 'online', rafraichir);
        sur(window, 'offline', rafraichir);
        rafraichir();
    }

    /* ---------------------------------------------------------------------
       2. Service worker
       --------------------------------------------------------------------- */
    var enregistrement = null;

    function initServiceWorker() {
        if (!('serviceWorker' in navigator) || !C.sw) {
            return Promise.resolve(null);
        }

        return navigator.serviceWorker.register(C.sw, { scope: C.base ? C.base + '/' : './' })
            .then(function (reg) {
                enregistrement = reg;
                return reg;
            })
            .catch(function (e) {
                if (window.console) {
                    console.warn('Service worker non enregistré :', e.message);
                }
                return null;
            });
    }

    /* ---------------------------------------------------------------------
       3. Mesure des interactions (ch. 5.7)
       --------------------------------------------------------------------- */
    var Mesure = (function () {
        var file   = [];
        var minuteur = null;
        var contexte = {
            session_id:    C.session || null,
            page_id:       C.page || null,
            annonceur_id:  C.annonceur || null,
            campagne_id:   C.campagne || null,
            panneau_id:    C.panneau || null,
            code_court_id: C.codeId || null
        };

        function envoyer() {
            if (file.length === 0) {
                return;
            }
            var lot = file.splice(0, 25);
            requete('/evenements', Object.assign({}, contexte, { evenements: lot }))
                .catch(function () {
                    // Mesure best-effort : en cas d’échec on ne rejoue pas indéfiniment
                });
        }

        function planifier() {
            window.clearTimeout(minuteur);
            minuteur = window.setTimeout(envoyer, 1200);
        }

        return {
            ajouter: function (type, meta) {
                if (!contexte.page_id) {
                    return;
                }
                file.push({ type: type, meta: meta || null });
                planifier();
            },
            vider: envoyer
        };
    })();

    function initMesure() {
        // Clics sur les éléments porteurs d’une action mesurable
        sur(document, 'click', function (e) {
            var cible = e.target.closest ? e.target.closest('[data-action]') : null;
            if (cible) {
                Mesure.ajouter(cible.getAttribute('data-action'));
            }
        }, true);

        // Dernière chance d’envoyer les événements en attente
        sur(document, 'visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                Mesure.vider();
            }
        });
        sur(window, 'pagehide', function () {
            Mesure.vider();
        });

        // Notification ouverte depuis le service worker
        if (navigator.serviceWorker) {
            navigator.serviceWorker.addEventListener('message', function (evenement) {
                var donnees = evenement.data || {};
                if (donnees.type === 'notification-clic') {
                    Mesure.ajouter('clic_rester_informe', { notification: donnees.notification || null });
                }
            });
        }
    }

    /* ---------------------------------------------------------------------
       4. Panneau glissant « Rester informé » (ch. 5.3)
       --------------------------------------------------------------------- */
    var Feuille = (function () {
        var voile   = null;
        var feuille = null;
        var declencheur = null;
        var ouverte = false;

        function ouvrir() {
            if (ouverte || !feuille) {
                return;
            }
            ouverte = true;
            declencheur = document.activeElement;

            voile.hidden = false;
            feuille.hidden = false;

            window.requestAnimationFrame(function () {
                voile.classList.add('voile--ouvert');
                feuille.classList.add('feuille--ouverte');
            });

            document.body.style.overflow = 'hidden';

            var premier = feuille.querySelector('button:not([hidden]), a[href]');
            if (premier) {
                premier.focus({ preventScroll: true });
            }
        }

        function fermer() {
            if (!ouverte || !feuille) {
                return;
            }
            ouverte = false;

            voile.classList.remove('voile--ouvert');
            feuille.classList.remove('feuille--ouverte');
            document.body.style.overflow = '';

            window.setTimeout(function () {
                if (!ouverte) {
                    voile.hidden = true;
                    feuille.hidden = true;
                }
            }, 340);

            if (declencheur && declencheur.focus) {
                declencheur.focus({ preventScroll: true });
            }
        }

        function init() {
            voile   = $('#voile-informe');
            feuille = $('#feuille-informe');

            if (!feuille) {
                return;
            }

            sur($('#ouvrir-rester-informe'), 'click', ouvrir);
            sur($('#fermer-feuille'), 'click', fermer);
            sur(voile, 'click', fermer);

            sur(document, 'keydown', function (e) {
                if (e.key === 'Escape' && ouverte) {
                    fermer();
                }
            });

            // Le panneau s’ouvre de lui-même après 8 s ou 40 % de défilement
            var deja = false;
            function proposer() {
                if (deja || ouverte) {
                    return;
                }
                deja = true;
                var flottant = $('#bouton-informe-flottant');
                if (flottant) {
                    flottant.classList.add('bouton-informe-flottant--visible');
                }
                ouvrir();
            }

            window.setTimeout(function () {
                if (!sessionStorage.getItem('souriane-informe-vu')) {
                    proposer();
                    try { sessionStorage.setItem('souriane-informe-vu', '1'); } catch (e) {}
                }
            }, 8000);

            sur(window, 'scroll', function () {
                if (ouverte || deja) {
                    return;
                }
                var hauteur = document.documentElement.scrollHeight - window.innerHeight;
                if (hauteur > 0 && window.scrollY / hauteur >= 0.4) {
                    proposer();
                    try { sessionStorage.setItem('souriane-informe-vu', '1'); } catch (e) {}
                }
            }, { passive: true });
        }

        return { init: init, ouvrir: ouvrir, fermer: fermer };
    })();

    /* ---------------------------------------------------------------------
       5. Installation de l’application (ch. 5.4)
       --------------------------------------------------------------------- */
    var Installation = (function () {
        var invite = null;
        var bouton = null;
        var etat   = null;

        function autonome() {
            return window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true;
        }

        function rafraichir() {
            if (!bouton || !etat) {
                return;
            }

            if (autonome()) {
                bouton.disabled = true;
                etat.hidden = false;
                bouton.querySelector('.action-informe__benefice').textContent =
                    'L’application est déjà installée sur cet appareil.';
                return;
            }

            if (C.ios) {
                // Sur iPhone, l’installation passe par le menu Partager
                bouton.disabled = false;
                return;
            }

            if (!invite) {
                bouton.disabled = true;
                bouton.querySelector('.action-informe__benefice').textContent =
                    'Utilisez le menu de votre navigateur puis « Ajouter à l’écran d’accueil ».';
            }
        }

        function init() {
            bouton = $('#action-installer');
            etat   = $('#etat-installe');

            if (!bouton) {
                return;
            }

            sur(window, 'beforeinstallprompt', function (e) {
                e.preventDefault();
                invite = e;
                rafraichir();
            });

            sur(window, 'appinstalled', function () {
                Mesure.ajouter('installation');
                invite = null;
                rafraichir();
            });

            sur(bouton, 'click', function () {
                if (autonome()) {
                    return;
                }

                if (C.ios) {
                    var tutoriel = $('#tutoriel-ios');
                    if (tutoriel) {
                        tutoriel.hidden = false;
                        tutoriel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    return;
                }

                if (!invite) {
                    return;
                }

                invite.prompt();
                invite.userChoice.then(function (choix) {
                    if (choix.outcome === 'accepted') {
                        Mesure.ajouter('installation');
                    }
                    invite = null;
                    rafraichir();
                });
            });

            rafraichir();
        }

        return { init: init, autonome: autonome };
    })();

    /* ---------------------------------------------------------------------
       6. Abonnement aux notifications (ch. 5.5)
       --------------------------------------------------------------------- */
    var Notifications = (function () {
        var bouton    = null;
        var etat      = null;
        var blocFreq  = null;
        var noteIos   = null;
        var abonnement = null;

        function prisEnCharge() {
            return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
        }

        /** Sur iPhone, les notifications exigent l’ajout à l’écran d’accueil. */
        function disponible() {
            if (!prisEnCharge()) {
                return false;
            }
            if (C.ios && !Installation.autonome()) {
                return false;
            }
            return Boolean(C.vapid);
        }

        function frequenceChoisie() {
            var choix = document.querySelector('input[name="frequence"]:checked');

            return choix ? choix.value : 'chaque_annonce';
        }

        function rafraichir() {
            if (!bouton || !etat) {
                return;
            }

            if (!disponible()) {
                bouton.disabled = true;
                bouton.querySelector('.action-informe__benefice').textContent = C.ios
                    ? 'Ajoutez d’abord la page à l’écran d’accueil, puis activez les notifications.'
                    : 'Les notifications ne sont pas prises en charge par ce navigateur.';

                if (C.ios && noteIos) {
                    noteIos.hidden = false;
                }
                return;
            }

            if (abonnement) {
                etat.hidden = false;
                bouton.querySelector('.action-informe__titre').childNodes[0].nodeValue = 'Ne plus recevoir les offres';
                bouton.querySelector('.action-informe__benefice').textContent =
                    'Vous pouvez vous désinscrire à tout moment.';
                if (blocFreq) {
                    blocFreq.hidden = false;
                }
                return;
            }

            etat.hidden = true;
            bouton.querySelector('.action-informe__titre').childNodes[0].nodeValue = 'Recevoir les offres';
            bouton.querySelector('.action-informe__benefice').textContent =
                'Une notification à chaque nouvelle offre. Quatre par mois au maximum.';
            if (blocFreq) {
                blocFreq.hidden = true;
            }
        }

        function souscrire() {
            return navigator.serviceWorker.ready
                .then(function (reg) {
                    return reg.pushManager.getSubscription().then(function (existante) {
                        if (existante) {
                            return existante;
                        }
                        return reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: base64UrlVersOctets(C.vapid)
                        });
                    });
                })
                .then(function (sub) {
                    var donnees = sub.toJSON();

                    return requete('/abonnements', {
                        annonceur_id:  C.annonceur,
                        code_court_id: C.codeId || null,
                        page_id:       C.page || null,
                        campagne_id:   C.campagne || null,
                        panneau_id:    C.panneau || null,
                        session_id:    C.session || null,
                        endpoint:      donnees.endpoint,
                        keys:          donnees.keys,
                        frequence:     frequenceChoisie()
                    }).then(function () {
                        abonnement = sub;
                        Mesure.ajouter('abonnement');
                    });
                });
        }

        function desinscrire() {
            if (!abonnement) {
                return Promise.resolve();
            }

            var endpoint = abonnement.endpoint;

            return abonnement.unsubscribe().then(function () {
                return requete('/abonnements/suppression', {
                    endpoint:      endpoint,
                    annonceur_id:  C.annonceur,
                    session_id:    C.session || null
                });
            }).then(function () {
                abonnement = null;
                Mesure.ajouter('desabonnement');
            });
        }

        function init() {
            bouton   = $('#action-notifier');
            etat     = $('#etat-abonne');
            blocFreq = $('#bloc-frequence');
            noteIos  = $('#note-ios');

            if (!bouton) {
                return;
            }

            // Abonnement éventuellement déjà en place
            if (prisEnCharge() && navigator.serviceWorker) {
                navigator.serviceWorker.ready
                    .then(function (reg) { return reg.pushManager.getSubscription(); })
                    .then(function (sub) {
                        abonnement = sub || null;
                        rafraichir();
                    })
                    .catch(function () { rafraichir(); });
            } else {
                rafraichir();
            }

            sur(bouton, 'click', function () {
                if (!disponible()) {
                    return;
                }

                bouton.disabled = true;

                var action = abonnement ? desinscrire() : souscrire();

                action.then(function () {
                    bouton.disabled = false;
                    rafraichir();
                }).catch(function (erreur) {
                    bouton.disabled = false;

                    var message = bouton.querySelector('.action-informe__benefice');
                    if (message) {
                        message.textContent = erreur.statut === 429
                            ? 'Trop de tentatives. Réessayez dans quelques minutes.'
                            : 'Activation impossible : ' + erreur.message;
                    }
                });
            });
        }

        return { init: init };
    })();

    /* ---------------------------------------------------------------------
       7. Bouton discret flottant
       --------------------------------------------------------------------- */
    function initBoutonFlottant() {
        if (!$('#feuille-informe')) {
            return;
        }

        var bouton = document.createElement('button');
        bouton.type = 'button';
        bouton.id = 'bouton-informe-flottant';
        bouton.className = 'bouton-informe-flottant';
        bouton.setAttribute('aria-label', 'Rester informé');
        bouton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 8-3 8h18s-3-1-3-8Z"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>'
            + '<span>Rester informé</span>';

        sur(bouton, 'click', function () {
            Feuille.ouvrir();
        });

        document.body.appendChild(bouton);
    }

    /* ---------------------------------------------------------------------
       8. Animations captivantes
       ---------------------------------------------------------------------
       Quatre effets, tous facultatifs et sans dépendance :
         · révélation au défilement (IntersectionObserver)
         · compteurs qui montent jusqu’à leur valeur
         · inclinaison 3D des cartes sous le curseur
         · barre de progression de lecture

       Deux garde-fous, dans cet ordre :
         1. `prefers-reduced-motion` → on ne touche à rien du tout, la page
            reste telle que le CSS la décrit (donc immobile et complète).
         2. Le drapeau data-anim posé dans <head> est validé ici par
            data-anim-pret. S’il n’est pas validé, le garde-fou de <head>
            retire data-anim et tout redevient visible.
       --------------------------------------------------------------------- */
    var Animations = (function () {

        function mouvementReduit() {
            return window.matchMedia
                && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        }

        /* Sépare les milliers par une espace, comme number_format() en PHP. */
        function formater(nombre) {
            return String(nombre).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }

        /* --- 8.1 Révélation au défilement -------------------------------- */
        function initRevelation() {
            var cibles = document.querySelectorAll('.reveler');

            if (cibles.length === 0) {
                return;
            }

            // Pas d’IntersectionObserver (navigateur ancien) : on révèle tout
            // immédiatement plutôt que de laisser des blocs invisibles.
            if (!('IntersectionObserver' in window)) {
                for (var i = 0; i < cibles.length; i++) {
                    cibles[i].classList.add('reveler--visible');
                }
                return;
            }

            var observateur = new IntersectionObserver(function (entrees) {
                entrees.forEach(function (entree) {
                    if (!entree.isIntersecting) {
                        return;
                    }
                    entree.target.classList.add('reveler--visible');
                    // Une seule fois : on cesse d’observer, l’animation est jouée.
                    observateur.unobserve(entree.target);
                });
            }, {
                rootMargin: '0px 0px -8% 0px',
                threshold: 0.12
            });

            for (var j = 0; j < cibles.length; j++) {
                observateur.observe(cibles[j]);
            }
        }

        /* --- 8.2 Compteurs ---------------------------------------------- */
        function initCompteurs() {
            var compteurs = document.querySelectorAll('[data-compteur]');

            if (compteurs.length === 0) {
                return;
            }

            function animer(element) {
                var cible = parseInt(element.getAttribute('data-compteur'), 10);

                if (isNaN(cible) || cible <= 0) {
                    element.textContent = formater(isNaN(cible) ? 0 : cible);
                    return;
                }

                var duree  = 1400;
                var depart = null;

                function etape(horodatage) {
                    if (depart === null) {
                        depart = horodatage;
                    }
                    var avancement = Math.min((horodatage - depart) / duree, 1);
                    // easeOutCubic : départ franc, arrivée posée.
                    var adouci = 1 - Math.pow(1 - avancement, 3);

                    element.textContent = formater(Math.round(cible * adouci));

                    if (avancement < 1) {
                        window.requestAnimationFrame(etape);
                    } else {
                        element.textContent = formater(cible);
                    }
                }

                element.textContent = '0';
                window.requestAnimationFrame(etape);
            }

            if (!('IntersectionObserver' in window)) {
                for (var i = 0; i < compteurs.length; i++) {
                    animer(compteurs[i]);
                }
                return;
            }

            var observateur = new IntersectionObserver(function (entrees) {
                entrees.forEach(function (entree) {
                    if (!entree.isIntersecting) {
                        return;
                    }
                    animer(entree.target);
                    observateur.unobserve(entree.target);
                });
            }, { threshold: 0.5 });

            for (var j = 0; j < compteurs.length; j++) {
                observateur.observe(compteurs[j]);
            }
        }

        /* --- 8.3 Inclinaison 3D ----------------------------------------- */
        function initInclinaison() {
            // Réservé aux pointeurs précis : sur écran tactile, il n’y a pas
            // de survol, l’effet ne ferait que consommer des ressources.
            if (!window.matchMedia || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
                return;
            }

            var cartes = document.querySelectorAll('.inclinable');
            var AMPLITUDE = 7;   // degrés, de part et d’autre

            for (var i = 0; i < cartes.length; i++) {
                (function (carte) {
                    var cadre = null;

                    function suivre(evenement) {
                        var boite = carte.getBoundingClientRect();
                        var x = (evenement.clientX - boite.left) / boite.width;
                        var y = (evenement.clientY - boite.top) / boite.height;

                        // y inversé : la souris en haut doit faire basculer le
                        // haut de la carte vers l’arrière.
                        carte.style.setProperty('--ry', ((x - 0.5) * 2 * AMPLITUDE).toFixed(2) + 'deg');
                        carte.style.setProperty('--rx', ((0.5 - y) * 2 * AMPLITUDE).toFixed(2) + 'deg');
                    }

                    sur(carte, 'pointerenter', function () {
                        carte.classList.add('inclinable--suivi');
                    });

                    sur(carte, 'pointermove', function (evenement) {
                        if (cadre !== null) {
                            return;
                        }
                        cadre = window.requestAnimationFrame(function () {
                            cadre = null;
                            suivre(evenement);
                        });
                    });

                    sur(carte, 'pointerleave', function () {
                        if (cadre !== null) {
                            window.cancelAnimationFrame(cadre);
                            cadre = null;
                        }
                        carte.classList.remove('inclinable--suivi');
                        carte.style.setProperty('--rx', '0deg');
                        carte.style.setProperty('--ry', '0deg');
                    });
                })(cartes[i]);
            }
        }

        /* --- 8.4 Barre de progression de lecture ------------------------ */
        function initProgression() {
            var barre = document.createElement('div');
            barre.className = 'barre-progression';
            barre.setAttribute('aria-hidden', 'true');
            document.body.appendChild(barre);

            var enAttente = false;

            function rafraichir() {
                enAttente = false;
                var hauteur = document.documentElement.scrollHeight - window.innerHeight;

                if (hauteur <= 0) {
                    barre.style.width = '0%';
                    return;
                }

                var ratio = Math.min(Math.max(window.pageYOffset / hauteur, 0), 1);
                barre.style.width = (ratio * 100).toFixed(2) + '%';
            }

            sur(window, 'scroll', function () {
                if (enAttente) {
                    return;
                }
                enAttente = true;
                window.requestAnimationFrame(rafraichir);
            }, { passive: true });

            sur(window, 'resize', rafraichir, { passive: true });
            rafraichir();
        }

        function init() {
            // Le JavaScript a répondu : le garde-fou de <head> ne retirera plus
            // le drapeau. On le pose AVANT tout le reste.
            document.documentElement.setAttribute('data-anim-pret', '1');

            if (mouvementReduit()) {
                return;
            }

            initRevelation();
            initCompteurs();
            initInclinaison();
            initProgression();
        }

        return { init: init };
    })();

    /* ---------------------------------------------------------------------
       9. Filtre du parc
       ---------------------------------------------------------------------
       Filtrage côté client sur /le-parc. Deux principes :

       1. Les lignes sont MASQUÉES via l'attribut `hidden`, jamais retirées du
          DOM. Le clavier et les lecteurs d'écran ne tombent donc pas sur une
          ligne invisible, et un retour au filtre précédent est instantané.
       2. Le compteur annoncé est celui des lignes réellement visibles, pas la
          longueur de la liste d'origine.
       --------------------------------------------------------------------- */
    var FiltreParc = (function () {

        var SELECTEUR_VISIBLE = '.tableau--parc tbody tr:not([hidden])';

        function appliquer(selecteur, valeur) {
            var lignes = document.querySelectorAll('.tableau--parc tbody tr');
            var visibles = 0;

            for (var i = 0; i < lignes.length; i++) {
                var ligne = lignes[i];
                var correspond = valeur === 'toutes'
                    || ligne.getAttribute('data-ville') === valeur;

                if (correspond) {
                    ligne.removeAttribute('hidden');
                    visibles++;
                } else {
                    ligne.setAttribute('hidden', '');
                }
            }

            var resultat = document.getElementById('parc-resultat');
            if (resultat) {
                resultat.textContent = String(visibles);
            }

            var enveloppe = document.getElementById('parc-tableau');
            var vide = document.getElementById('parc-vide');

            if (enveloppe) {
                enveloppe.classList.toggle('tableau-enveloppe--actif', visibles !== 0);
            }
            if (vide) {
                vide.hidden = visibles !== 0;
            }
        }

        function init() {
            var boutons = document.querySelectorAll('[data-filtre]');

            if (boutons.length === 0) {
                return;
            }

            function activer(choisi) {
                for (var i = 0; i < boutons.length; i++) {
                    var actif = boutons[i].getAttribute('data-filtre') === choisi;
                    boutons[i].setAttribute('aria-pressed', actif ? 'true' : 'false');
                }
            }

            for (var j = 0; j < boutons.length; j++) {
                sur(boutons[j], 'click', function (evenement) {
                    var cible = evenement.currentTarget;
                    var valeur = cible.getAttribute('data-filtre') || 'toutes';
                    activer(valeur);
                    appliquer(null, valeur);
                });
            }

            // État initial : tout est visible, mais on calcule le compteur pour
            // qu'il ne dépende pas du nombre de lignes écrites dans le HTML.
            appliquer(null, 'toutes');
        }

        return { init: init };
    })();

    /* ---------------------------------------------------------------------
       Démarrage
       --------------------------------------------------------------------- */
    function demarrer() {
        initReseau();
        initMesure();
        initBoutonFlottant();
        Animations.init();
        FiltreParc.init();
        Feuille.init();
        Installation.init();
        Notifications.init();

        initServiceWorker().then(function () {
            // Le service worker est prêt : rien de plus à faire ici
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', demarrer);
    } else {
        demarrer();
    }
})();
