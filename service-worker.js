/* ==========================================================================
   SOURIANE — Service worker (ch. 5.4 et 5.5)
   · met en cache la coquille de l’application pour un usage hors connexion ;
   · sert les pages en « réseau d’abord » et les ressources en « cache d’abord » ;
   · ne met jamais en cache les réponses de l’API ;
   · affiche les notifications reçues par Web Push.
   ========================================================================== */

'use strict';

/* Incrémenter cette version est OBLIGATOIRE à chaque modification de app.css
   ou de app.js : les ressources sont servies en « cache d’abord », donc sans
   changement de version les visiteurs déjà venus garderaient indéfiniment
   l’ancienne feuille de style. Le passage en v2 correspond à l’ajout des
   animations de la vitrine et du composant « chiffres du parc ». */
var VERSION = 'souriane-v7';
var CACHE_COQUILLE = VERSION + '-coquille';
var CACHE_RESSOURCES = VERSION + '-ressources';

/** Ressources mises en cache dès l’installation. */
var COQUILLE = [
    './',
    './le-parc',
    './manifest.webmanifest',
    './assets/css/app.css',
    './assets/js/app.js',
    './assets/img/logo-marque.svg',
    './assets/img/favicon.png',
    './assets/img/icons/icone-192.png',
    './assets/img/icons/icone-512.png'
];

/** Page de repli hors connexion. */
var PAGE_HORS_LIGNE = '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">'
    + '<meta name="viewport" content="width=device-width,initial-scale=1">'
    + '<title>Hors connexion — Souriane</title>'
    + '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;'
    + 'font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
    + 'background:#F5F3FF;color:#1A1033;text-align:center;padding:24px}'
    + 'h1{font-size:1.5rem;margin:0 0 10px}p{color:#4B5563;max-width:38ch;margin:0 auto}'
    + 'a{display:inline-block;margin-top:22px;background:#4C1D95;color:#fff;'
    + 'padding:13px 24px;border-radius:999px;text-decoration:none;font-weight:600}'
    + '</style></head><body><div><h1>Vous êtes hors connexion</h1>'
    + '<p>Cette page n’a pas encore été enregistrée sur votre appareil. '
    + 'Reconnectez-vous puis réessayez.</p><a href="./">Réessayer</a></div></body></html>';

/* -------------------------------------------------------------------------
   Installation : pré-cache de la coquille
   ------------------------------------------------------------------------- */
self.addEventListener('install', function (evenement) {
    evenement.waitUntil(
        caches.open(CACHE_COQUILLE)
            .then(function (cache) {
                // addAll échoue en bloc : on tolère les ressources manquantes
                return Promise.all(COQUILLE.map(function (url) {
                    return cache.add(new Request(url, { cache: 'reload' }))
                        .catch(function () { return null; });
                }));
            })
            .then(function () { return self.skipWaiting(); })
    );
});

/* -------------------------------------------------------------------------
   Activation : purge des anciennes versions
   ------------------------------------------------------------------------- */
self.addEventListener('activate', function (evenement) {
    evenement.waitUntil(
        caches.keys()
            .then(function (noms) {
                return Promise.all(noms.map(function (nom) {
                    if (nom !== CACHE_COQUILLE && nom !== CACHE_RESSOURCES) {
                        return caches.delete(nom);
                    }
                    return null;
                }));
            })
            .then(function () { return self.clients.claim(); })
    );
});

/* -------------------------------------------------------------------------
   Interception des requêtes
   ------------------------------------------------------------------------- */
function estRessource(url) {
    return /\.(css|js|png|jpe?g|webp|svg|ico|woff2?|ttf)$/i.test(url.pathname);
}

function estApi(url) {
    return url.pathname.indexOf('/api/') !== -1;
}

/** Réseau d’abord, cache ensuite, page de repli en dernier recours. */
function reseauDAbord(requete) {
    return fetch(requete)
        .then(function (reponse) {
            if (reponse && reponse.ok && requete.method === 'GET') {
                var copie = reponse.clone();
                caches.open(CACHE_COQUILLE).then(function (cache) {
                    cache.put(requete, copie).catch(function () {});
                });
            }
            return reponse;
        })
        .catch(function () {
            return caches.match(requete, { ignoreSearch: true })
                .then(function (enCache) {
                    if (enCache) {
                        return enCache;
                    }
                    if (requete.mode === 'navigate') {
                        return new Response(PAGE_HORS_LIGNE, {
                            status: 200,
                            headers: { 'Content-Type': 'text/html; charset=utf-8' }
                        });
                    }
                    return Response.error();
                });
        });
}

/** Cache d’abord, réseau ensuite (ressources versionnées). */
function cacheDAbord(requete) {
    return caches.match(requete, { ignoreSearch: true }).then(function (enCache) {
        if (enCache) {
            // Rafraîchit la copie en arrière-plan
            fetch(requete).then(function (reponse) {
                if (reponse && reponse.ok) {
                    caches.open(CACHE_RESSOURCES).then(function (cache) {
                        cache.put(requete, reponse.clone()).catch(function () {});
                    });
                }
            }).catch(function () {});
            return enCache;
        }

        return fetch(requete).then(function (reponse) {
            if (reponse && reponse.ok) {
                var copie = reponse.clone();
                caches.open(CACHE_RESSOURCES).then(function (cache) {
                    cache.put(requete, copie).catch(function () {});
                });
            }
            return reponse;
        });
    });
}

self.addEventListener('fetch', function (evenement) {
    var requete = evenement.request;

    if (requete.method !== 'GET') {
        return;
    }

    var url = new URL(requete.url);

    // L’API n’est jamais mise en cache : les mesures doivent atteindre le serveur
    if (estApi(url)) {
        return;
    }

    // Ressources d’une autre origine : laissées au navigateur
    if (url.origin !== self.location.origin) {
        return;
    }

    if (estRessource(url)) {
        evenement.respondWith(cacheDAbord(requete));
        return;
    }

    evenement.respondWith(reseauDAbord(requete));
});

/* -------------------------------------------------------------------------
   Notifications (Web Push, ch. 5.5)
   ------------------------------------------------------------------------- */
self.addEventListener('push', function (evenement) {
    var charge = {};

    if (evenement.data) {
        try {
            charge = evenement.data.json();
        } catch (e) {
            charge = { corps: evenement.data.text() };
        }
    }

    var titre = charge.titre || 'Souriane';
    var options = {
        body: charge.corps || 'Une nouvelle offre vous attend.',
        icon: charge.icone || './assets/img/icons/icone-192.png',
        badge: './assets/img/icons/icone-192.png',
        tag: charge.etiquette || 'souriane',
        renotify: false,
        lang: 'fr',
        dir: 'ltr',
        data: {
            url: charge.url || './',
            notification: charge.etiquette || null
        }
    };

    evenement.waitUntil(self.registration.showNotification(titre, options));
});

self.addEventListener('notificationclick', function (evenement) {
    evenement.notification.close();

    var destination = (evenement.notification.data && evenement.notification.data.url)
        ? evenement.notification.data.url
        : './';

    evenement.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function (fenetres) {
                // Réutilise un onglet déjà ouvert sur le site
                for (var i = 0; i < fenetres.length; i++) {
                    var fenetre = fenetres[i];
                    if (fenetre.url.indexOf(self.location.origin) === 0 && 'focus' in fenetre) {
                        fenetre.postMessage({
                            type: 'notification-clic',
                            notification: evenement.notification.data
                                ? evenement.notification.data.notification
                                : null
                        });
                        return fenetre.focus();
                    }
                }
                if (self.clients.openWindow) {
                    return self.clients.openWindow(destination);
                }
                return null;
            })
    );
});
