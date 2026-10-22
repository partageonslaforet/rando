-- Historique de TOUS les emails sortants (transactionnels, contact, abonnés, mailing).
-- MySQL : les index sont déclarés inline (CREATE INDEX IF NOT EXISTS n'existe pas en MySQL).
-- Peuplé par Mailer::sendHtml() / Mailer::recordSentEmail() dans includes/mailer.php.
CREATE TABLE IF NOT EXISTS sent_emails (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient   VARCHAR(190) NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    body_html   MEDIUMTEXT   DEFAULT NULL,
    type        VARCHAR(50)  NOT NULL DEFAULT 'generic',
    status      VARCHAR(10)  NOT NULL DEFAULT 'ok',
    error       TEXT         DEFAULT NULL,
    sent_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sent_emails_sent (sent_at),
    KEY idx_sent_emails_email (recipient),
    KEY idx_sent_emails_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Types utilisés :
--   generic                    : appel sendHtml() sans type explicite
--   verification               : vérification de compte utilisateur
--   password_reset             : réinitialisation mot de passe
--   password_change            : confirmation changement mot de passe (profil)
--   event_pending              : accusé à l'utilisateur (événement soumis)
--   event_pending_admin        : notification admin (événement à valider)
--   event_approved             : événement approuvé
--   event_rejected             : événement refusé
--   event_notification_new     : abonnés — nouvel événement
--   event_notification_update  : abonnés — événement mis à jour
--   event_notification_cancelled : abonnés — événement annulé
--   organizer_online           : notification organisateur (événement en ligne)
--   subscriber_verify          : confirmation d'abonnement newsletter
--   subscriber_manage          : lien de gestion des abonnements
--   contact                    : formulaire de contact
--   contact_copy               : copie au visiteur du formulaire de contact
--   mailing_single             : publipostage — envoi unitaire
--   mailing_test               : publipostage — email de test
--   mailing_campaign           : publipostage — campagne
