<?php
/**
 * Classe: Subscribers (service)
 * Rôle: Gestion des abonnements aux notifications d'événements
 * Utilisation: Enregistrement, vérification, préférences, notifications
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../logs/error.log.php';

class Subscribers {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = getConnection();
    }

    /**
     * Enregistre un nouvel abonné
     * @param string $email
     * @param array $categoryIds
     * @param string $frequency
     * @return array ['success' => bool, 'message' => string, 'subscriber_id' => int|null]
     */
    public function register($email, $categoryIds = [], $frequency = 'immediate') {
        try {
            // Validation email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Email invalide'];
            }

            // Vérifier si email existe déjà (on récupère is_active pour permettre réactivation)
            $stmt = $this->pdo->prepare('SELECT id, verified_at, is_active FROM event_subscribers WHERE email = ?');
            $stmt->execute([$email]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing && $existing['verified_at']) {
                // Variante 1: si déjà vérifié mais désabonné, on réactive directement
                if ((int)($existing['is_active'] ?? 0) === 0) {
                    $stmt = $this->pdo->prepare(
                        'UPDATE event_subscribers SET is_active = 1, notification_frequency = ?, updated_at = NOW() WHERE id = ?'
                    );
                    $stmt->execute([$frequency, $existing['id']]);

                    // Mettre à jour les préférences
                    if (!empty($categoryIds) && is_array($categoryIds)) {
                        $this->setPreferences($existing['id'], $categoryIds);
                    }

                    

                    return [
                        'success' => true,
                        'message' => 'Réabonnement activé.',
                        'subscriber_id' => $existing['id'],
                        'token' => null
                    ];
                }

                // Sinon, déjà abonné et actif
                return ['success' => false, 'message' => 'Cet email est déjà abonné'];
            }

            // Générer token de vérification
            $token = bin2hex(random_bytes(32));

            // Insérer ou mettre à jour
            if ($existing) {
                $stmt = $this->pdo->prepare(
                    'UPDATE event_subscribers SET verification_token = ?, notification_frequency = ?, updated_at = NOW() WHERE id = ?'
                );
                $stmt->execute([$token, $frequency, $existing['id']]);
                $subscriberId = $existing['id'];
            } else {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO event_subscribers (email, verification_token, notification_frequency) VALUES (?, ?, ?)'
                );
                $stmt->execute([$email, $token, $frequency]);
                $subscriberId = $this->pdo->lastInsertId();
            }

            // Sauvegarder les préférences
            if (!empty($categoryIds) && is_array($categoryIds)) {
                $this->setPreferences($subscriberId, $categoryIds);
            }

            

            return [
                'success' => true,
                'message' => 'Inscription réussie. Vérifiez votre email.',
                'subscriber_id' => $subscriberId,
                'token' => $token
            ];
        } catch (Exception $e) {
            logError('Subscribers.php', 'Erreur register', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erreur lors de l\'inscription'];
        }
    }

    /**
     * Vérifie le token et active l'abonné
     * @param string $token
     * @return array ['success' => bool, 'message' => string]
     */
    public function verify($token) {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE event_subscribers SET verified_at = NOW(), is_active = 1 WHERE verification_token = ? AND verified_at IS NULL'
            );
            $stmt->execute([$token]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Token invalide ou expiré'];
            }

            
            return ['success' => true, 'message' => 'Email vérifié avec succès'];
        } catch (Exception $e) {
            logError('Subscribers.php', 'Erreur verify', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erreur lors de la vérification'];
        }
    }

    /**
     * Définit les préférences de catégories pour un abonné
     * @param int $subscriberId
     * @param array $categoryIds
     * @return bool
     */
    public function setPreferences($subscriberId, $categoryIds) {
        try {
            // Supprimer les préférences existantes
            $stmt = $this->pdo->prepare('DELETE FROM subscriber_preferences WHERE subscriber_id = ?');
            $stmt->execute([$subscriberId]);

            // Insérer les nouvelles
            if (!empty($categoryIds)) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO subscriber_preferences (subscriber_id, category_id) VALUES (?, ?)'
                );
                foreach ($categoryIds as $categoryId) {
                    $stmt->execute([$subscriberId, (int)$categoryId]);
                }
            }

            return true;
        } catch (Exception $e) {
            logError('Subscribers.php', 'Erreur setPreferences', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Récupère les préférences d'un abonné
     * @param int $subscriberId
     * @return array
     */
    public function getPreferences($subscriberId) {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT category_id FROM subscriber_preferences WHERE subscriber_id = ?'
            );
            $stmt->execute([$subscriberId]);
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category_id');
        } catch (Exception $e) {
            logError('Subscribers.php', 'Erreur getPreferences', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Récupère les abonnés intéressés par une catégorie
     * @param int $categoryId
     * @param string $frequency
     * @return array
     */
    public function getSubscribersByCategory($categoryId, $frequency = 'immediate') {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT DISTINCT es.id, es.email, es.notification_frequency
                 FROM event_subscribers es
                 JOIN subscriber_preferences sp ON es.id = sp.subscriber_id
                 WHERE sp.category_id = ? AND es.is_active = 1 AND es.verified_at IS NOT NULL
                 AND es.notification_frequency = ?'
            );
            $stmt->execute([$categoryId, $frequency]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            logError('Subscribers.php', 'Erreur getSubscribersByCategory', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Enregistre une notification envoyée
     * @param int $subscriberId
     * @param int $eventId
     * @param string $type 'new' ou 'update'
     * @return bool
     */
    public function recordNotification($subscriberId, $eventId, $type = 'new') {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT IGNORE INTO subscriber_notifications (subscriber_id, event_id, notification_type) VALUES (?, ?, ?)'
            );
            $allowed = ['new', 'update', 'cancelled'];
            $stmt->execute([$subscriberId, $eventId, in_array($type, $allowed, true) ? $type : 'new']);
            return true;
        } catch (Exception $e) {
            logError('Subscribers.php', 'Erreur recordNotification', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Envoie une notification aux abonnés intéressés par les catégories d'un événement.
     * Type : 'cancelled' si l'événement est annulé (is_cancelled=1, prioritaire),
     * sinon $context ('new'|'update') s'il est fourni, sinon détection auto
     * ('update' si l'événement a déjà été notifié, 'new' sinon).
     * Seuls les abonnés en fréquence 'immediate', actifs et vérifiés sont
     * notifiés (dédupliqués par id).
     * @param int      $eventId
     * @param string   $context       'auto' | 'new' | 'update'
     * @param string[] $changedFields Libellés des éléments modifiés (affichés si type 'update')
     * @return array ['sent' => int, 'skipped' => int, 'type' => string]
     */
    public function notifyEvent($eventId, string $context = 'auto', array $changedFields = []) {
        $result = ['sent' => 0, 'skipped' => 0, 'type' => 'new'];
        try {
            // Événement (on ne notifie que s'il est approuvé)
            $stmt = $this->pdo->prepare(
                "SELECT id, title, date, start_time, location, description, is_cancelled, cancellation_reason
                 FROM events WHERE id = ? AND (status = 'approved' OR is_cancelled = 1)"
            );
            $stmt->execute([$eventId]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$event) {
                logError('Subscribers.php', 'notifyEvent: événement introuvable ou non approuvé', ['event_id' => $eventId]);
                return $result;
            }

            // Catégories liées à l'événement
            $stmt = $this->pdo->prepare('SELECT category_id FROM event_category_links WHERE event_id = ?');
            $stmt->execute([$eventId]);
            $categoryIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category_id');
            if (empty($categoryIds)) {
                logError('Subscribers.php', 'notifyEvent: aucune catégorie liée', ['event_id' => $eventId]);
                return $result;
            }

            // Type : 'cancelled' si l'événement est annulé (prioritaire),
            // sinon le contexte fourni ('new'|'update'), sinon détection auto
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM subscriber_notifications WHERE event_id = ?');
            $stmt->execute([$eventId]);
            $hasPreviousNotifications = ((int) $stmt->fetchColumn()) > 0;
            $type = ((int)($event['is_cancelled'] ?? 0) === 1)
                ? 'cancelled'
                : (in_array($context, ['new', 'update'], true)
                    ? $context
                    : ($hasPreviousNotifications ? 'update' : 'new'));
            $result['type'] = $type;

            // Ne pas re-notifier une annulation déjà envoyée (ex : modification
            // ultérieure d'un événement annulé)
            if ($type === 'cancelled') {
                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM subscriber_notifications WHERE event_id = ? AND notification_type = 'cancelled'"
                );
                $stmt->execute([$eventId]);
                if ((int) $stmt->fetchColumn() > 0) {
                    logError('Subscribers.php', 'notifyEvent: annulation déjà notifiée', ['event_id' => $eventId]);
                    return $result;
                }
            }

            // Abonnés ciblés, dédupliqués par id
            $targets = [];
            foreach ($categoryIds as $catId) {
                foreach ($this->getSubscribersByCategory($catId, 'immediate') as $sub) {
                    $targets[(int) $sub['id']] = $sub;
                }
            }

            if (empty($targets)) {
                logError('Subscribers.php', 'notifyEvent: aucun abonné ciblé', [
                    'event_id' => $eventId,
                    'categories' => $categoryIds
                ]);
                return $result;
            }

            require_once __DIR__ . '/../../includes/mailer.php';
            $mailer = new Mailer();

            foreach ($targets as $sub) {
                try {
                    if ($mailer->sendEventNotificationEmail($sub['email'], $event, $type, $changedFields)) {
                        $this->recordNotification((int) $sub['id'], $eventId, $type);
                        $result['sent']++;
                    } else {
                        $result['skipped']++;
                    }
                } catch (Exception $e) {
                    $result['skipped']++;
                    logError('Subscribers.php', 'notifyEvent: échec envoi', [
                        'event_id' => $eventId,
                        'subscriber_id' => $sub['id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            logError('Subscribers.php', 'notifyEvent: envoi terminé', [
                'event_id' => $eventId,
                'type' => $type,
                'sent' => $result['sent'],
                'skipped' => $result['skipped']
            ]);
        } catch (Exception $e) {
            logError('Subscribers.php', 'Erreur notifyEvent', [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);
        }
        return $result;
    }

    /**
     * Désabonne un abonné
     * @param int $subscriberId
     * @return bool
     */
    public function unsubscribe($subscriberId) {
        try {
            $stmt = $this->pdo->prepare('UPDATE event_subscribers SET is_active = 0 WHERE id = ?');
            $stmt->execute([$subscriberId]);
            return true;
        } catch (Exception $e) {
            logError('Subscribers.php', 'Erreur unsubscribe', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Récupère les infos d'un abonné par email
     * @param string $email
     * @return array|null
     */
    public function getByEmail($email) {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM event_subscribers WHERE email = ?');
            $stmt->execute([$email]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            logError('Subscribers.php', 'Erreur getByEmail', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Récupère les infos d'un abonné par ID
     * @param int $subscriberId
     * @return array|null
     */
    public function getById($subscriberId) {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM event_subscribers WHERE id = ?');
            $stmt->execute([$subscriberId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            logError('Subscribers.php', 'Erreur getById', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
?>
