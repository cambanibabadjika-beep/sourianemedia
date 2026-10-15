-- ===========================================================================
-- SOURIANE — Création de la table `demandes`
-- Demandes de proposition envoyées par les prospects depuis le site public.
-- Rejouable : utilise CREATE TABLE IF NOT EXISTS.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS demandes (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    enseigne      VARCHAR(180) NOT NULL COMMENT 'Nom de l’enseigne ou de l’entreprise',
    contact       VARCHAR(180) NOT NULL COMMENT 'Nom de la personne à contacter',
    email         VARCHAR(180) NOT NULL,
    telephone     VARCHAR(40)  NULL,
    ville         VARCHAR(120) NOT NULL COMMENT 'Ville visée par la campagne',
    formats       JSON         NULL COMMENT 'Formats de panneaux souhaités (tableau)',
    periode       VARCHAR(120) NULL COMMENT 'Période souhaitée (libre)',
    budget        VARCHAR(80)  NULL COMMENT 'Enveloppe envisagée (libre)',
    message       TEXT         NULL,
    statut        VARCHAR(24)  NOT NULL DEFAULT 'nouveau'
                  COMMENT 'nouveau | contacte | converti | archive',
    cree_le       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    mis_a_jour_le DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_demandes_statut (statut),
    KEY idx_demandes_ville (ville)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
