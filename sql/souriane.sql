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
--  Fichier unique regroupant tout le SQL du projet :
--    1. Structure — 18 tables (dont `demandes`)
--    2. Vues de lecture (statistiques)
--    3. Jeu de données de démonstration
--
--  ATTENTION : l’import SUPPRIME puis recrée toutes les tables.
--  En production, retirer la section 3 (comptes de démonstration).
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
DROP TABLE IF EXISTS `demandes`;

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
    KEY `idx_visites_session` (`session_id`),
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

-- ----------------------------------------------------------------------------
-- 18. demandes — demandes de proposition envoyées depuis le site public
-- ----------------------------------------------------------------------------
CREATE TABLE `demandes` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `enseigne`      VARCHAR(180) NOT NULL COMMENT 'Nom de l’enseigne ou de l’entreprise',
    `contact`       VARCHAR(180) NOT NULL COMMENT 'Nom de la personne à contacter',
    `email`         VARCHAR(180) NOT NULL,
    `telephone`     VARCHAR(40)  NULL,
    `ville`         VARCHAR(120) NOT NULL COMMENT 'Ville visée par la campagne',
    `formats`       JSON         NULL COMMENT 'Formats de panneaux souhaités (tableau)',
    `periode`       VARCHAR(120) NULL COMMENT 'Période souhaitée (libre)',
    `budget`        VARCHAR(80)  NULL COMMENT 'Enveloppe envisagée (libre)',
    `message`       TEXT         NULL,
    `statut`        VARCHAR(24)  NOT NULL DEFAULT 'nouveau'
                    COMMENT 'nouveau | contacte | converti | archive',
    `cree_le`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `mis_a_jour_le` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_demandes_statut` (`statut`),
    KEY `idx_demandes_ville` (`ville`)
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

-- ============================================================================
--  JEU DE DONNÉES DE DÉMONSTRATION
--  (à retirer pour une installation en production)
--
--  Comptes créés :
--    admin@souriane.sn   / Souriane2026!   (rôle : regie)
--    agent@souriane.sn   / Souriane2026!   (rôle : agent)
-- ============================================================================

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
