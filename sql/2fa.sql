-- ===========================================================================
-- SOURIANE — Second facteur pour les comptes d’administration (ch. 8)
--
--   « Les comptes d’administration sont protégés par une authentification à
--     deux facteurs. »
--
-- Le secret partagé doit être relu à chaque connexion pour vérifier le code :
-- il ne peut donc pas être haché comme un mot de passe. Il est chiffré par le
-- coffre applicatif (AES-256-GCM, clé dérivée de HASH_SECRET) et stocké dans
-- `totp_secret`.
--
-- Rejouable : chaque colonne est ajoutée avec IF NOT EXISTS, ce que MariaDB
-- accepte depuis la version 10.0. Rejouer ce script ne casse rien.
-- ===========================================================================

ALTER TABLE utilisateurs
    ADD COLUMN IF NOT EXISTS totp_secret        VARCHAR(255) NULL
        COMMENT 'Secret TOTP, chiffré par le coffre applicatif',
    ADD COLUMN IF NOT EXISTS totp_actif         TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 lorsque le second facteur a été confirmé par un code valide',
    ADD COLUMN IF NOT EXISTS totp_confirme_le   DATETIME NULL
        COMMENT 'Date d’activation du second facteur',
    ADD COLUMN IF NOT EXISTS totp_dernier_pas   BIGINT UNSIGNED NULL
        COMMENT 'Dernier intervalle de 30 s consommé : un code ne sert qu’une fois',
    ADD COLUMN IF NOT EXISTS totp_codes_secours TEXT NULL
        COMMENT 'Empreintes SHA-256 des codes de secours, une par ligne';
