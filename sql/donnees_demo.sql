-- ============================================================================
--  SOURIANE — Jeu de données de démonstration
--  À importer APRÈS sql/souriane.sql
--
--    mysql -u root -p souriane < sql/donnees_demo.sql
--
--  Comptes créés :
--    admin@souriane.sn   / Souriane2026!   (rôle : regie)
--    agent@souriane.sn   / Souriane2026!   (rôle : agent)
-- ============================================================================

USE `souriane`;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `envois`;
TRUNCATE TABLE `notifications`;
TRUNCATE TABLE `abonnes`;
TRUNCATE TABLE `evenements`;
TRUNCATE TABLE `visites`;
TRUNCATE TABLE `poses`;
TRUNCATE TABLE `codes_courts`;
TRUNCATE TABLE `campagne_panneaux`;
TRUNCATE TABLE `sections_page`;
TRUNCATE TABLE `pages`;
TRUNCATE TABLE `campagnes`;
TRUNCATE TABLE `panneaux`;
TRUNCATE TABLE `annonceurs`;
TRUNCATE TABLE `formules`;
TRUNCATE TABLE `utilisateurs`;
TRUNCATE TABLE `journal_operations`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Paramètres applicatifs
--
-- Le script peut être rejoué : les réglages courants sont rafraîchis, tandis
-- que les clés de signature VAPID déjà présentes ne sont jamais écrasées
-- (elles sont produites par cron/generer_cles.php et propres à l'installation).
-- ---------------------------------------------------------------------------
INSERT INTO `parametres` (`cle`, `valeur`, `libelle`) VALUES
('site_nom',            'Souriane',                              'Nom de la plateforme'),
('site_slogan',         'Votre panneau vous parle',              'Slogan'),
('plafond_notifications', '4',                                   'Plafond de notifications par mois et par annonceur'),
('contact_email',       'contact@souriane.sn',                   'Adresse de contact de la régie'),
('whatsapp_regie',      '+221770000000',                         'WhatsApp de secours de la régie')
ON DUPLICATE KEY UPDATE `valeur` = VALUES(`valeur`), `libelle` = VALUES(`libelle`);

INSERT IGNORE INTO `parametres` (`cle`, `valeur`, `libelle`) VALUES
('cle_publique_vapid',  '',  'Clé publique VAPID (générer avec : php cron/generer_cles.php)'),
('cle_privee_vapid',    '',  'Clé privée VAPID — ne pas versionner');

-- ---------------------------------------------------------------------------
-- Formules tarifaires (EF-B03)
-- ---------------------------------------------------------------------------
INSERT INTO `formules` (`id`, `nom`, `code`, `duree_min_jours`, `prix_base`, `duree_reference_jours`, `remise_panneaux`, `actif`) VALUES
(1, 'Affichage 30 jours',  'A30',   30, 75000.00,  30, NULL, 1),
(2, 'Affichage 90 jours',  'A90',   90, 195000.00, 90, '{"3":0.05,"5":0.10,"10":0.18}', 1),
(3, 'Pack réseau 5 panneaux', 'PACK5', 30, 320000.00, 30, '{"5":0.15,"10":0.25}', 1);

-- ---------------------------------------------------------------------------
-- Annonceurs
-- ---------------------------------------------------------------------------
INSERT INTO `annonceurs`
(`id`,`nom`,`adresse_courte`,`description`,`logo`,`telephone`,`whatsapp`,`email`,`adresse`,`ville`,`latitude`,`longitude`,`reseaux`,`horaires`,`statut`) VALUES
(1, 'Quincaillerie Bignona', 'quincaillerie-bignona',
 'Outillage, matériaux de construction et fournitures agricoles au cœur de Bignona. Livraison sur toute la Casamance.',
 NULL, '+221771234567', '+221771234567', 'contact@quincaillerie-bignona.sn',
 'Avenue de la Casamance, face au marché central', 'Bignona', 12.8083000, -16.2333000,
 '{"facebook":"https://facebook.com/quincailleriebignona","instagram":"https://instagram.com/quincailleriebignona","tiktok":"","youtube":"","site":""}',
 '{"lun":"08:00-19:00","mar":"08:00-19:00","mer":"08:00-19:00","jeu":"08:00-19:00","ven":"08:00-19:00","sam":"08:00-14:00","dim":"fermé"}',
 'actif'),
(2, 'Restaurant Le Baobab', 'restaurant-le-baobab',
 'Cuisine sénégalaise authentique : thiéboudiène, yassa, mafé. Salle climatisée et terrasse ombragée.',
 NULL, '+221776543210', '+221776543210', 'lebaobab@example.sn',
 'Rue Émile Badiane, quartier Escale', 'Ziguinchor', 12.5665000, -16.2733000,
 '{"facebook":"https://facebook.com/restaurantlebaobab","instagram":"","tiktok":"","youtube":"","site":""}',
 '{"lun":"11:00-23:00","mar":"11:00-23:00","mer":"11:00-23:00","jeu":"11:00-23:00","ven":"11:00-23:30","sam":"11:00-23:30","dim":"12:00-22:00"}',
 'actif'),
(3, 'Pharmacie de la Casamance', 'pharmacie-casamance',
 'Officine ouverte 7j/7. Garde de nuit, matériel médical, conseil pharmaceutique.',
 NULL, '+221778888999', '+221778888999', 'pharmacie.casamance@example.sn',
 'Boulevard de la Paix, à côté du rond-point', 'Bignona', 12.8101000, -16.2299000,
 '{"facebook":"","instagram":"","tiktok":"","youtube":"","site":""}',
 '{"lun":"08:00-21:00","mar":"08:00-21:00","mer":"08:00-21:00","jeu":"08:00-21:00","ven":"08:00-21:00","sam":"08:00-21:00","dim":"09:00-13:00"}',
 'actif'),
(4, 'Teranga Tissus', 'teranga-tissus',
 'Wax, bazin riche, broderies et confection sur mesure. Nouveaux arrivages chaque semaine.',
 NULL, '+221770111222', '+221770111222', 'teranga.tissus@example.sn',
 'Marché de Bignona, allée 3, boutique 12', 'Bignona', 12.8072000, -16.2351000,
 '{"facebook":"https://facebook.com/terangatissus","instagram":"https://instagram.com/terangatissus","tiktok":"https://tiktok.com/@terangatissus","youtube":"","site":""}',
 '{"lun":"09:00-19:00","mar":"09:00-19:00","mer":"09:00-19:00","jeu":"09:00-19:00","ven":"09:00-19:00","sam":"09:00-19:00","dim":"fermé"}',
 'actif');

-- ---------------------------------------------------------------------------
-- Utilisateurs
-- ---------------------------------------------------------------------------
INSERT INTO `utilisateurs` (`id`,`nom`,`email`,`mot_de_passe`,`role`,`annonceur_id`,`telephone`,`actif`) VALUES
(1, 'Administrateur Souriane', 'admin@souriane.sn',
 '$argon2id$v=19$m=65536,t=4,p=1$M1J0VGZ2TlNSb2xvaHJ5MQ$jXwe33EBaiKywYLLvgf4CjZFXX5nz7vRibuTxeXnitQ',
 'regie', NULL, '+221770000000', 1),
(2, 'Agent Terrain Bignona', 'agent@souriane.sn',
 '$argon2id$v=19$m=65536,t=4,p=1$M1J0VGZ2TlNSb2xvaHJ5MQ$jXwe33EBaiKywYLLvgf4CjZFXX5nz7vRibuTxeXnitQ',
 'agent', NULL, '+221771111111', 1),
(3, 'Quincaillerie Bignona', 'annonceur@souriane.sn',
 '$argon2id$v=19$m=65536,t=4,p=1$M1J0VGZ2TlNSb2xvaHJ5MQ$jXwe33EBaiKywYLLvgf4CjZFXX5nz7vRibuTxeXnitQ',
 'annonceur', 1, '+221771234567', 1);

-- ---------------------------------------------------------------------------
-- Parc de panneaux
-- ---------------------------------------------------------------------------
INSERT INTO `panneaux`
(`id`,`reference`,`libelle`,`adresse`,`ville`,`latitude`,`longitude`,`format`,`faces`,`trafic_estime`,`tarif`,`statut`,`photo`,`photo_date`) VALUES
(1, 'PNB-001', 'Entrée nord de Bignona', 'RN4, sortie vers Ziguinchor', 'Bignona', 12.8150000, -16.2280000, '8x3', 1, 4200, 85000.00, 'occupe', NULL, '2026-08-12'),
(2, 'PNB-002', 'Marché central', 'Face au marché central', 'Bignona', 12.8080000, -16.2340000, '4x3', 2, 6800, 75000.00, 'occupe', NULL, '2026-08-12'),
(3, 'PNB-003', 'Rond-point de la Paix', 'Rond-point, boulevard de la Paix', 'Bignona', 12.8100000, -16.2305000, '6x3', 1, 5100, 70000.00, 'reserve', NULL, '2026-09-02'),
(4, 'PNB-004', 'Gare routière', 'Parvis de la gare routière', 'Bignona', 12.8055000, -16.2380000, '4x3', 1, 3300, 55000.00, 'disponible', NULL, '2026-09-02'),
(5, 'PNZ-001', 'Avenue Émile Badiane', 'Avenue Émile Badiane', 'Ziguinchor', 12.5660000, -16.2740000, '8x3', 2, 7400, 95000.00, 'occupe', NULL, '2026-07-20'),
(6, 'PNZ-002', 'Lycée Djignabo', 'Devant le lycée Djignabo', 'Ziguinchor', 12.5720000, -16.2680000, '4x3', 1, 3900, 60000.00, 'disponible', NULL, '2026-07-20'),
(7, 'PND-001', 'Autoroute, sortie Bignona', 'Voie de dégagement nord', 'Dakar', 14.7300000, -17.4100000, '12x4', 1, 21000, 180000.00, 'disponible', NULL, '2026-06-30'),
(8, 'PND-002', 'Abribus Plateau', 'Place de l’Indépendance', 'Dakar', 14.6690000, -17.4370000, 'abribus', 2, 12000, 120000.00, 'maintenance', NULL, '2026-06-30');

-- ---------------------------------------------------------------------------
-- Campagnes
-- ---------------------------------------------------------------------------
INSERT INTO `campagnes`
(`id`,`annonceur_id`,`formule_id`,`nom`,`date_debut`,`date_fin`,`prix`,`statut`,`moyen_paiement`,`reference_paiement`,`note`) VALUES
(1, 1, 2, 'Rentrée des chantiers — outillage', '2026-09-01', '2026-11-30', 195000.00, 'en_cours', 'wave', 'WAVE-2026-0912-A77', 'Campagne d’affichage longue durée sur 3 supports.'),
(2, 2, 1, 'Menu du jour — Le Baobab', '2026-10-01', '2026-10-31', 75000.00, 'en_cours', 'orange_money', 'OM-2026-1001-B21', NULL),
(3, 3, 1, 'Vaccination antigrippale', '2026-10-10', '2026-11-10', 75000.00, 'reservee', 'especes', NULL, 'Visuel fourni par l’officine.'),
(4, 4, 3, 'Arrivage wax — Teranga Tissus', '2026-11-01', '2026-12-31', 320000.00, 'brouillon', 'aucun', NULL, 'En attente de validation du visuel.');

-- ---------------------------------------------------------------------------
-- Association campagne ↔ panneaux
-- ---------------------------------------------------------------------------
INSERT INTO `campagne_panneaux` (`campagne_id`,`panneau_id`,`visuel`,`date_debut`,`date_fin`,`prix_ligne`) VALUES
(1, 1, NULL, '2026-09-01', '2026-11-30', 65000.00),
(1, 2, NULL, '2026-09-01', '2026-11-30', 65000.00),
(1, 5, NULL, '2026-09-01', '2026-11-30', 65000.00),
(2, 2, NULL, '2026-10-01', '2026-10-31', 75000.00),
(3, 3, NULL, '2026-10-10', '2026-11-10', 75000.00),
(4, 5, NULL, '2026-11-01', '2026-12-31', 64000.00),
(4, 1, NULL, '2026-11-01', '2026-12-31', 64000.00);

-- ---------------------------------------------------------------------------
-- Codes courts — un par couple campagne × panneau (EF-C01)
-- ---------------------------------------------------------------------------
INSERT INTO `codes_courts` (`id`,`code`,`campagne_id`,`panneau_id`,`destination`,`code_secours`,`actif`) VALUES
(1, 'b7k2', 1, 1, NULL, 'sr.n/b7k2', 1),
(2, 'm3x9', 1, 2, NULL, 'sr.n/m3x9', 1),
(3, 't5p8', 1, 5, NULL, 'sr.n/t5p8', 1),
(4, 'r2w4', 2, 2, NULL, 'sr.n/r2w4', 1),
(5, 'v8c1', 3, 3, NULL, 'sr.n/v8c1', 1),
(6, 'w4n6', 4, 5, NULL, 'sr.n/w4n6', 1),
(7, 'z9j3', 4, 1, NULL, 'sr.n/z9j3', 1);

-- ---------------------------------------------------------------------------
-- Pages publiques + sections
-- ---------------------------------------------------------------------------
INSERT INTO `pages` (`id`,`annonceur_id`,`adresse_courte`,`titre`,`bandeau_offre`,`statut`,`langue`) VALUES
(1, 1, 'quincaillerie-bignona', 'Quincaillerie Bignona — Outillage & matériaux',
 '−20 % sur l’outillage de chantier jusqu’au 30 novembre', 'publiee', 'fr'),
(2, 2, 'restaurant-le-baobab', 'Restaurant Le Baobab — Cuisine sénégalaise',
 'Thiéboudiène à 3 500 FCFA le midi, du lundi au vendredi', 'publiee', 'fr'),
(3, 3, 'pharmacie-casamance', 'Pharmacie de la Casamance — Ouverte 7j/7',
 'Vaccination antigrippale sans rendez-vous', 'publiee', 'fr'),
(4, 4, 'teranga-tissus', 'Teranga Tissus — Wax & bazin riche',
 'Nouveaux arrivages wax chaque mercredi', 'brouillon', 'fr');

INSERT INTO `sections_page` (`page_id`,`type`,`titre`,`contenu`,`ordre`,`actif`) VALUES
-- Quincaillerie Bignona
(1, 'offre', 'Offre en cours',
 '{"texte":"−20 % sur l’outillage de chantier : perceuses, disqueuses, bétonnières et petit outillage à main.","debut":"2026-09-01","fin":"2026-11-30","restrictions":"Dans la limite des stocks disponibles. Non cumulable avec d’autres remises."}', 1, 1),
(1, 'presentation', 'Qui sommes-nous',
 '{"texte":"Depuis 1998, la Quincaillerie Bignona équipe les artisans, les chantiers et les exploitations agricoles de la Casamance. Plus de 4 000 références en stock, du ciment au groupe électrogène."}', 2, 1),
(1, 'infos_complementaires', 'Informations complémentaires',
 '{"texte":"Devis gratuit pour tout projet de construction. Livraison possible à Ziguinchor, Oussouye et Thionck Essyl sous 48 h. Paiement par Wave, Orange Money, espèces ou virement.","conditions":"Facturation sur présentation d’un numéro de contribuable.","validite":"Du 1er septembre au 30 novembre 2026."}', 3, 1),
(1, 'galerie', 'Nos rayons',
 '{"images":[]}', 4, 1),
(1, 'horaires', 'Horaires d’ouverture', '{}', 5, 1),
(1, 'reseaux', 'Nous suivre', '{}', 6, 1),
-- Restaurant Le Baobab
(2, 'offre', 'Formule du midi',
 '{"texte":"Thiéboudiène, yassa poulet ou mafé — 3 500 FCFA, boisson locale offerte.","debut":"2026-10-01","fin":"2026-10-31","restrictions":"Du lundi au vendredi, de 12 h à 15 h. Sur place uniquement."}', 1, 1),
(2, 'presentation', 'Notre maison',
 '{"texte":"Le Baobab cuisine le poisson du jour, acheté chaque matin au port de Ziguinchor. Salle climatisée de 60 couverts et terrasse ombragée sous les fromagers."}', 2, 1),
(2, 'infos_complementaires', 'Informations complémentaires',
 '{"texte":"Réservation conseillée le week-end. Menus de groupe à partir de 10 personnes sur demande. Plats à emporter disponibles.","validite":"Offre valable jusqu’au 31 octobre 2026."}', 3, 1),
(2, 'galerie', 'Nos plats',
 '{"images":[]}', 4, 1),
(2, 'horaires', 'Horaires d’ouverture', '{}', 5, 1),
(2, 'reseaux', 'Nous suivre', '{}', 6, 1),
-- Pharmacie de la Casamance
(3, 'offre', 'Campagne de vaccination',
 '{"texte":"Vaccination antigrippale sans rendez-vous, tous les jours aux heures d’ouverture.","debut":"2026-10-10","fin":"2026-11-10","restrictions":"Sur présentation du carnet de vaccination. Tarif : 4 000 FCFA."}', 1, 1),
(3, 'presentation', 'Votre officine',
 '{"texte":"Pharmacie de garde de nuit une semaine sur trois. Matériel médical, orthopédie et conseil pharmaceutique personnalisé."}', 2, 1),
(3, 'infos_complementaires', 'Informations complémentaires',
 '{"texte":"Garde de nuit assurée selon le planning affiché en vitrine. Tiers payant accepté pour les mutuelles partenaires.","validite":"Campagne du 10 octobre au 10 novembre 2026."}', 3, 1),
(3, 'galerie', 'L’officine',
 '{"images":[]}', 4, 1),
(3, 'horaires', 'Horaires d’ouverture', '{}', 5, 1),
(3, 'reseaux', 'Nous suivre', '{}', 6, 1);

-- ---------------------------------------------------------------------------
-- Poses (preuves de pose)
-- ---------------------------------------------------------------------------
INSERT INTO `poses` (`panneau_id`,`campagne_id`,`photo`,`latitude`,`longitude`,`horodatage`,`agent_id`,`controle_15j`,`controle_30j`,`note`) VALUES
(1, 1, 'storage/uploads/pose-exemple.jpg', 12.8150000, -16.2280000, '2026-09-03 08:40:00', 2, 'conforme', 'non_fait', 'Pose conforme, QR testé à 5 m en plein soleil.'),
(2, 1, 'storage/uploads/pose-exemple.jpg', 12.8080000, -16.2340000, '2026-09-03 10:15:00', 2, 'conforme', 'non_fait', 'Zone de silence respectée.'),
(5, 1, 'storage/uploads/pose-exemple.jpg', 12.5660000, -16.2740000, '2026-09-04 09:05:00', 2, 'non_fait', 'non_fait', 'Pose effectuée, contrôle à 15 jours à programmer.');

-- ---------------------------------------------------------------------------
-- Abonnés aux notifications
-- ---------------------------------------------------------------------------
INSERT INTO `abonnes`
(`annonceur_id`,`endpoint`,`endpoint_hash`,`cle_p256dh`,`cle_auth`,`frequence`,`statut`,`consentement_le`,`dernier_envoi`) VALUES
(1, 'https://fcm.googleapis.com/fcm/send/demo-abonne-001', SHA2('demo-abonne-001', 256), 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'tBHItJI5svbpez7KI4CCXg', 'chaque_annonce', 'actif', '2026-09-10 18:22:00', '2026-10-01 09:00:00'),
(1, 'https://fcm.googleapis.com/fcm/send/demo-abonne-002', SHA2('demo-abonne-002', 256), 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'tBHItJI5svbpez7KI4CCXg', 'hebdomadaire', 'actif', '2026-09-18 12:05:00', NULL),
(2, 'https://fcm.googleapis.com/fcm/send/demo-abonne-003', SHA2('demo-abonne-003', 256), 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'tBHItJI5svbpez7KI4CCXg', 'chaque_annonce', 'desabonne', '2026-10-02 20:41:00', '2026-10-05 11:00:00');

-- ---------------------------------------------------------------------------
-- Notifications + envois
-- ---------------------------------------------------------------------------
INSERT INTO `notifications`
(`id`,`annonceur_id`,`titre`,`corps`,`adresse_ouverture`,`statut`,`programmee_le`,`envoyee_le`,`cree_par`) VALUES
(1, 1, 'Nouvel arrivage d’outillage', 'Perceuses, disqueuses et bétonnières viennent d’arriver. −20 % jusqu’au 30 novembre.', '/quincaillerie-bignona', 'envoyee', '2026-10-01 09:00:00', '2026-10-01 09:00:12', 1),
(2, 2, 'Menu de la semaine', 'Cette semaine : thiéboudiène le mardi, yassa poulet le jeudi. Réservez votre table.', '/restaurant-le-baobab', 'programmee', '2026-10-20 11:30:00', NULL, 1),
(3, 1, 'Rappel : dernière semaine de promotion', 'L’offre −20 % se termine le 30 novembre. Passez nous voir.', '/quincaillerie-bignona', 'brouillon', NULL, NULL, 1);

INSERT INTO `envois` (`notification_id`,`abonne_id`,`statut`,`date_envoi`,`date_clic`) VALUES
(1, 1, 'clique',    '2026-10-01 09:00:12', '2026-10-01 09:07:44'),
(1, 2, 'delivre',   '2026-10-01 09:00:12', NULL),
(1, 3, 'expire',    '2026-10-01 09:00:12', NULL);

-- ---------------------------------------------------------------------------
-- Sessions de visite et événements (pour alimenter le tableau de bord)
-- ---------------------------------------------------------------------------
INSERT INTO `visites` (`session_id`,`page_id`,`annonceur_id`,`code_court_id`,`debut`,`fin`,`appareil`,`source`) VALUES
('8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c01', 1, 1, 1, '2026-10-12 08:12:00', '2026-10-12 08:19:00', 'Android 14 · Chrome', 'qr'),
('8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c02', 1, 1, 2, '2026-10-12 09:41:00', '2026-10-12 09:44:00', 'iOS 18 · Safari', 'qr'),
('8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c03', 2, 2, 4, '2026-10-12 12:30:00', '2026-10-12 12:36:00', 'Android 13 · Chrome', 'qr'),
('8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c04', 1, 1, 1, '2026-10-13 17:02:00', '2026-10-13 17:05:00', 'Android 14 · Chrome', 'qr'),
('8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c05', 3, 3, 5, '2026-10-13 19:22:00', NULL, 'iOS 17 · Safari', 'qr');

INSERT INTO `evenements` (`type`,`code_court_id`,`page_id`,`annonceur_id`,`campagne_id`,`panneau_id`,`session_id`,`empreinte_reseau`,`date`) VALUES
('scan',            1, 1, 1, 1, 1, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c01', SHA2('demo-ip-1', 256), '2026-10-12 08:12:00'),
('affichage',       1, 1, 1, 1, 1, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c01', SHA2('demo-ip-1', 256), '2026-10-12 08:12:02'),
('clic_appel',      1, 1, 1, 1, 1, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c01', SHA2('demo-ip-1', 256), '2026-10-12 08:14:31'),
('clic_whatsapp',   1, 1, 1, 1, 1, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c01', SHA2('demo-ip-1', 256), '2026-10-12 08:16:10'),
('scan',            2, 1, 1, 1, 2, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c02', SHA2('demo-ip-2', 256), '2026-10-12 09:41:00'),
('affichage',       2, 1, 1, 1, 2, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c02', SHA2('demo-ip-2', 256), '2026-10-12 09:41:01'),
('clic_itineraire', 2, 1, 1, 1, 2, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c02', SHA2('demo-ip-2', 256), '2026-10-12 09:43:22'),
('clic_rester_informe', 2, 1, 1, 1, 2, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c02', SHA2('demo-ip-2', 256), '2026-10-12 09:43:58'),
('abonnement',      2, 1, 1, 1, 2, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c02', SHA2('demo-ip-2', 256), '2026-10-12 09:44:20'),
('scan',            4, 2, 2, 2, 2, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c03', SHA2('demo-ip-3', 256), '2026-10-12 12:30:00'),
('affichage',       4, 2, 2, 2, 2, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c03', SHA2('demo-ip-3', 256), '2026-10-12 12:30:01'),
('clic_whatsapp',   4, 2, 2, 2, 2, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c03', SHA2('demo-ip-3', 256), '2026-10-12 12:34:12'),
('scan',            1, 1, 1, 1, 1, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c04', SHA2('demo-ip-1', 256), '2026-10-13 17:02:00'),
('affichage',       1, 1, 1, 1, 1, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c04', SHA2('demo-ip-1', 256), '2026-10-13 17:02:01'),
('installation',    1, 1, 1, 1, 1, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c04', SHA2('demo-ip-1', 256), '2026-10-13 17:03:40'),
('scan',            5, 3, 3, 3, 3, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c05', SHA2('demo-ip-4', 256), '2026-10-13 19:22:00'),
('affichage',       5, 3, 3, 3, 3, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c05', SHA2('demo-ip-4', 256), '2026-10-13 19:22:02'),
('clic_appel',      5, 3, 3, 3, 3, '8f2c1a90-6d3e-4b7a-9c11-2f5e7a1b0c05', SHA2('demo-ip-4', 256), '2026-10-13 19:24:55');

-- ---------------------------------------------------------------------------
-- Journal des opérations
-- ---------------------------------------------------------------------------
INSERT INTO `journal_operations` (`utilisateur_id`,`action`,`entite`,`entite_id`,`detail`,`adresse_ip`,`date`) VALUES
(1, 'connexion',        'utilisateurs', 1, 'Connexion réussie', '127.0.0.1', '2026-10-01 08:55:00'),
(1, 'creation',         'campagnes',    1, 'Création de la campagne « Rentrée des chantiers — outillage »', '127.0.0.1', '2026-09-12 10:20:00'),
(1, 'generation_codes', 'codes_courts', NULL, 'Génération de 3 codes courts pour la campagne #1', '127.0.0.1', '2026-09-12 10:22:00'),
(2, 'pose',             'poses',        1, 'Preuve de pose enregistrée pour le panneau PNB-001', '127.0.0.1', '2026-09-03 08:40:00'),
(1, 'envoi_notification','notifications', 1, 'Notification « Nouvel arrivage d’outillage » envoyée à 2 abonnés', '127.0.0.1', '2026-10-01 09:00:12');
