# Souriane — plateforme de panneaux connectés

Plateforme d'affichage extérieur connecté : chaque panneau porte un QR code qui
ouvre la page d'un annonceur local, dans un ordre de présentation imposé. Les
visiteurs peuvent installer le site comme application (PWA), s'abonner aux
notifications de l'annonceur, consulter le parc sur une carte interactive,
visualiser des vidéos courtes dans la galerie, et retrouver l'historique des
offres reçues. Les campagnes sont pilotées par une régie, les poses contrôlées
par un agent terrain.

- **PHP 8.3**, sans framework ni dépendance Composer
- **MySQL 8+** (testé sur 9.1), moteur InnoDB, `utf8mb4_unicode_ci`
- **PDO** en requêtes préparées exclusivement, émulation désactivée
- **Vanilla JS**, aucune bibliothèque côté client (sauf Leaflet embarqué pour la carte du parc)

---

## 1. Prérequis

| Élément | Version | Remarque |
|---|---|---|
| PHP | 8.3+ | extensions `pdo_mysql`, `openssl`, `mbstring`, `json`, `gd` (facultatif) |
| MySQL / MariaDB | 8.0+ | InnoDB, `utf8mb4` |
| Apache | 2.4+ | `mod_rewrite`, `mod_headers`, `mod_deflate`, `mod_expires` |
| HTTPS | — | **obligatoire** en production : service worker et notifications push |

Sans HTTPS (ou `localhost`), le site fonctionne mais le service worker,
l'installation PWA et les notifications restent indisponibles.

---

## 2. Installation

### 2.1 Fichiers

Placer le contenu du dépôt dans la racine web, par exemple
`/var/www/souriane` ou `C:\wamp64\www\sourianemedia`. Le document root doit
pointer sur ce dossier : `index.php` est le seul point d'entrée public.

### 2.2 Base de données

```bash
mysql -u root -p < sql/souriane.sql     # structure (18 tables, 2 vues) + démonstration
```

Ce fichier unique contient la structure, les vues et un jeu de démonstration :
trois annonceurs, trois campagnes, cinq panneaux et trois comptes. Pour une
installation en production, supprimer la dernière section
« JEU DE DONNÉES DE DÉMONSTRATION » avant l'import.

### 2.3 Configuration

```bash
cp .env.example .env
```

Renseigner au minimum :

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.tld

DB_HOST=127.0.0.1
DB_NAME=souriane
DB_USER=souriane
DB_PASS=motdepasse

HASH_SECRET=<64 caractères hexadécimaux>
```

Générer le secret de hachage des empreintes réseau :

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Ce secret sert à dériver une empreinte non réversible de l'adresse réseau des
visiteurs. **Aucune adresse IP n'est jamais stockée en clair** (ch. 9).

### 2.4 Clés de notification

```bash
php cron/generer_cles.php
```

Le script produit la paire de clés VAPID, l'écrit dans `.env` et dans la table
`parametres`. Une rotation des clés invalide les abonnements existants.

### 2.5 Droits d'écriture

Seul le dossier `storage/` doit être accessible en écriture par le serveur web :

```bash
chmod -R 775 storage
```

Il contient les journaux, les compteurs de limitation de débit et les
téléversements. Les dossiers `app/`, `sql/`, `cron/` et `tests/` sont protégés
par `.htaccess` et ne doivent jamais être servis.

### 2.6 Comptes de démonstration

| Adresse | Mot de passe | Rôle | 2FA |
|---|---|---|---|
| `admin@souriane.sn` | `Souriane2026!` | Régie — accès complet | préactivé (clé demo) |
| `agent@souriane.sn` | `Souriane2026!` | Agent terrain | préactivé (clé demo) |
| `annonceur@souriane.sn` | `Souriane2026!` | Annonceur | désactivé |

**Ces comptes doivent être supprimés ou re-mot de passe avant toute mise en
production.**

---

## 2 bis. Accès depuis un autre appareil (téléphone, tablette)

Le poste sert l'application sur **toutes ses interfaces réseau** : un téléphone
connecté au même Wi-Fi ouvre donc le site directement par l'adresse IP du poste,
sans rien installer.

Sous Windows, le lanceur fait tout :

```bash
demarrer-local.bat
```

Il démarre MySQL et Apache s'ils ne tournent pas déjà, **sonde le site jusqu'à
obtenir une vraie réponse HTTP 200** (aucun délai fixe, qui déclarerait un
démarrage à froid en échec), puis affiche les deux adresses :

```
Sur cet ordinateur : http://localhost/sourianemedia/
Sur le telephone   : http://192.168.1.10/sourianemedia/
```

Aucune configuration n'est nécessaire : `APP_URL` est vide, donc l'application
déduit son adresse de base de l'en-tête `Host` reçu. Les liens, les feuilles de
style et les appels d'API s'adaptent automatiquement à l'adresse utilisée.

### Ce qui a été vérifié sur le poste

| Point | État relevé |
|---|---|
| Écoute d'Apache | `0.0.0.0:80` et `[::]:80` — toutes les interfaces, pas seulement localhost |
| Accès par l'IP du LAN | `curl http://192.168.1.10/sourianemedia/` → **200** |
| URL engendrées | absolues et sur la bonne IP (`http://192.168.1.10/…`), aucun `localhost` résiduel |
| `httpd.conf` | `<Directory "C:/xampp/htdocs">` → `Require all granted`, aucun hôte virtuel restrictif |
| Redirection HTTPS / en-tête HSTS | aucune |
| Pare-feu Windows | entrant bloqué par défaut, **mais** la règle « Apache HTTP Server » (Autoriser, TCP+UDP, tous ports) est active |
| Profil réseau du Wi-Fi | **Public** — précisément le profil couvert par cette règle |
| Pare-feu ou antivirus tiers | aucun (Windows Defender uniquement) |

Autrement dit : **rien, sur le poste, ne bloque le téléphone.** Si l'accès échoue,
la cause est en amont — dans le réseau, ou sur le téléphone.

### Isoler la cause en trois essais

1. **Depuis le téléphone**, ouvrir `http://<ip>/` — la page d'accueil XAMPP, sans
   le `/sourianemedia`.
   - Elle s'affiche → le réseau passe, le problème est applicatif.
   - Elle échoue aussi → c'est le réseau, pas l'application. Passer au point 2.
2. **Vérifier que le téléphone est sur le même réseau** : Wi-Fi et non données
   mobiles, même SSID que le poste, et **VPN désactivé** — un VPN actif détourne
   tout le trafic et rend le réseau local injoignable.
3. **Isolation des clients.** Beaucoup de box et de systèmes maillés interdisent
   par défaut à deux appareils du même Wi-Fi de se parler : « isolation des
   clients », « isolation sans fil », « AP isolation », ou un SSID invité. C'est
   la cause la plus fréquente lorsque tout le reste est correct. La désactiver
   dans l'interface de la box, ou brancher le poste en Ethernet.

Pour distinguer 2 et 3 : depuis le téléphone, ouvrir l'interface d'administration
de la box (`http://192.168.1.1`). Si elle répond mais pas le poste, l'isolation
des clients est en cause ; si rien ne répond, le téléphone n'est pas sur ce réseau.

### Si la règle du pare-feu ne couvre pas le profil actif

La règle livrée est limitée au profil **Public**. Si le Wi-Fi du poste est
requalifié en **Privé** — Windows le fait parfois après un redémarrage de la box —
elle ne s'applique plus et tout accès entrant est refusé. Vérifier, puis élargir :

```powershell
Get-NetConnectionProfile | Select-Object InterfaceAlias, NetworkCategory
Get-NetFirewallRule -Direction Inbound | Where-Object DisplayName -like '*Apache*' |
    Select-Object DisplayName, Profile, Action
Set-NetFirewallRule -DisplayName 'Apache HTTP Server' -Profile Any
```

**Limite importante — HTTPS.** En HTTP, sur une adresse qui n'est pas
`localhost`, le navigateur considère la page comme non sécurisée : le service
worker, l'installation PWA et **les notifications push** restent indisponibles.
C'est une règle des navigateurs, pas un défaut de l'application. Pour tester les
notifications depuis le téléphone, servir le site en HTTPS (tunnel `cloudflared`
ou `ngrok`, ou certificat local) — voir la section 8.

> Le lanceur `.bat`, les scripts `.sh` et `.ps1` sont bloqués par `.htaccess` :
> ils ne sont pas téléchargeables depuis le réseau, car ils révèlent les chemins
> locaux du poste.

---

## 3. Arborescence

```
index.php                  point d'entrée unique
service-worker.js          cache, mode hors ligne, réception des notifications
manifest.webmanifest       métadonnées d'installation PWA
.htaccess                  réécriture, durcissement, cache, compression
demarrer-local.bat         lanceur Windows : Apache + MySQL, puis adresse réseau

app/
  bootstrap.php            amorçage : autoload PSR-4, session, erreurs
  routes.php               table de routage (l'ordre fait la priorité)
  helpers.php              échappement, URL, dates, étiquettes d'état
  config/config.php        lecture du .env, constantes applicatives
  core/                    Router, Request, Response, Database, Auth,
                           Csrf, RateLimiter, Session, View, Flash, Icone,
                           Totp (RFC 6238), Coffre (AES-256-GCM)
  controllers/             Public, Api, Auth, Admin, Cron
  models/                  11 modèles, requêtes préparées uniquement
  services/                QrService, PushService, DiffusionService,
                           SecondFacteur (TOTP, codes de secours)
  views/
    layouts/               public.php, admin.php
    partials/              logo, en-tête, pied de page, messages
    public/                vitrine, page-annonceur, parc, statique, erreur
    admin/                 18 vues du back-office + connexion 2FA + sécurité

assets/
  css/app.css              design system public
  css/admin.css            back-office
  js/app.js                mesure d'audience, PWA, notifications, historique
  js/parc-carte.js         carte interactive du parc (Leaflet)
  lib/leaflet/             Leaflet 1.9.4 embarqué (vendored)
  img/                     logo, favicons, icônes PWA

app/lang/                  catalogues de traduction (fr, wo…)

cron/
  generer_cles.php         génération des clés VAPID
  envoyer_notifications.php  traitement de la file d'envoi
  sauvegarder_base.php     sauvegarde de la base + rotation (ligne de commande)
  preparer_2fa_demo.php    active le second facteur sur les comptes démo

sql/                       structure (16 tables, 3 vues) + migration 2FA
storage/                   journaux, limitations, téléversements (inscriptible)
  sauvegardes/             dumps SQL datés (hors web : 403)
tests/
  verifier_api.php         API JSON (CSRF, abonnements, limitation, 2FA)
  verifier_admin.php       back-office (rubriques, rôles, écritures, 2FA)
  verifier_cron.php        file d'envoi et plafond mensuel
  verifier_limiteur.php    limiteur : échecs, fenêtre, réinitialisation
  verifier_abonnement_plateforme.php  abonnement « portée plateforme »
  verifier_demande.php     formulaire public de demande
  verifier_offres.php      validité des offres sur /decouvrir
  verifier_sauvegarde.php  dump complet, rotation, refus d'un dump tronqué
  verifier_contraste.php   contrastes WCAG de la charte
  verifier_totp.php        RFC 6238, base32, anti-rejeu, codes de secours
  verifier_animations.js   animations et rendu du bloc chiffres (navigateur)
  audit_sombre.js          contraste réel des 20 pages en thème sombre
  generer_icones.js        régénère les icônes depuis le logo
  capturer_site.js         captures d'écran du site et du back-office
```

---

## 4. Routage

| Méthode | Chemin | Rôle |
|---|---|---|
| GET | `/` | vitrine publique |
| GET | `/c/{code}` | résolution du QR code → redirection vers la page |
| GET | `/comment-ca-marche` | explication du dispositif |
| GET | `/annonceurs` | annuaire des annonceurs |
| GET | `/le-parc` | parcs et panneaux |
| GET | `/decouvrir` | offres en cours des annonceurs (hors offres expirées) |
| GET | `/mentions-legales`, `/confidentialite`, `/accessibilite` | pages légales |
| GET | `/{adresse}` | page publique d'un annonceur |
| GET | `/api/sante` | sonde de disponibilité |
| POST | `/api/evenements` | journalisation des interactions (lot) |
| POST | `/api/abonnements` | enregistrement d'un abonnement push |
| POST | `/api/abonnements/suppression` | désabonnement |
| GET | `/api/campagnes/{id}/statistiques` | statistiques (authentifié) |
| GET | `/api/annonceurs/{id}/notifications` | historique public des notifications |
| GET/POST | `/tache/notifications` | déclencheur de la tâche planifiée |
| GET/POST | `/admin/connexion` | connexion (mot de passe) |
| GET/POST | `/admin/connexion/code` | second facteur TOTP |
| GET/POST | `/admin/connexion/enrolement` | enrôlement 2FA |
| GET/POST | `/admin/securite` | sécurité du compte (2FA, codes de secours) |
| GET/POST | `/admin/...` | back-office (rôle requis par rubrique) |

Le motif `/{adresse}` est déclaré **en dernier** : c'est le plus générique.

---

## 5. Tâche planifiée

### En ligne de commande

```bash
php cron/envoyer_notifications.php --verbeux
```

À planifier toutes les cinq minutes :

```
*/5 * * * * /usr/bin/php /var/www/souriane/cron/envoyer_notifications.php >> /var/log/souriane-cron.log 2>&1
```

Options : `--limite=N` (20 par défaut, 200 au plus), `--silencieux`,
`--verbeux`, `--aide`.

### Sur un hébergement sans crontab

Le dossier `cron/` est totalement inaccessible par le web. Un planificateur en
ligne peut appeler le point d'entrée dédié :

```
https://votre-domaine.tld/tache/notifications?cle=<CRON_TOKEN>
```

Le jeton se définit dans `.env` (`php -r "echo bin2hex(random_bytes(24));"`).
Il est comparé en temps constant et l'accès est limité à dix appels par minute.

Un verrou exclusif empêche deux passages simultanés d'envoyer deux fois les
mêmes notifications.

---

## 5 bis. Sauvegarde de la base

`cron/sauvegarder_base.php` produit un dump SQL complet dans
`storage/sauvegardes/`, puis ne conserve que les sept plus récents.

```bash
php cron/sauvegarder_base.php                # dump + rotation (7 conservés)
php cron/sauvegarder_base.php --garder=30    # en conserver 30
php cron/sauvegarder_base.php --aide         # options
```

À planifier chaque nuit à 3 h :

```
0 3 * * * /usr/bin/php /var/www/souriane/cron/sauvegarder_base.php --silencieux
```

Sous Windows, par le Planificateur de tâches :

| Champ | Valeur |
|---|---|
| Programme | `C:\xampp\php\php.exe` |
| Arguments | `C:\xampp\htdocs\sourianemedia\cron\sauvegarder_base.php --silencieux` |
| Déclencheur | quotidien, 03:00 |

Restauration — le dump contient `CREATE DATABASE` et `USE` :

```bash
mysql -u souriane -p < storage/sauvegardes/souriane-2026-10-16-171437.sql
```

**Ce que le script refuse.** `mysqldump` termine toujours par
`-- Dump completed on …`. Un fichier dépourvu de ce marqueur, ou sans
`CREATE TABLE`, ou pesant moins de 1 ko, est **supprimé** et le script sort en
erreur. Sans ce contrôle, un disque plein laisserait un fichier tronqué portant
le nom d'une sauvegarde valide — une fausse assurance, pire que pas de
sauvegarde du tout.

**Aucun repli en PHP.** Si `mysqldump` est introuvable, le script échoue
franchement : un dump reconstruit à la main laisserait de côté les vues, les
index et l'encodage, tout en portant le nom d'une sauvegarde. Le chemin du
binaire est cherché parmi les emplacements usuels et peut être forcé par
`MYSQLDUMP_CHEMIN` dans `.env`.

**Réservé à la ligne de commande.** Un appel HTTP reçoit 403 : une sauvegarde
déclenchable par le web permettrait de remplir le disque à volonté. Le dossier
`storage/sauvegardes/` n'est pas servi non plus — éprouvé par
`tests/verifier_sauvegarde.php`, qui y dépose un faux dump et exige un 403.

---

## 6. Notifications : contraintes respectées

- **Plafond de quatre notifications par mois et par annonceur** (ch. 5.5).
  Un cinquième envoi est refusé et l'incident est consigné au journal
  technique. Vérifié par `tests/verifier_cron.php`.
- **Deux formules d'abonnement** : chaque annonce (envoi immédiat) ou
  hebdomadaire (synthèse groupée). Seuls les abonnés « chaque annonce »
  reçoivent les envois immédiats.
- **Désactivation automatique** : un abonnement qui répond 404 ou 410 côté
  service de push est désactivé sans intervention.
- **iOS** : l'abonnement n'est possible qu'après installation sur l'écran
  d'accueil, à partir d'iOS 16.4. En deçà, l'interface propose un repli
  WhatsApp.
- **HTTPS obligatoire** : sans certificat, les notifications sont annoncées
  comme indisponibles plutôt que silencieusement inopérantes.

---

## 6 bis. Second facteur (ch. 8)

Les comptes `regie` et `agent` sont protégés par un second facteur TOTP
(RFC 6238) sans dépendance externe :

- **Secret** : 160 bits, chiffré en AES-256-GCM via `Coffre` (clé dérivée avec
  HKDF), jamais stocké en clair.
- **QR d'enrôlement** : URI `otpauth://` générée par `QrService` (SVG inline).
- **Codes de secours** : 8 codes à usage unique, hashés avec SHA-256.
- **Anti-rejeu** : chaque pas de 30 s ne peut être utilisé qu'une fois ; le
  champ `totp_dernier_pas` bloque la réutilisation.
- **Enrôlement forcé** : un compte `regie` ou `agent` sans 2FA actif est
  redirigé vers l'écran d'enrôlement dès la connexion. L'annonceur peut
  l'activer volontairement.
- **Dérogation de développement** : `SECURITE_2FA_SANS_CODE=true` dispense de
  saisir le code à la connexion. Elle n'efface rien — secrets et codes de
  secours restent en base — et se rétablit en repassant le réglage à `false`.
  Elle est **refusée si `APP_ENV=production`** (voir `app/config/config.php`).
  À ne pas confondre avec `SECURITE_2FA_ROLES`, qui régit l'enrôlement *imposé* :
  vider la liste n'exempte pas un compte *déjà* enrôlé de saisir son code.

La migration `sql/2fa.sql` ajoute les cinq colonnes nécessaires à la table
`utilisateurs`. Le script `cron/preparer_2fa_demo.php` active le 2FA sur les
comptes de démonstration avec une clé publique (RFC 6238 Annexe B) pour
permettre aux tests de lire le code attendu sans backdoor.

---

## 6 ter. Validité des offres

Les dates de validité d'une offre ne sont pas des colonnes SQL : elles vivent
dans le JSON de la section `offre` (`debut`, `fin`), au format `Y-m-d`. Le
contrôle se fait donc en PHP, après `json_lire()`, et non dans la requête.

La page `/decouvrir` annonce « Jusqu’au … » sur chaque carte : elle doit donc
respecter cette échéance. Une offre dont la `fin` est dépassée est **écartée**
(`offre_expiree()`, `app/helpers.php`). Deux cas ne sont *pas* filtrés, par
choix :

- **sans date de fin** — l'offre n'a pas d'échéance, elle reste en cours ;
- **dont le début est à venir** — la carte l'annonce « À partir du … », c'est
  une offre que l'on peut déjà montrer.

Le formulaire d'administration (`admin/page-form.php`) porte un badge
« Expirée » et rappelle la conséquence dès que la date de fin est dépassée.

`tests/verifier_offres.php` (22 vérifications) crée trois offres réelles —
expirée, en cours, sans échéance — interroge la vraie page par HTTP, contrôle
le badge côté admin, puis supprime ses données.

---

## 7. Vérifications

Onze suites couvrent le parcours complet — dix en PHP, une dans un vrai
navigateur — plus un audit de contraste du thème sombre. Elles nécessitent que
le site soit servi et que la base de démonstration soit chargée.

```bash
php tests/verifier_api.php                    # API JSON : CSRF, abonnements, limitation, 2FA
php tests/verifier_admin.php                  # back-office : rubriques, rôles, écritures, 2FA
php tests/verifier_cron.php                   # file d'envoi et plafond mensuel
php tests/verifier_limiteur.php               # limiteur : échecs, fenêtre, réinitialisation
php tests/verifier_abonnement_plateforme.php  # abonnement « portée plateforme »
php tests/verifier_demande.php                # formulaire public de demande
php tests/verifier_offres.php                 # validité des offres sur /decouvrir
php tests/verifier_sauvegarde.php             # dump complet, rotation, refus d'un dump tronqué
php tests/verifier_contraste.php              # contrastes WCAG de la charte
php tests/verifier_totp.php                   # RFC 6238, base32, anti-rejeu, codes de secours

node tests/verifier_animations.js             # animations, dans un vrai navigateur
node tests/audit_sombre.js                    # contraste réel des 20 pages en thème sombre
```

Avant la première exécution, préparer le second facteur des comptes de
démonstration — sinon les suites ne peuvent pas franchir l'écran de code :

```bash
php cron/preparer_2fa_demo.php
```

État attendu :

| Suite | Vérifications |
|---|---|
| `verifier_api.php` | 62 |
| `verifier_admin.php` | 155 (17 échecs attendus — voir la note) |
| `verifier_cron.php` | 28 |
| `verifier_limiteur.php` | 11 |
| `verifier_abonnement_plateforme.php` | 17 |
| `verifier_demande.php` | 12 |
| `verifier_offres.php` | 22 |
| `verifier_sauvegarde.php` | 23 |
| `verifier_contraste.php` | 54 |
| `verifier_totp.php` | 33 |
| `verifier_animations.js` | 31 |
| **Total** | **448** |

> **Second facteur en local.** Deux réglages allègent la politique du chapitre 8
> sur cette installation, et chacun a une conséquence mesurable sur
> `verifier_admin.php` :
>
> - `SECURITE_2FA_ROLES` est vidé dans `app/config/config.php` (décision du
>   16/10) : l’enrôlement n’est imposé à personne. Le bloc d’enrôlement échoue
>   alors — un compte sans second facteur va droit au back-office — soit **17
>   échecs**. La suite les rapporte sans s’interrompre et affiche une note
>   expliquant l’origine du réglage. Remettre `['regie', 'agent']` rétablit le
>   parcours testé.
> - `SECURITE_2FA_SANS_CODE` (dans `.env`) dispense de saisir le code à la
>   connexion. Le bloc « écran de code » ne décrit alors plus le site : la suite
>   **l’écarte** au lieu de le faire échouer, et vérifie à la place que le mot
>   de passe seul ouvre bien la session. Aucune assertion n’est retirée — elles
>   se réactivent en repassant le réglage à `false`.
>
> Ce ne sont pas des régressions de l’application : ne pas affaiblir ces
> assertions pour faire passer la suite.

Les suites PHP nettoient les données qu'elles ont créées et rendent la base dans
son état d'origine. Utiliser de préférence l'interpréteur livré avec le serveur
web (par exemple `C:\wamp64\bin\php\php8.3.14\php.exe`), afin que `pdo_mysql` et
la version de PHP correspondent à celles réellement servies.

`verifier_animations.js` mérite une mention : il ne se contente pas de vérifier
que la page s'affiche. Il contrôle que le bloc des chiffres est **réellement
stylé** (fond translucide, flou d'arrière-plan, couleur d'étiquette foncée,
taille du nombre), que la révélation et les compteurs se déclenchent, que les
jauges de la page du parc sont proportionnées, puis il rejoue la page en
**mouvement réduit** et en **JavaScript désactivé** pour confirmer que rien ne
disparaît. Il produit aussi des captures dans le dossier de sortie.

`audit_sombre.js` mérite la même mention. Il parcourt les vingt pages du site en
thème sombre et mesure, pour chaque élément porteur de texte ou de pictogramme,
le contraste **réel** — fond translucide composé couche par couche sur ses
ancêtres — puis compare au seuil WCAG AA (4,5:1 pour le texte, 3:1 pour les
éléments non textuels). Il traite quatre pièges qui, sinon, produisent des faux
positifs : le texte en dégradé ; les icônes à `fill` explicite (le QR code est
violet sur blanc, les épingles de la carte ont leur propre palette) ; les fonds
en dégradé, pour lesquels `backgroundColor` vaut « transparent » et ne décrit
plus rien ; et les panneaux de la carte Leaflet, posés sur des tuiles — donc sur
des images — qui sont comptés à part et déclarés hors périmètre. État attendu :
**0 élément sous le seuil**, 4 éléments hors périmètre. Un faux positif coûte
plus cher qu'un silence : il fait « corriger » du code sain.

Contrôle syntaxique de l'ensemble du code :

```bash
find app cron tests -name "*.php" -exec php -l {} \;
```

Régénération des icônes de l'application dans la charte courante, et capture
d'écran du site (les deux nécessitent Playwright) :

```bash
node tests/generer_icones.js
node tests/capturer_site.js /chemin/vers/sortie
```

Toutes les suites sont **rejouables** : chacune retire les données qu'elle a
créées et rend les tables à leur état de démonstration. Vérifié sur deux
passages consécutifs.

---

## 8. Sécurité

| Mesure | Mise en œuvre |
|---|---|
| Mots de passe | Argon2id, jamais conservés en clair |
| Verrouillage | 5 tentatives infructueuses en 15 min → compte bloqué |
| CSRF | jeton par session, comparaison `hash_equals`, statut 419 |
| Injection SQL | requêtes préparées uniquement, émulation désactivée |
| XSS | échappement systématique via `e()` dans les vues |
| Limitation de débit | fenêtre glissante par point d'entrée |
| Données personnelles | empreinte HMAC tronquée, jamais l'IP en clair |
| Secrets | exclusivement dans `.env`, jamais dans le dépôt |
| En-têtes | `nosniff`, `SAMEORIGIN`, `strict-origin-when-cross-origin` |
| Dossiers sensibles | `app/`, `sql/`, `cron/`, `storage/` inaccessibles par le web |

Le back-office applique une matrice de rôles par rubrique : un agent terrain
n'accède ni aux annonceurs, ni aux notifications, ni aux paramètres ; un compte
annonceur ne consulte que ses propres campagnes.

---

## 9. Design system

| Usage | Couleur | Code |
|---|---|---|
| Primaire | Violet profond | `#4C1D95` |
| Primaire (foncé) | Violet nuit | `#2E1065` |
| Secondaire | Lilas clair | `#C4B5FD` |
| Secondaire (vif) | Violet électrique | `#7C3AED` |
| Accent | Jaune or | `#FBBF24` |
| Fond | Blanc | `#FFFFFF` |
| Texte | Noir charbon violacé | `#1A1033` |

Les neutres (gris) sont légèrement teintés de violet pour rester dans la
charte. Tous les jetons sont déclarés une seule fois, en tête de
`assets/css/app.css` ; aucune couleur n'est écrite en dur dans les vues.

Typographie : **Poppins** (titres), empilement système pour le corps de texte.
Le logo est un SVG en ligne (`app/views/partials/logo.php`) qui hérite de la
couleur du contexte, ce qui permet de le décliner en violet, blanc ou or sans
requête réseau supplémentaire.

### Effet verre liquide

Les surfaces principales (cartes, blocs, panneaux, en-tête collant, feuille
« Rester informé », écran de connexion) sont traitées en **verre** : fond
translucide, bord lumineux avec reflet d'arête, et flou d'arrière-plan
(`backdrop-filter`).

Trois points sont indispensables pour que l'effet existe réellement :

1. **Un fond « aurore »** — des halos de violet, lilas et or posés sur le
   `body`, fixes au défilement. Sans relief derrière, le flou n'a rien à
   flouter et le verre se réduit à un aplat blanc.
2. **Un repli** — `@supports not (backdrop-filter: …)` rétablit des surfaces
   opaques, sinon le verre deviendrait illisible sur les navigateurs qui ne
   gèrent pas la propriété.
3. **Un équilibre de lisibilité** — la feuille modale, qui porte du texte à
   lire, utilise un fond plus opaque (`--verre-fond-modal`, 93 %) que les
   cartes (58 %). Le flou y reste un relief, jamais une gêne.

Les halos du héros et de l'écran de connexion dérivent lentement ; le mouvement
est neutralisé par `prefers-reduced-motion`, et le verre est aplati à
l'impression.

Le vert du bouton WhatsApp a été assombri (`#1A7F4B` au lieu du `#25D366`
officiel) : le vert d'origine ne donne que 2:1 avec un texte blanc, très en
dessous du seuil d'accessibilité. `tests/verifier_contraste.php` verrouille ce
choix.

### Thème sombre

Le thème est piloté par un attribut `data-theme` posé sur `<html>` (voir
`app/views/partials/theme-init.php`) : `?theme=sombre|clair` dans l'URL, sinon
le choix mémorisé dans `localStorage`, sinon `prefers-color-scheme`. Un seul bloc
de jetons est surchargé ; tout le design system suit, parce qu'il consomme ces
jetons et rien d'autre.

Quatre principes, tirés des corrections successives :

1. **Aucune couleur claire figée sur une surface devenue sombre.** C'est la cause
   de tous les défauts constatés : un `background: rgba(255, 255, 255, .72)`
   écrit en dur laissait une pastille claire sous un libellé devenu clair.
2. **Le texte secondaire se juge sur la surface la plus claire, pas sur le fond
   noir.** Les surfaces de verre du back-office — en-tête, barre latérale — sont
   des dégradés qui s'éclairent jusqu'à `rgb(43, 28, 83)`. C'est là que
   `--gris-500` tombait à 4,2:1, et c'est cette contrainte qui a fixé sa valeur
   (`#9A91BA`), et non le fond noir où il frôlait déjà le seuil.
3. **Une couleur de marque ne se réemploie pas telle quelle.** `--violet`
   (`#4C1D95`) sur fond sombre ne donne que 1,7:1 : les liens passent par le
   jeton `--lien`, les accents de texte et d'icône par `--texte-accent`. Le logo,
   lui, reste or en thème sombre — c'est une décision de marque, pas un oubli.
4. **À spécificité égale, c'est la dernière feuille chargée qui gagne.**
   `leaflet.css` est injecté après `app.css` : ses règles sur
   `.leaflet-container` et `.leaflet-control-attribution` écrasaient les nôtres,
   laissant un fond gris clair sous la carte et sous sa légende. Les correctifs
   montent d'une classe plutôt que d'employer `!important`.

`node tests/audit_sombre.js` parcourt les vingt pages en thème sombre et doit
rapporter **0 élément sous le seuil** ; `tests/verifier_contraste.php` verrouille
les paires de jetons, y compris celles des surfaces en dégradé.

### Composant « chiffres du parc »

La bande de chiffres de la vitrine est un composant à part entière, et non un
bloc de styles en ligne :

```html
<section class="section section--serree section--profil">
    <div class="chiffres">
        <div class="chiffre verre verre--fort reveler">
            <span class="chiffre__icone">…</span>
            <span class="chiffre__valeur" data-compteur="8">8</span>
            <span class="chiffre__etiquette">Panneaux en parc</span>
        </div>
        …
    </div>
</section>
```

Deux précautions, toutes deux vérifiées par des tests :

- **Le fond de la carte est un verre à 78 % de blanc**, pas à 58 % comme les
  autres cartes. Composé sur le violet profond `#2E1065`, cela donne un fond
  équivalent à `#D8CDE8`, sur lequel le nombre en `#4C1D95` tient 6,9:1 et
  l'étiquette en `#3A3155` tient 7,5:1. À 58 %, le nombre tombe à 4,2:1 et
  échoue. `tests/verifier_contraste.php` contient un **témoin négatif** qui
  échoue volontairement : il prouve que le calcul de composition des
  transparences fonctionne réellement.
- **La valeur est écrite deux fois** : dans `data-compteur` pour l'animation, et
  dans le texte de l'élément comme repli. Sans JavaScript, les chiffres restent
  affichés.

Le bandeau des villes sous les chiffres est une piste dupliquée qui défile de
0 à −50 % : la seconde copie arrive exactement là où la première est partie,
donc la boucle est invisible sans aucun calcul en JavaScript.

---

## 9 bis. Animations

Cinq effets, tous facultatifs et sans dépendance externe :

| Effet | Déclencheur | Mise en œuvre |
|---|---|---|
| Révélation au défilement | `IntersectionObserver` | classe `.reveler` → `.reveler--visible`, cascade par `--retard` |
| Compteurs | entrée à l'écran | `data-compteur`, `requestAnimationFrame`, easeOutCubic |
| Inclinaison 3D | survol d'une carte | `.inclinable`, variables `--rx` / `--ry` posées en JS |
| Bandeau défilant | boucle CSS | `.defileur__piste`, animation `defilement` |
| Barre de progression | défilement | `.barre-progression`, mise à jour sous `requestAnimationFrame` |

**Règle de conception : aucune animation ne conditionne la lisibilité.**

1. Les états initiaux masqués ne s'appliquent que sous
   `html[data-anim="actif"]`. Ce drapeau est posé par un court script en ligne
   dans le `<head>`, donc **avant le premier rendu** — sinon le contenu
   apparaîtrait, disparaîtrait, puis serait révélé.
2. Un garde-fou le retire après 3 secondes si `app.js` ne s'est pas signalé
   (`data-anim-pret`). Réseau coupé, script bloqué par une extension, erreur de
   syntaxe : la page reste intégralement visible.
3. `prefers-reduced-motion: reduce` désactive tout. Les animations en boucle
   sont arrêtées avec `animation: none` et non simplement raccourcies, sinon
   `animation-duration: .01ms` les transformerait en scintillement ; le bandeau
   défilant redevient une liste centrée sur plusieurs lignes.
4. À l'impression, la barre de progression et le bandeau défilant disparaissent,
   et la bande violette est éclaircie pour ne pas vider les cartouches.

`tests/verifier_animations.js` contrôle ces quatre points dans un vrai
navigateur, y compris le cas **JavaScript désactivé**.

> **Service worker** : `app.css` et `app.js` sont servis en « cache d'abord ».
> La constante `VERSION` de `service-worker.js` **doit** être incrémentée à
> chaque modification de l'un de ces deux fichiers, sans quoi les visiteurs
> déjà venus conserveraient l'ancienne version.

---

## 10. Accessibilité

`php tests/verifier_contraste.php` recalcule les ratios WCAG 2.1 des
principales paires texte/fond et échoue si l'une passe sous son seuil
(4,5:1 pour le texte courant, 3:1 pour les éléments d'interface et les
indicateurs de focus). Les 29 paires de la charte sont conformes, y compris
celles des surfaces de verre — dont les transparences sont composées sur le
fond réel avant calcul.

L'indicateur de focus emploie le lilas vif `#7C3AED` et non le lilas clair :
sur fond blanc, ce dernier ne donne que 1,7:1, insuffisant pour repérer le
focus au clavier.

---

## 11. Performance

La page d'un annonceur est le point critique : elle s'ouvre au scan d'un QR
code, souvent en 4G depuis un téléphone d'entrée de gamme.

- Poids de la page d'un annonceur : **≈ 27 Ko** de HTML, très en deçà du
  plafond de 300 Ko fixé au cahier des charges.
- Feuille de style et script uniques, mis en cache un an.
- Polices distantes chargées en `media="print"` puis basculées, pour ne pas
  bloquer le rendu.
- Service worker : cache d'abord pour les ressources statiques, réseau d'abord
  pour les navigations, page hors ligne en repli.
- Mesure d'audience par lots : les interactions sont accumulées puis envoyées
  en un seul appel, au plus tard à la fermeture de l'onglet.

---

## 11 bis. Langue locale (EF-D08)

Un mécanisme d'internationalisation léger est intégré :

- **Catalogues** dans `app/lang/` : un fichier par langue (`fr.php`, `wo.php`…).
  Le français est le catalogue de référence ; chaque autre langue n'a qu'à
  traduire les valeurs.
- **Fonction `__(clé, défaut)`** dans `helpers.php` : charge le catalogue de la
  langue active et renvoie la traduction, ou le défaut si la clé n'existe pas.
- **Détection** : paramètre GET `?lang=`, session, ou `Accept-Language` du
  navigateur. Le choix est mémorisé en session.
- **Sélecteur** : menu déroulant dans le pied de page du site public.

Le wolof (`wo`) est fourni en exemple pour les termes les plus visibles.
L'ajout d'une langue se résume à copier `app/lang/fr.php`, traduire les
valeurs, et ajouter l'entrée dans `langues_disponibles()`.

---

## 12. Ordre d'affichage imposé

La page d'un annonceur respecte strictement la séquence du chapitre 5.2 :

1. bandeau de l'offre
2. identité de l'annonceur
3. boutons d'appel et WhatsApp
4. informations complémentaires
5. galerie
6. adresse et itinéraire
7. horaires d'ouverture
8. réseaux sociaux
9. « Rester informé »
10. mentions légales

Le bouton « Rester informé » apparaît après huit secondes ou 40 % de
défilement, au choix du visiteur, et une seule fois par session.
