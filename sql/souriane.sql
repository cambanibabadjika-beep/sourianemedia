-- ============================================================================
--  SOURIANE — Plateforme de publicité extérieure connectée
--  Schéma de base de données
--  Moteur : MySQL / MariaDB  ·  InnoDB  ·  utf8mb4
--  Version : 1.0
--
--  Ce schéma couvre le modèle de données décrit au chapitre 6.3 du cahier des
--  charges, complété par les entités nécessaires aux exigences fonctionnelles :
--  comptes et rôles (EF-G01), formules tarifaires (EF-B03), journal des
--  opérations (EF-G05) et paramètres applicatifs (clés de notification).
--
--  Import :
--    mysql -u root -p < sql/souriane.sql
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `souriane`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE `souriane`;

DROP TABLE IF EXISTS `envois`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `abonnes`;
DROP TABLE IF EXISTS `evenements`;
DROP TABLE IF EXISTS `visites`;
DROP TABLE IF EXISTS `poses`;
DROP TABLE IF EXISTS `campagne_panneaux`;
DROP TABLE IF EXISTS `codes_courts`;
DROP TABLE IF EXISTS `sections_page`;
DROP TABLE IF EXISTS `pages`;
DROP TABLE IF EXISTS `campagnes`;
DROP TABLE IF EXISTS `panneaux`;
DROP TABLE IF EXISTS `annonceurs`;
DROP TABLE IF EXISTS `formules`;
DROP TABLE IF EXISTS `utilisateurs`;
DROP TABLE IF EXISTS `journal_operations`;
DROP TABLE IF EXISTS `parametres`;

-- ----------------------------------------------------------------------------
-- 1. utilisateurs — comptes de la régie, des agents terrain et des annonceurs
--    Rôles : regie (accès complet), agent (terrain), annonceur (ses stats)
-- ----------------------------------------------------------------------------
CREATE TABLE `utilisateurs` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nom`           VARCHAR(120)  NOT NULL,
    `email`         VARCHAR(180)  NOT NULL,
    `mot_de_passe`  VARCHAR(255)  NOT NULL COMMENT 'Empreinte Argon2id',
    `role`          ENUM('regie','agent','annonceur') NOT NULL DEFAULT 'agent',
    `annonceur_id`  INT UNSIGNED  NULL COMMENT 'Renseigné si role = annonceur',
    `telephone`     VARCHAR(30)   NULL,
    `actif`         TINYINT(1)    NOT NULL DEFAULT 1,
    `derniere_connexion` DATETIME NULL,
    `tentatives_echec` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `verrouille_jusqua` DATETIME  NULL,
    `cree_le`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `maj_le`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_utilisateurs_email` (`email`),
    KEY `idx_utilisateurs_role` (`role`),
    KEY `fk_utilisateurs_annonceur` (`annonceur_id`),
    CONSTRAINT `fk_utilisateurs_annonceur` FOREIGN KEY (`annonceur_id`)
        REFERENCES `annonceurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. annonceurs — compte annonceur et fiche publique
-- ----------------------------------------------------------------------------
CREATE TABLE `annonceurs` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nom`           VARCHAR(180)  NOT NULL,
    `adresse_courte` VARCHAR(80)  NOT NULL COMMENT 'Identifiant d’URL (slug)',
    `description`   TEXT          NULL,
    `logo`          VARCHAR(255)  NULL,
    `telephone`     VARCHAR(30)   NULL,
    `whatsapp`      VARCHAR(30)   NULL,
    `email`         VARCHAR(180)  NULL,
    `adresse`       VARCHAR(255)  NULL,
    `ville`         VARCHAR(120)  NULL,
    `latitude`      DECIMAL(10,7) NULL,
    `longitude`     DECIMAL(10,7) NULL,
    `reseaux`       JSON          NULL COMMENT '{facebook,instagram,tiktok,youtube,site}',
    `horaires`      JSON          NULL COMMENT '{"lun":"08:00-18:00", ...}',
    `statut`        ENUM('actif','inactif','archive') NOT NULL DEFAULT 'actif',
    `cree_le`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `maj_le`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_annonceurs_slug` (`adresse_courte`),
    KEY `idx_annonceurs_statut` (`statut`),
    KEY `idx_annonceurs_ville` (`ville`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. panneaux — parc de supports physiques
-- ----------------------------------------------------------------------------
CREATE TABLE `panneaux` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference`     VARCHAR(60)   NOT NULL COMMENT 'Référence interne unique',
    `libelle`       VARCHAR(180)  NOT NULL,
    `adresse`       VARCHAR(255)  NULL,
    `ville`         VARCHAR(120)  NULL,
    `latitude`      DECIMAL(10,7) NULL,
    `longitude`     DECIMAL(10,7) NULL,
    `format`        VARCHAR(50)   NULL COMMENT '4x3, 8x3, abribus, mobilier urbain…',
    `faces`         TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `trafic_estime` INT UNSIGNED  NULL COMMENT 'Passages estimés par jour',
    `tarif`         DECIMAL(12,2) NULL COMMENT 'Tarif de référence par période',
    `statut`        ENUM('disponible','reserve','occupe','maintenance','archive')
                    NOT NULL DEFAULT 'disponible',
    `photo`         VARCHAR(255)  NULL,
    `photo_date`    DATE          NULL COMMENT 'Date de prise de la photo (EF-A03)',
    `cree_le`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `maj_le`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_panneaux_reference` (`reference`),
    KEY `idx_panneaux_statut` (`statut`),
    KEY `idx_panneaux_ville` (`ville`),
    KEY `idx_panneaux_format` (`format`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. formules — grille tarifaire utilisée par le calcul automatique (EF-B03)
-- ----------------------------------------------------------------------------
CREATE TABLE `formules` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nom`           VARCHAR(120)  NOT NULL,
    `code`          VARCHAR(40)   NOT NULL,
    `duree_min_jours` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `prix_base`     DECIMAL(12,2) NOT NULL COMMENT 'Prix pour un panneau sur la durée de référence',
    `duree_reference_jours` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    `remise_panneaux` JSON        NULL COMMENT 'Palier de remise selon le nombre de panneaux',
    `actif`         TINYINT(1)    NOT NULL DEFAULT 1,
    `cree_le`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_formules_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. campagnes — campagne commerciale
-- ----------------------------------------------------------------------------
CREATE TABLE `campagnes` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `annonceur_id`  INT UNSIGNED  NOT NULL,
    `formule_id`    INT UNSIGNED  NULL,
    `nom`           VARCHAR(180)  NOT NULL,
    `date_debut`    DATE          NOT NULL,
    `date_fin`      DATE          NOT NULL,
    `prix`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `statut`        ENUM('brouillon','reservee','en_cours','terminee','annulee')
                    NOT NULL DEFAULT 'brouillon',
    `moyen_paiement` ENUM('aucun','wave','orange_money','especes','virement')
                    NOT NULL DEFAULT 'aucun',
    `reference_paiement` VARCHAR(120) NULL,
    `note`          TEXT          NULL,
    `cree_le`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `maj_le`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_campagnes_statut` (`statut`),
    KEY `idx_campagnes_periode` (`date_debut`,`date_fin`),
    KEY `fk_campagnes_annonceur` (`annonceur_id`),
    KEY `fk_campagnes_formule` (`formule_id`),
    CONSTRAINT `fk_campagnes_annonceur` FOREIGN KEY (`annonceur_id`)
        REFERENCES `annonceurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_campagnes_formule` FOREIGN KEY (`formule_id`)
        REFERENCES `formules` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_campagnes_dates` CHECK (`date_fin` >= `date_debut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. campagne_panneaux — association campagne ↔ panneau, avec période et visuel
--    La contrainte d’unicité et le verrouillage applicatif (SELECT … FOR UPDATE)
--    garantissent l’absence de double réservation (EF-B05).
-- ----------------------------------------------------------------------------
CREATE TABLE `campagne_panneaux` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campagne_id`   INT UNSIGNED  NOT NULL,
    `panneau_id`    INT UNSIGNED  NOT NULL,
    `visuel`        VARCHAR(255)  NULL,
    `date_debut`    DATE          NOT NULL,
    `date_fin`      DATE          NOT NULL,
    `prix_ligne`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `cree_le`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_campagne_panneau` (`campagne_id`,`panneau_id`),
    KEY `idx_cp_periode` (`panneau_id`,`date_debut`,`date_fin`),
    KEY `fk_cp_campagne` (`campagne_id`),
    CONSTRAINT `fk_cp_campagne` FOREIGN KEY (`campagne_id`)
        REFERENCES `campagnes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cp_panneau` FOREIGN KEY (`panneau_id`)
        REFERENCES `panneaux` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_cp_dates` CHECK (`date_fin` >= `date_debut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. codes_courts — QR codes et codes de secours
--    Un code par couple campagne × panneau (EF-C01, granularité d’attribution).
--    La destination est modifiable sans réimprimer le visuel (EF-C03).
-- ----------------------------------------------------------------------------
CREATE TABLE `codes_courts` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`          VARCHAR(24)   NOT NULL COMMENT 'Partie variable de l’URL courte',
    `campagne_id`   INT UNSIGNED  NOT NULL,
    `panneau_id`    INT UNSIGNED  NOT NULL,
    `destination`   VARCHAR(255)  NULL COMMENT 'Surcharge de destination ; sinon page de l’annonceur',
    `code_secours`  VARCHAR(40)   NULL COMMENT 'Code lisible imprimé sous le QR (EF-C04)',
    `actif`         TINYINT(1)    NOT NULL DEFAULT 1,
    `cree_le`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `maj_le`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_codes_code` (`code`),
    UNIQUE KEY `uk_codes_couple` (`campagne_id`,`panneau_id`),
    KEY `fk_codes_panneau` (`panneau_id`),
    CONSTRAINT `fk_codes_campagne` FOREIGN KEY (`campagne_id`)
        REFERENCES `campagnes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_codes_panneau` FOREIGN KEY (`panneau_id`)
        REFERENCES `panneaux` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. pages — page publique de l’annonceur
-- ----------------------------------------------------------------------------
CREATE TABLE `pages` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `annonceur_id`  INT UNSIGNED  NOT NULL,
    `adresse_courte` VARCHAR(80)  NOT NULL,
    `titre`         VARCHAR(180)  NULL,
    `bandeau_offre` VARCHAR(255)  NULL COMMENT 'Mention reprise du panneau (ch. 5.2)',
    `statut`        ENUM('brouillon','publiee','depubliee') NOT NULL DEFAULT 'brouillon',
    `langue`        VARCHAR(5)    NOT NULL DEFAULT 'fr',
    `cree_le`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `maj_le`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_pages_slug` (`adresse_courte`),
    KEY `fk_pages_annonceur` (`annonceur_id`),
    CONSTRAINT `fk_pages_annonceur` FOREIGN KEY (`annonceur_id`)
        REFERENCES `annonceurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. sections_page — blocs de contenu activables d’une page (EF-D02)
-- ----------------------------------------------------------------------------
CREATE TABLE `sections_page` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page_id`   INT UNSIGNED  NOT NULL,
    `type`      ENUM('offre','presentation','infos_complementaires','galerie','video',
                     'adresse','horaires','reseaux','offres_a_venir','personnalise')
                NOT NULL,
    `titre`     VARCHAR(180)  NULL,
    `contenu`   JSON          NULL,
    `ordre`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `actif`     TINYINT(1)    NOT NULL DEFAULT 1,
    `cree_le`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `maj_le`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sections_page_ordre` (`page_id`,`ordre`),
    CONSTRAINT `fk_sections_page` FOREIGN KEY (`page_id`)
        REFERENCES `pages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10. visites — sessions de visite (décompte des visiteurs uniques)
-- ----------------------------------------------------------------------------
CREATE TABLE `visites` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id`    CHAR(36)      NOT NULL COMMENT 'Identifiant de session anonyme (UUID v4)',
    `page_id`       INT UNSIGNED  NULL,
    `annonceur_id`  INT UNSIGNED  NULL,
    `code_court_id` INT UNSIGNED  NULL,
    `debut`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fin`           DATETIME      NULL,
    `appareil`      VARCHAR(120)  NULL,
    `source`        VARCHAR(80)   NULL COMMENT 'qr, nfc, direct, lien',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_visites_session` (`session_id`),
    KEY `idx_visites_debut` (`debut`),
    KEY `fk_visites_page` (`page_id`),
    KEY `fk_visites_annonceur` (`annonceur_id`),
    KEY `fk_visites_code` (`code_court_id`),
    CONSTRAINT `fk_visites_page` FOREIGN KEY (`page_id`)
        REFERENCES `pages` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_visites_annonceur` FOREIGN KEY (`annonceur_id`)
        REFERENCES `annonceurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_visites_code` FOREIGN KEY (`code_court_id`)
        REFERENCES `codes_courts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 11. evenements — actions journalisées (ch. 5.7)
--     L’adresse réseau n’est jamais stockée en clair : seule une empreinte
--     non réversible (HMAC) est conservée (ch. 8, minimisation des données).
-- ----------------------------------------------------------------------------
CREATE TABLE `evenements` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`          ENUM('affichage','clic_appel','clic_whatsapp','clic_itineraire',
                         'clic_reseau','clic_rester_informe','installation',
                         'abonnement','desabonnement','scan','nfc') NOT NULL,
    `code_court_id` INT UNSIGNED  NULL,
    `page_id`       INT UNSIGNED  NULL,
    `annonceur_id`  INT UNSIGNED  NULL,
    `campagne_id`   INT UNSIGNED  NULL,
    `panneau_id`    INT UNSIGNED  NULL,
    `session_id`    CHAR(36)      NULL,
    `empreinte_reseau` CHAR(64)   NULL COMMENT 'HMAC-SHA256 tronqué, jamais l’IP en clair',
    `agent`         VARCHAR(255)  NULL,
    `referent`      VARCHAR(255)  NULL,
    `meta`          JSON          NULL,
    `date`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_evenements_type_date` (`type`,`date`),
    KEY `idx_evenements_campagne` (`campagne_id`),
    KEY `idx_evenements_panneau` (`panneau_id`),
    KEY `idx_evenements_session` (`session_id`),
    KEY `fk_evenements_code` (`code_court_id`),
    KEY `fk_evenements_page` (`page_id`),
    KEY `fk_evenements_annonceur` (`annonceur_id`),
    CONSTRAINT `fk_evenements_code` FOREIGN KEY (`code_court_id`)
        REFERENCES `codes_courts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_evenements_page` FOREIGN KEY (`page_id`)
        REFERENCES `pages` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_evenements_annonceur` FOREIGN KEY (`annonceur_id`)
        REFERENCES `annonceurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_evenements_campagne` FOREIGN KEY (`campagne_id`)
        REFERENCES `campagnes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_evenements_panneau` FOREIGN KEY (`panneau_id`)
        REFERENCES `panneaux` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 12. abonnes — abonnés aux notifications push
-- ----------------------------------------------------------------------------
CREATE TABLE `abonnes` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `annonceur_id`  INT UNSIGNED  NOT NULL,
    `endpoint`      VARCHAR(500)  NOT NULL,
    `endpoint_hash` CHAR(64)      NOT NULL COMMENT 'SHA-256 de l’endpoint (unicité)',
    `cle_p256dh`    VARCHAR(255)  NOT NULL,
    `cle_auth`      VARCHAR(255)  NOT NULL,
    `frequence`     ENUM('chaque_annonce','hebdomadaire') NOT NULL DEFAULT 'chaque_annonce',
    `statut`        ENUM('actif','inactif','desabonne') NOT NULL DEFAULT 'actif',
    `consentement_le` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `dernier_envoi` DATETIME      NULL,
    `cree_le`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `maj_le`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_abonnes_endpoint` (`endpoint_hash`),
    KEY `idx_abonnes_annonceur_statut` (`annonceur_id`,`statut`),
    CONSTRAINT `fk_abonnes_annonceur` FOREIGN KEY (`annonceur_id`)
        REFERENCES `annonceurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 13. notifications — messages rédigés et programmés
-- ----------------------------------------------------------------------------
CREATE TABLE `notifications` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `annonceur_id`  INT UNSIGNED  NOT NULL,
    `titre`         VARCHAR(120)  NOT NULL,
    `corps`         VARCHAR(400)  NOT NULL,
    `icone`         VARCHAR(255)  NULL,
    `adresse_ouverture` VARCHAR(255) NULL,
    `statut`        ENUM('brouillon','programmee','en_cours','envoyee','annulee')
                    NOT NULL DEFAULT 'brouillon',
    `programmee_le` DATETIME      NULL,
    `envoyee_le`    DATETIME      NULL,
    `cree_par`      INT UNSIGNED  NULL,
    `cree_le`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `maj_le`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_statut` (`statut`,`programmee_le`),
    KEY `fk_notifications_annonceur` (`annonceur_id`),
    KEY `fk_notifications_user` (`cree_par`),
    CONSTRAINT `fk_notifications_annonceur` FOREIGN KEY (`annonceur_id`)
        REFERENCES `annonceurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`cree_par`)
        REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 14. envois — journal d’envoi (EF-G04 : envoyés, délivrés, cliqués)
-- ----------------------------------------------------------------------------
CREATE TABLE `envois` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `notification_id` INT UNSIGNED NOT NULL,
    `abonne_id`     BIGINT UNSIGNED NOT NULL,
    `statut`        ENUM('en_attente','envoye','delivre','clique','echec','expire')
                    NOT NULL DEFAULT 'en_attente',
    `erreur`        VARCHAR(255)  NULL,
    `date_envoi`    DATETIME      NULL,
    `date_clic`     DATETIME      NULL,
    `cree_le`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_envois_couple` (`notification_id`,`abonne_id`),
    KEY `idx_envois_statut` (`statut`),
    KEY `fk_envois_abonne` (`abonne_id`),
    CONSTRAINT `fk_envois_notification` FOREIGN KEY (`notification_id`)
        REFERENCES `notifications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_envois_abonne` FOREIGN KEY (`abonne_id`)
        REFERENCES `abonnes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 15. poses — preuves de pose (photo horodatée et géolocalisée, EF-B06)
-- ----------------------------------------------------------------------------
CREATE TABLE `poses` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `panneau_id`    INT UNSIGNED  NOT NULL,
    `campagne_id`   INT UNSIGNED  NOT NULL,
    `photo`         VARCHAR(255)  NOT NULL,
    `latitude`      DECIMAL(10,7) NULL,
    `longitude`     DECIMAL(10,7) NULL,
    `horodatage`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `agent_id`      INT UNSIGNED  NULL,
    `controle_15j`  ENUM('non_fait','conforme','non_conforme') NOT NULL DEFAULT 'non_fait',
    `controle_30j`  ENUM('non_fait','conforme','non_conforme') NOT NULL DEFAULT 'non_fait',
    `note`          VARCHAR(400)  NULL,
    `cree_le`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_poses_panneau` (`panneau_id`),
    KEY `fk_poses_campagne` (`campagne_id`),
    KEY `fk_poses_agent` (`agent_id`),
    CONSTRAINT `fk_poses_panneau` FOREIGN KEY (`panneau_id`)
        REFERENCES `panneaux` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_poses_campagne` FOREIGN KEY (`campagne_id`)
        REFERENCES `campagnes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_poses_agent` FOREIGN KEY (`agent_id`)
        REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 16. journal_operations — journal des opérations d’administration (EF-G05)
-- ----------------------------------------------------------------------------
CREATE TABLE `journal_operations` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `utilisateur_id` INT UNSIGNED NULL,
    `action`        VARCHAR(80)   NOT NULL,
    `entite`        VARCHAR(60)   NULL,
    `entite_id`     INT UNSIGNED  NULL,
    `detail`        VARCHAR(500)  NULL,
    `adresse_ip`    VARCHAR(64)   NULL,
    `date`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_journal_date` (`date`),
    KEY `idx_journal_action` (`action`),
    KEY `fk_journal_user` (`utilisateur_id`),
    CONSTRAINT `fk_journal_user` FOREIGN KEY (`utilisateur_id`)
        REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 17. parametres — paramètres applicatifs (clés de notification, réglages)
-- ----------------------------------------------------------------------------
CREATE TABLE `parametres` (
    `cle`       VARCHAR(80)  NOT NULL,
    `valeur`    TEXT         NULL,
    `libelle`   VARCHAR(180) NULL,
    `maj_le`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
--  VUES DE LECTURE (statistiques)
-- ============================================================================

CREATE OR REPLACE VIEW `v_stats_panneau` AS
SELECT
    p.id                AS panneau_id,
    p.reference,
    p.libelle,
    p.ville,
    COUNT(DISTINCT e.id)                                            AS evenements_total,
    SUM(e.type = 'scan')                                            AS scans,
    COUNT(DISTINCT CASE WHEN e.type = 'scan' THEN e.session_id END) AS visiteurs_uniques
FROM panneaux p
LEFT JOIN evenements e ON e.panneau_id = p.id
GROUP BY p.id;

CREATE OR REPLACE VIEW `v_stats_campagne` AS
SELECT
    c.id AS campagne_id,
    c.nom,
    a.nom AS annonceur,
    COUNT(DISTINCT CASE WHEN e.type = 'scan' THEN e.id END)          AS scans_bruts,
    COUNT(DISTINCT CASE WHEN e.type = 'scan' THEN e.session_id END)  AS visiteurs_uniques,
    SUM(e.type = 'clic_appel')                                       AS clics_appel,
    SUM(e.type = 'clic_whatsapp')                                    AS clics_whatsapp,
    SUM(e.type = 'clic_itineraire')                                  AS clics_itineraire,
    SUM(e.type = 'installation')                                     AS installations,
    SUM(e.type = 'abonnement')                                       AS abonnements
FROM campagnes c
JOIN annonceurs a ON a.id = c.annonceur_id
LEFT JOIN evenements e ON e.campagne_id = c.id
GROUP BY c.id;
