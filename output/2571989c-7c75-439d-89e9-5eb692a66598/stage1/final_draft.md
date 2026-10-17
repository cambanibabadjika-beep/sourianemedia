# Souriane — Comment fonctionne la plateforme, et le problème qu'elle résout

## 1. En bref

Souriane est une plateforme d'affichage extérieur connecté, conçue pour la région de Bignona et de Ziguinchor, en Casamance. Elle repose sur une idée simple : **chaque panneau d'affichage porte un QR code qui ouvre la page d'un annonceur local**.

Là où un panneau classique se contente d'être vu, un panneau Souriane devient un point de contact mesurable. Le passant qui le souhaite scanne le code, arrive sur une page enrichie, peut appeler, écrire sur WhatsApp, demander son itinéraire, s'abonner aux prochaines offres. Chacune de ces actions est comptée. L'annonceur, à la fin de sa campagne, dispose d'un bilan chiffré au lieu d'une impression.

En une phrase : **Souriane relie un support physique, qui ne laisse aucune trace, à une mesure numérique, qui laisse des traces exploitables.**

## 2. Le problème : un panneau vu par beaucoup, mais qui ne dit rien

L'affichage extérieur traditionnel souffre d'un défaut structurel. Un panneau de bord de route est vu par des milliers de personnes chaque jour, mais il ne renvoie **aucune information** à celui qui l'a payé.

Concrètement, l'annonceur se heurte à trois murs :

- **Il ne sait pas combien de personnes ont réagi.** Le panneau est un média à sens unique. On peut estimer le trafic qui passe devant, jamais le nombre de personnes qui ont effectivement prêté attention, ni combien ont agi.
- **Le lien entre le panneau et l'acte d'achat est rompu.** Quelqu'un voit une promotion, veut appeler le commerce, puis oublie le numéro. Rien ne relie l'envie née devant le panneau à la visite en boutique.
- **La mesure est déclarative.** Les chiffres d'audience d'un panneau sont des estimations de comptage terrain, présentées comme telles. Ce ne sont pas des mesures.

Pour une petite entreprise locale, ces murs sont encore plus hauts. Elle n'a ni les moyens d'une régie nationale, ni d'outil pour suivre ce que produit son affichage. Elle paie pour un support et repart avec une intuition.

C'est ce vide que Souriane comble : **rendre l'affichage extérieur lisible, mesurable et exploitable, y compris pour un commerce de quartier.**

## 3. La réponse de Souriane : le panneau qui se mesure

Le cœur de la plateforme tient en une mécanique : **le QR code, l'URL courte, la page de l'annonceur.**

Plutôt que d'imprimer l'adresse complète d'un site sur le panneau — longue, fragile, impossible à retenir — la plateforme encode une **URL courte** du type `sr.n/xxxx`. Ce code ne dépend pas de l'annonceur : il pointe vers une redirection interne. Quand le visiteur scanne, la plateforme :

1. reconnaît le code court ;
2. enregistre une visite et un scan ;
3. redirige vers la **page publique** de l'annonceur associé.

Ce détour par une URL courte n'est pas un détail. Il permet de **changer la destination d'un code sans réimprimer le panneau**, de mesurer chaque scan, et de rattacher la visite à la campagne et au panneau concernés.

Ce que cela change pour l'annonceur :

- chaque scan devient un événement compté ;
- chaque action sur la page devient une intention lisible (appel, message, itinéraire, abonnement) ;
- le panneau cesse d'être un coût opaque pour devenir un support dont on suit les effets.

## 4. Comment ça marche, côté visiteur

### 4.1 Le scan

Le passant voit le panneau, lit l'offre, puis scanne le QR code avec son téléphone. Il est redirigé vers la page de l'annonceur. Il peut aussi arriver par un lien direct ou par une puce NFC, quand le support en est équipé : la plateforme distingue ces sources pour ne pas mélanger les mesures.

### 4.2 La page annonceur

La page est pensée pour un téléphone, en connexion mobile parfois lente. Elle présente l'annonceur et son offre dans un ordre de lecture imposé, puis propose des actions immédiates :

- **Appeler** le commerce ;
- **Écrire sur WhatsApp** avec un message pré-rempli ;
- **Demander l'itinéraire** vers la boutique ;
- **Suivre** l'annonceur sur ses réseaux ;
- **Rester informé** des prochaines offres.

Chaque action est un bouton clair, et chaque appui est compté comme une interaction. La page ne demande aucun nom, aucun numéro, aucune donnée personnelle pour être consultée.

### 4.3 Après la visite : installation et notifications

Un visiteur intéressé peut **installer la page sur son écran d'accueil**, comme une petite application, et **s'abonner aux notifications** de l'annonceur. Il reçoit alors les offres suivantes directement sur son téléphone.

Deux garde-fous encadrent ce canal :

- **Quatre notifications par mois et par annonceur, au maximum.** Le canal reste utile, il ne devient pas du harcèlement.
- **Un canal WhatsApp de repli** pour les appareils qui ne prennent pas en charge les notifications.
- Sur iPhone, l'abonnement est possible après ajout de la page à l'écran d'accueil, conformément aux contraintes du système.

L'abonnement est toujours volontaire, et la désinscription se fait en un clic, sans justification.

## 5. Comment ça marche, côté annonceur

### 5.1 Formuler une demande

Un prospect qui veut afficher commence par **décrire son projet** depuis le site public. Un formulaire dédié lui demande :

- le nom de son enseigne ou de son entreprise ;
- la personne à contacter, son e-mail et, en option, son téléphone ;
- la ville visée ;
- les formats de panneaux souhaités ;
- la période envisagée ;
- l'enveloppe budgétaire, en option ;
- un message libre pour préciser son besoin.

La demande est enregistrée et arrive directement dans le back-office de la régie, classée « nouveau ». La régie la traite, la marque comme contactée, puis convertie ou archivée. Le prospect est recontacté pour la suite.

### 5.2 La campagne et sa préparation

La régie crée ensuite le compte annonceur, sa **page publique** — celle que verront les visiteurs — et la **campagne** : période, formule, panneaux retenus. Le prix se calcule à partir de la formule choisie, de la durée d'affichage et du nombre de panneaux, avec les remises par palier appliquées automatiquement.

Point important : la disponibilité d'un panneau se lit **mois par mois, avant toute réservation**. La plateforme empêche la double réservation : deux campagnes ne peuvent pas occuper le même panneau sur la même période.

### 5.3 Pendant la campagne

Chaque pose fait l'objet d'une **preuve photographique horodatée et géolocalisée**. Un contrôle de conformité est prévu à quinze jours, puis à trente jours. L'annonceur a la garantie que son affichage a bien été posé, au bon endroit, au bon moment.

En parallèle, les interactions sont comptées en continu : scans, visiteurs uniques, appels, messages WhatsApp, demandes d'itinéraire, installations et abonnements.

### 5.4 Le bilan de fin de campagne

À la fin, la plateforme produit un **rapport de fin de campagne** qui distingue rigoureusement **trois natures de données** :

| Nature | Signification | Exemple |
|--------|---------------|---------|
| **Mesuré** | Compté directement par la plateforme | Scans, visiteurs uniques, clics d'appel |
| **Estimé** | Calculé à partir du trafic déclaré des panneaux | Portée estimée sur la durée |
| **Déclaré** | Fourni par la régie | Trafic journalier du panneau |

Cette séparation est une règle de la plateforme : **aucune estimation n'est jamais présentée comme une mesure.** L'annonceur sait exactement ce qui a été constaté et ce qui a été extrapolé.

## 6. Comment ça marche, côté régie

### 6.1 Les trois rôles

La plateforme sépare clairement les responsabilités :

| Rôle | Périmètre |
|------|-----------|
| **Régie** | Accès complet : commercial, diffusion, exploitation, système |
| **Agent terrain** | Parc, poses, QR codes, lecture des campagnes |
| **Annonceur** | Ses propres campagnes et leurs statistiques, uniquement |

Un annonceur ne voit jamais les données d'un autre annonceur : le rôle ouvre une rubrique, il n'ouvre pas les données des autres.

### 6.2 Les outils du back-office

Le back-office regroupe tout le pilotage :

- **Tableau de bord et statistiques** — l'activité en un coup d'œil ;
- **Annonceurs et demandes** — les fiches clients et les demandes reçues du site public ;
- **Campagnes et panneaux** — la réservation du parc et le calcul des prix ;
- **Pages publiques** — le contenu que verront les visiteurs ;
- **QR codes** — la génération et l'export des codes à imprimer ;
- **Notifications** — la rédaction et l'envoi des messages aux abonnés ;
- **Preuves de pose** — les photos horodatées et géolocalisées ;
- **Journal** — l'historique des opérations ;
- **Utilisateurs, paramètres et sécurité du compte**.

### 6.3 Le parc de panneaux

Le parc est consultable en ligne : référence, emplacement, ville, format, nombre de faces, trafic estimé et statut. La page du parc permet de filtrer par ville et de lire, d'un coup d'œil, ce qui est disponible, réservé, occupé ou en maintenance.

## 7. Ce que la plateforme ne fait pas (limites assumées)

Un document honnête dit aussi ce que l'outil ne prétend pas faire.

- **Le trafic d'un panneau est une estimation déclarée, pas une mesure.** Les chiffres de trafic servent à calculer une portée estimée. Ils sont tenus distincts des scans réellement mesurés. C'est une règle de conception, pas un oubli.
- **La conversion d'une demande en annonceur n'est pas encore automatisée.** Aujourd'hui, la régie traite la demande puis crée l'annonceur manuellement. Le lien entre les deux n'est pas encore automatique.
- **La plateforme ne remplace pas le panneau.** Elle ne mesure pas les personnes qui regardent le panneau sans scanner. Elle mesure celles qui font la démarche de scanner, et tout ce qui suit.

Ces limites sont connues et documentées : elles dessinent la feuille de route autant qu'elles décrivent l'existant.

## 8. Les bénéfices, concrètement

| Acteur | Ce qu'il gagne |
|--------|----------------|
| **Annonceur** | Un bilan chiffré au lieu d'une intuition ; la preuve de pose ; un canal d'abonnement vers ses clients |
| **Visiteur** | Une page utile et rapide, des actions en un geste, des offres suivies volontairement |
| **Régie** | Un parc géré et réservable en ligne, des demandes centralisées, un suivi des poses |
| **Petite entreprise locale** | Un accès à un affichage mesurable, sans outil technique à sa charge |

## 9. Fiche technique

| Élément | Valeur |
|---------|--------|
| Langage | PHP 8.3, sans framework ni dépendance Composer |
| Base de données | MySQL 8+ (testé sur 9.1), moteur InnoDB, encodage utf8mb4 |
| Accès aux données | PDO en requêtes préparées exclusivement |
| Côté client | JavaScript natif, aucune bibliothèque lourde |
| Application installable | PWA (manifest + service worker) |
| Mots de passe | Argon2id |
| Mesure d'audience | Empreinte non réversible de l'adresse réseau (HMAC-SHA256) ; jamais d'IP en clair |
| Protection des formulaires | Jeton CSRF par session |
| Page annonceur | Légère, optimisée pour le mobile |

En résumé, Souriane transforme un support muet en point de contact mesurable : le panneau ouvre une page, la page déclenche des actions, les actions nourrissent un bilan honnête — mesuré quand c'est mesuré, estimé quand c'est estimé.
