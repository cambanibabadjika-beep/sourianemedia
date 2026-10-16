# Souriane — Propositions d'améliorations et de fonctionnalités

> Document de travail — 16 octobre 2026
> Rédigé après la livraison du lot « abonnement plateforme + page Découvrir + mode sombre admin ».
> Chaque proposition est ancrée sur le code existant (fichiers, tables, services nommés)
> et porte une estimation d'effort honnête. Rien ici n'est implémenté : c'est une liste
> à arbitrer.

---

## 0. Ce qui vient d'être livré (point de départ)

| Sujet | Livrable | Preuve |
|---|---|---|
| Abonnement « portée plateforme » | `abonnes.portee`, `Abonne::actifs()` sert annonceur **ou** plateforme | `tests/verifier_abonnement_plateforme.php` — 17/17 |
| Page « Découvrir les offres » | `/decouvrir`, `PublicController::decouvrir()`, `Page::publieesAvecOffre()` | HTTP 200, 3 cartes rendues en base réelle |
| Mode sombre admin | Bloc `:root[data-theme="dark"]` complet dans `assets/css/admin.css` | `tests/verifier_contraste.php` — 43/43 |

Les propositions ci-dessous **partent de cet état** et ne le remettent pas en cause.

### Légende

- **Impact** — bénéfice réel pour Nazaire / les annonceurs / les visiteurs.
- **Effort** — S (≈ une demi-journée), M (1–2 jours), L (3 jours et plus).
- ⚠️ = touche à de la donnée existante ou à une protection : à faire avec précaution.

---

## Axe A — Notifications & audience
*Le cœur de la demande initiale. C'est ici que le plus de valeur reste à prendre.*

### A1. Résumé hebdomadaire « plateforme » (digest)
**Impact : élevé — Effort : M**

Aujourd'hui, `abonnes.frequence` vaut `chaque_annonce` ou `hebdomadaire`, mais la fréquence
`hebdomadaire` ne change **que le rythme par annonceur** : un abonné plateforme peut encore
recevoir plusieurs notifications dans la semaine, une par annonceur. Un vrai digest
consisterait à :

- regrouper toutes les annonces de la semaine en **une seule notification** ;
- la construire dans `DiffusionService` (nouvelle méthode `composerDigest()`) ;
- la déclencher depuis `cron/envoyer_notifications.php` (déjà planifié toutes les 5 min —
  il suffit d'ajouter une fenêtre « lundi 9 h »).

Bénéfice : respecte mieux le plafond de 4 notifications/mois et donne une raison de rester
abonné sans saturer. C'est la suite logique de `portee = plateforme`.

### A2. Choix des annonceurs (abonnement sélectif)
**Impact : moyen — Effort : M**

Aujourd'hui c'est binaire : soit un annonceur, soit toute la plateforme. On pourrait offrir
un troisième mode : **« je choisis mes annonceurs »** avec des cases à cocher dans la feuille
`feuille-informe.php`. Techniquement, cela demande une table de liaison
`abonnes_annonceurs (abonne_id, annonceur_id)` — d'où l'effort M et le statut ⚠️ (migration).

### A3. Catégories d'offres + filtres d'abonnement
**Impact : moyen — Effort : M**

Ajouter une colonne `annonceurs.categorie` (alimentation, santé, mode, services…) et laisser
l'abonné plateforme cocher les catégories qui l'intéressent. Rend la page `/decouvrir`
filtrable et prépare des statistiques utiles pour les annonceurs.

### A4. Centre de notifications in-app (historique visible)
**Impact : moyen — Effort : S**

`Notification.php` et la table `envois` existent déjà. On pourrait exposer un
`/mes-notifications` (ou un tiroir dans la page annonceur) qui liste les dernières
notifications reçues par cet appareil. Réutilise `EF-E10` (historique déjà présent côté
annonceur) et le rend visible côté visiteur.

### A5. Notification « nouvelle offre dans votre ville »
**Impact : moyen — Effort : S**

`abonnes` porte déjà `ville` (via l'annonceur). Lorsqu'une nouvelle page est publiée dans la
ville de l'abonné, envoyer une notification ciblée. Petit ajout dans `DiffusionService`.

---

## Axe B — Back-office admin
*Confort d'exploitation. Le mode sombre est corrigé ; voici ce qui manque encore.*

### B1. Tableau de bord « abonnés plateforme »
**Impact : moyen — Effort : S**

`Abonne::compterPlateforme()` existe déjà mais **n'est affiché nulle part**. Le brancher sur
`app/views/admin/tableau-de-bord.php` (une tuile de plus) donne enfin de la visibilité sur
l'audience plateforme. Le plus petit effort / bénéfice de toute cette liste.

### B2. Statistiques de diffusion par annonceur
**Impact : élevé — Effort : M**

La table `envois` journalise chaque envoi. Une vue admin « performance » par annonceur
(notifications envoyées, taux d'ouverture si on ajoute un pixel, abonnés actifs) aiderait à
vendre le service aux annonceurs. C'est un argument commercial, pas juste du confort.

### B3. Import / export CSV
**Impact : moyen — Effort : S**

Export des demandes (`demandes`), des abonnés (`abonnes`) et des annonceurs en CSV, avec
import pour les annonceurs. Évite la saisie manuelle et permet une sauvegarde hors base.

### B4. Corbeille / restauration
**Impact : moyen — Effort : M — ⚠️**

Aujourd'hui une suppression d'annonceur ou de page est définitive. Une colonne `supprime_le`
(soft delete) sur les tables sensibles permettrait de restaurer. Touche à la donnée : à faire
avec un script de migration testé.

### B5. Journal technique filtrable et exportable
**Impact : faible-moyen — Effort : S**

`app/views/admin/journal.php` et `journal_operations` existent. Ajouter des filtres
(date, niveau, acteur) et un export facilite le diagnostic à distance.

### B6. Édition des paramètres par l'interface
**Impact : moyen — Effort : S**

La table `parametres` est déjà là, `Parametre.php` aussi. S'assurer que **toutes** les clés
opérationnelles (délai de publication, plafond mensuel, libellés du site) sont éditables dans
`admin/parametres.php` sans passer par le SQL.

---

## Axe C — Annonceurs & offres

### C1. Gestion des dates de validité des offres — ✅ LIVRÉ (16/10)
**Impact : élevé — Effort : S**

Le défaut était réel : `Page::publieesAvecOffre()` (ligne 75 de `app/models/Page.php`)
ne filtre que `p.statut = 'publiee'` et `a.statut = 'actif'`, et
`PublicController::decouvrir()` lisait `debut` / `fin` sans jamais les exploiter — une
offre périmée restait donc affichée indéfiniment sur `/decouvrir`.

Livré :

- `offre_expiree()` (`app/helpers.php`) — une date absente ou illisible ne périme rien ;
  seule une échéance franchement dépassée compte.
- Filtre dans la boucle de `decouvrir()`. Les offres sans date de fin et celles dont le
  début est à venir restent visibles : la carte les annonce « À partir du … ».
- Badge « Expirée » et rappel de la conséquence dans `admin/page-form.php`.
- `tests/verifier_offres.php` — **22 vérifications**, dont la falsification : filtre
  neutralisé, la suite échoue bien sur « l'offre expirée est absente ».

Le filtre reste en PHP (après `json_lire()`), comme prévu : aucune migration.

### C2. Galerie d'images / carrousel d'offres
**Impact : moyen — Effort : M**

`Page.php` interroge déjà `galerie_contenu`. On pourrait l'exposer dans la carte
`.offre-carte` (mini-carrousel) pour des offres plus vendeuses que la seule image de bandeau.

### C3. Partage social des offres
**Impact : faible-moyen — Effort : S**

Boutons de partage (WhatsApp en priorité — déjà utilisé dans la feuille
`feuille-informe.php`) sur chaque carte d'offre. Cohérent avec les usages de la région.

### C4. Favoris côté visiteur
**Impact : faible — Effort : M**

Marquer une offre en favori (localStorage d'abord, compte ensuite). Utile seulement si les
visiteurs reviennent souvent — à valider par la mesure avant de construire.

---

## Axe D — Sécurité & robustesse
*Le socle est bon (PDO préparé, CSRF, 2FA TOTP, rate limiter). Voici les angles restants.*

### D1. Réglage du limiteur de débit par environnement
**Impact : moyen — Effort : S — ⚠️**

`RateLimiter` écrit dans `storage/limites/`. Le dossier n'est pas vidé entre les suites de
tests, ce qui a déjà provoqué un échec intermittent. Ajouter un paramètre (fenêtre, seuil)
**lu depuis `parametres`** plutôt que codé en dur rendrait le réglage explicite. ⚠️ Ne jamais
affaiblir la protection : seulement la rendre configurable.

### D2. Rotation et purge des logs
**Impact : moyen — Effort : S**

`storage/*.log` grossit sans limite. Une tâche cron de rotation (ou une purge au-delà de N
Mo) évite de saturer le disque en production.

### D3. En-têtes de sécurité HTTP
**Impact : moyen — Effort : S**

Vérifier / compléter dans `.htaccess` : `Content-Security-Policy`, `X-Content-Type-Options`,
`Referrer-Policy`, `Permissions-Policy`. Le CSP est le plus délicat (Leaflet, styles inline)
— à construire en `report-only` d'abord.

### D4. Audit de sécurité de l'app déployée
**Impact : élevé — Effort : M**

Une fois en ligne, passer l'application au crible : en-têtes, cookies (attributs
`HttpOnly`/`Secure`/`SameSite`), exposition de `.env`, énumération d'utilisateurs,
messages d'erreur fuitant des chemins. Chaque affirmation prouvée par une requête HTTP.

### D5. Sauvegardes automatiques de la base — ✅ LIVRÉ (16/10)
**Impact : élevé — Effort : S**

Il n'existait aucune sauvegarde : ni script, ni planification, ni dossier de destination.
C'est le risque le plus concret du projet — une base perdue ne se reconstruit pas depuis le
code.

Livré :

- `cron/sauvegarder_base.php` — dump complet par `mysqldump`
  (`--single-transaction --quick --routines --events`), puis rotation ne conservant que les
  N plus récentes (`--garder`, défaut 7, plafond 365).
- **Un dump incomplet est refusé et supprimé.** `mysqldump` termine toujours par
  « -- Dump completed on … » ; le script exige ce marqueur, *et* un `CREATE TABLE` en tête,
  *et* un fichier de plus de 1 ko. Sans ce contrôle, un disque plein laisserait un fichier
  tronqué portant le nom d'une sauvegarde valide — une fausse assurance, pire que pas de
  sauvegarde du tout.
- **Aucun repli en PHP.** Un dump reconstruit à la main laisserait de côté les vues, les
  index et l'encodage, tout en portant le nom d'une sauvegarde. Mieux vaut un échec visible
  qu'une fausse sécurité : `mysqldump` introuvable → erreur explicite, jamais de fichier
  trompeur.
- Identifiants passés par un fichier `--defaults-extra-file` temporaire (chmod 0600,
  supprimé dans le `finally`) : un mot de passe en argument est visible de tout utilisateur
  capable de lister les processus.
- Verrou `flock` : deux passages simultanés ne peuvent pas tronquer le dump l'un de l'autre.
- **Réservé à la ligne de commande** — un appel HTTP reçoit 403. Une sauvegarde déclenchable
  par le web permettrait de remplir le disque à volonté.
- `tests/verifier_sauvegarde.php` — **23 vérifications**. Le contrôle de complétude et la
  rotation ont été **falsifiés** : le premier retiré, le script annonce « Sauvegarde
  écrite : … (0 ko) » et la suite échoue ; la seconde neutralisée, six sauvegardes subsistent
  au lieu de deux.
- Accès HTTP aux dumps éprouvé, et non supposé : `storage/sauvegardes/` répond **403**. Les
  deux verrous (la règle `RewriteRule` de la racine et `storage/.htaccess`) ont été retirés
  l'un après l'autre pour vérifier que la suite détecte leur absence — sans eux, le dump part
  en **200**.

Planification documentée dans le README (§5 bis : crontab et Planificateur de tâches
Windows), restauration comprise.

---

## Axe E — Accessibilité & performance

### E1. Audit d'accessibilité complet (au-delà du contraste)
**Impact : moyen — Effort : M**

`verifier_contraste.php` couvre les couleurs (43/43). Il reste : navigation clavier, focus
visible, `aria-*` sur la feuille glissante et les onglets admin, labels de formulaires,
alternatives textuelles des images. Un test dédié (comme le test de contraste) rendrait ces
points vérifiables.

### E2. Budget de performance
**Impact : moyen — Effort : S**

Mesurer et fixer un budget : taille du CSS/JS, poids des images d'offres, nombre de requêtes
SQL par page. Le script `capturer_site.js` peut servir de base. Évite la dérive silencieuse.

### E3. Optimisation des images à l'upload
**Impact : moyen — Effort : M**

Les visuels d'offres/panneaux sont stockés tels quels. Générer des vignettes (WebP, plusieurs
tailles) à l'upload allégerait fortement `/decouvrir` sur mobile.

---

## Axe F — PWA / application mobile

### F1. Coquille applicative native vs PWA
**Impact : à évaluer — Effort : L**

La PWA actuelle (manifest + service worker) suffit pour la plupart des usages. Une vraie
application (Capacitor / React Native) n'apporte de valeur que si l'on a besoin de
notifications **fiables en arrière-plan sur iOS** ou d'un accès au matériel. Décision à
prendre seulement si un besoin concret se présente — sinon, rester en PWA.

### F2. Écran d'installation guidé et suivi
**Impact : faible — Effort : S**

Le tutoriel iOS existe (`tutoriel-ios`). Ajouter un suivi d'installation (savoir combien de
visiteurs installent) aide à mesurer l'effet de la PWA.

### F3. Mode hors-ligne plus généreux
**Impact : faible-moyen — Effort : M**

Le service worker met en cache l'essentiel. Mettre en cache la dernière liste d'offres
consultée permettrait de la revoir sans réseau — utile en connexion instable.

---

## Axe G — Exploitation & outillage

### G1. Suite de tests unifiée
**Impact : élevé — Effort : S**

Les vérifications sont des scripts séparés (`verifier_*.php`, `*.js`). Un lanceur unique
(`tests/lancer_tout.sh` + un `npm run test`) qui exécute tout, agrège les compteurs et
renvoie un code de sortie non nul en cas d'échec rendrait la vérification systématique. Il
manque aussi une note explicite sur les deux échecs **externes attendus**
(`verifier_api.php` 2FA désactivée localement, `verifier_admin.php` sur PHP 8.2 vs 8.3).

### G2. Mise à jour du README
**Impact : moyen — Effort : S**

Le README (545 lignes) ne mentionne pas encore : la route `/decouvrir`, la colonne
`abonnes.portee`, la migration `sql/abonnement-plateforme.sql`, ni le `VERSION` du service
worker (`souriane-v11`). À compléter pour que la doc reste la source de vérité.

### G3. Environnement de recette isolé
**Impact : moyen — Effort : M**

Un second jeu de configuration (`.env.recette`, base séparée) pour tester les migrations
avant de les appliquer en production. Réduit le risque des changements ⚠️ ci-dessus.

### G4. Journalisation des erreurs applicatives
**Impact : moyen — Effort : S**

Vérifier qu'un gestionnaire d'exceptions global journalise proprement (sans fuiter de chemin
ni de trace en production) et affiche une page d'erreur soignée (`public/erreur.php` existe
déjà — s'assurer qu'elle est bien branchée en production).

---

## Récapitulatif priorisé

| # | Proposition | Axe | Impact | Effort | Note |
|---|---|---|---|---|---|
| B1 | Afficher le compteur d'abonnés plateforme | Admin | Moyen | S | Helper déjà écrit |
| C1 | Filtrer les offres expirées | Offres | Élevé | S | ✅ Livré le 16/10 |
| D5 | Sauvegardes automatiques de la base | Sécurité | Élevé | S | ✅ Livré le 16/10 |
| A1 | Digest hebdomadaire plateforme | Notifications | Élevé | M | Suite logique de la demande |
| G1 | Suite de tests unifiée | Outillage | Élevé | S | Qualité continue |
| G2 | Mise à jour du README | Outillage | Moyen | S | Doc = vérité |
| D4 | Audit sécurité de l'app déployée | Sécurité | Élevé | M | Avant/après mise en ligne |
| B2 | Statistiques de diffusion par annonceur | Admin | Élevé | M | Argument commercial |
| D1 | Limiteur de débit configurable | Sécurité | Moyen | S | ⚠️ Ne pas affaiblir |
| A2 | Abonnement sélectif par annonceur | Notifications | Moyen | M | ⚠️ Migration |
| A3 | Catégories + filtres d'abonnement | Notifications | Moyen | M | Prépare les stats |
| E1 | Audit d'accessibilité complet | Accessibilité | Moyen | M | Au-delà du contraste |
| E3 | Optimisation des images à l'upload | Performance | Moyen | M | Mobile d'abord |
| B4 | Corbeille / restauration | Admin | Moyen | M | ⚠️ Soft delete |
| F1 | Coquille native vs PWA | Mobile | À évaluer | L | Décision, pas implémentation |

---

## Ma recommandation pour commencer

C1 et D5 sont livrés. Il reste, dans cet ordre :

1. **B1 — Afficher le compteur d'abonnés plateforme.** Le code est déjà là
   (`compterPlateforme()`), il ne manque que l'affichage. Le meilleur ratio effort/bénéfice.
2. **G1 — Suite de tests unifiée.** Onze suites à lancer à la main, c'est onze occasions d'en
   oublier une — l'ajout de `verifier_offres.php` puis de `verifier_sauvegarde.php` vient de
   le montrer deux fois de suite.
3. **A1 — Synthèse hebdomadaire.** Si l'audience plateforme grandit.

---

*Ce document n'engage rien. Dis-moi quels points tu veux retenir et je les transforme en
plan d'exécution vérifié, un lot à la fois.*
