# Socle factuel — Souriane

Source : lecture directe du code du projet `C:/wamp64/www/sourianemedia` (commit `734d9db`).
Aucune recherche web : la vérité de ce document est le code lui-même.

## Nature du projet
- Plateforme d'affichage extérieur connecté, région de Bignona / Ziguinchor (Casamance, Sénégal).
- PHP 8.3 natif, sans framework ni Composer ; MySQL 9.1 (InnoDB, utf8mb4) ; PDO en requêtes préparées uniquement.
- PWA : manifest, service worker, notifications Web Push.

## Le principe central
- Chaque panneau porte un QR code encodant une URL courte (`/c/{code}`), qui redirige vers la page publique d'un annonceur.
- Le code court est résolu, une visite est enregistrée, puis redirection (PublicController::resoudreCode).

## Parcours visiteur
- Scan / NFC → page annonceur enrichie (offre, présentation, infos, galerie, vidéo).
- Actions mesurées : appel, WhatsApp, itinéraire, réseaux sociaux, « rester informé », installation, abonnement.
- Abonnement aux notifications limité à 4 par mois et par annonceur ; repli WhatsApp.
- Compteurs mesurés exposés : scans bruts, visiteurs uniques, pages affichées, clics d'appel, clics WhatsApp, itinéraires, installations, abonnements.

## Parcours annonceur / prospect
- Formulaire public `/annonceurs/demande` : enseigne, contact, e-mail, téléphone, ville, formats, période, budget, message.
- Stockage dans la table `demandes` (statuts : nouveau, contacté, converti, archivé).
- Back-office `/admin/demandes` : liste filtrable, détail, changement de statut, pastille de menu.
- La création de l'annonceur et le devis restent des actions manuelles de la régie.

## Parcours régie (back-office)
- Rôles : `regie` (accès complet), `agent` (parc, poses, QR codes), `annonceur` (ses seules campagnes et statistiques).
- Rubriques : tableau de bord, statistiques, annonceurs, demandes, campagnes, panneaux, pages publiques, QR codes, notifications, preuves de pose, journal, utilisateurs, paramètres, sécurité du compte.
- Réservation de panneaux avec contrôle de chevauchement et verrou de ligne (transaction).
- Preuves de pose photographiques horodatées et géolocalisées ; contrôles de conformité à 15 et 30 jours.
- Rapport de fin de campagne distinguant explicitement mesuré / estimé / déclaré.

## Sécurité et données
- Argon2id pour les mots de passe ; second facteur (TOTP) disponible.
- CSRF sur les formulaires porteurs d'action ; jeton par session.
- Aucune IP en clair dans les mesures d'audience : empreinte HMAC-SHA256 tronquée à 64 caractères.
- Journal d'opérations administratives ; journal technique applicatif.

## Limites constatées (à assumer dans le document)
- Le trafic des panneaux est une estimation déclarée, distincte des scans réellement mesurés.
- La conversion d'une demande en annonceur n'est pas automatisée (pas de lien demande → annonceur).
- Le second facteur est désactivé par réglage local (`SECURITE_2FA_ROLES` vide) ; à réactiver en production.

## Chiffres de démonstration
- Parc de démonstration : 8 panneaux, plusieurs villes, 4 annonceurs, 4 campagnes, 3 comptes.
