-- ===========================================================================
-- SOURIANE — Portée de l’abonnement aux notifications
--
--   « Une fois l’application installée, l’utilisateur ne suit pas seulement
--     l’annonceur dont il a scanné le QR code : il peut demander à recevoir
--     les offres de l’ensemble de la plateforme, et consulter les autres
--     publicités. »
--
-- Jusqu’ici un abonnement était nécessairement rattaché à un annonceur
-- (`abonnes.annonceur_id` NOT NULL, clé étrangère vers `annonceurs`). On
-- ajoute une portée :
--
--   · 'annonceur'  — comportement d’origine : l’abonné ne reçoit que les
--                    offres de l’annonceur de la page où il s’est inscrit ;
--   · 'plateforme' — l’abonné reçoit les offres de tous les annonceurs ;
--                    `annonceur_id` vaut alors NULL (la clé étrangère accepte
--                    NULL : la contrainte reste valable).
--
-- Le plafond de quatre notifications par mois reste apprécié par annonceur
-- (ch. 5.5) : élargir la portée ne le fait pas sauter, il change simplement
-- le nombre d’abonnés servis par chaque envoi.
--
-- Rejouable : la colonne est ajoutée avec IF NOT EXISTS, l’index aussi, et la
-- modification de nullabilité est sans effet si elle est déjà appliquée.
-- ===========================================================================

ALTER TABLE abonnes
    ADD COLUMN IF NOT EXISTS portee ENUM('annonceur','plateforme')
        NOT NULL DEFAULT 'annonceur'
        COMMENT 'Étendue de l’abonnement : un annonceur, ou toute la plateforme'
        AFTER frequence;

-- `annonceur_id` devient facultatif : NULL = abonnement à toute la plateforme.
ALTER TABLE abonnes
    MODIFY COLUMN annonceur_id INT UNSIGNED NULL
        COMMENT 'NULL lorsque portee = plateforme';

-- Accélère la recherche des abonnés « plateforme » lors d’un envoi.
ALTER TABLE abonnes
    ADD INDEX IF NOT EXISTS idx_abonnes_portee_statut (portee, statut);
