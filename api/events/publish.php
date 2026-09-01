<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Désactiver l'affichage des erreurs
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/publish.log');

session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Logs
$logFile = __DIR__ . '/publish.log';
function log_message($msg, $error = false) {
    global $logFile;
    $date = date('Y-m-d H:i:s');
    $message = "[$date] " . ($error ? "❌ " : "") . $msg . "\n";
    error_log($message);  // Aussi envoyer au log système
    file_put_contents($logFile, $message, FILE_APPEND);
}

// S'assurer qu'aucune sortie n'a été envoyée avant
if (headers_sent($filename, $linenum)) {
    log_message("❌ Headers déjà envoyés dans $filename à la ligne $linenum", true);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Headers déjà envoyés',
        'file' => $filename,
        'line' => $linenum
    ]);
    exit;
}

// Définir le type de contenu avant toute sortie
header('Content-Type: application/json');

log_message("=== Début de la requête ===");
log_message("📝 POST data: " . print_r($_POST, true));
log_message("📝 SESSION data: " . print_r($_SESSION, true));
log_message("📝 SERVER data: " . print_r($_SERVER, true));

try {
    // Vérifier si l'utilisateur est connecté
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Utilisateur non connecté");
    }
    log_message("✅ Utilisateur connecté (ID: " . $_SESSION['user_id'] . ")");

    // Vérifier l'ID du brouillon
    if (empty($_POST['draftId'])) {
        throw new Exception("ID du brouillon manquant");
    }
    $draftId = $_POST['draftId'];
    log_message("Draft ID: $draftId");

    // Connexion à la base de données
    $pdo = getConnection();
    $pdo->beginTransaction();

    try {
        // Vérifier les données du brouillon avant la copie
        $checkStmt = $pdo->prepare("SELECT * FROM draft_events WHERE id = ?");
        $checkStmt->execute([$draftId]);
        $draftData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$draftData) {
            throw new Exception("Brouillon non trouvé: $draftId");
        }
        log_message("✅ Données du brouillon trouvées");
        log_message("📝 Informations de l'événement:");
        log_message("- Titre: " . ($draftData['title'] ?? 'Non défini'));
        log_message("- Date: " . ($draftData['date'] ?? 'Non définie'));
        log_message("- Heures d'inscription: " . ($draftData['registration_opens'] ?? 'Non définie') . " à " . ($draftData['registration_closes'] ?? 'Non définie'));
        log_message("- Lieu: " . ($draftData['location'] ?? 'Non défini'));
        log_message("- Adresse: " . ($draftData['venue'] ?? 'Non définie'));
        log_message("- Coordonnées: " . ($draftData['coordinates'] ?? 'Non définies'));

        // 1. Insérer l'événement
        log_message("🔄 Copie des informations principales de l'événement");
        $stmt = $pdo->prepare("
            INSERT INTO events (
                user_id, title, description, date,
                registration_opens, registration_closes,
                location, venue, coordinates, status
            ) 
            SELECT 
                user_id, title, description, date,
                registration_opens, registration_closes,
                location, venue, coordinates, 'pending'
            FROM draft_events WHERE id = ?
        ");
        try {
            $stmt->execute([$draftId]);
            $eventId = $pdo->lastInsertId();
            log_message("✅ Événement créé avec l'ID: $eventId");
        } catch (PDOException $e) {
            log_message("❌ Erreur lors de l'insertion de l'événement: " . $e->getMessage(), true);
            throw new Exception("Erreur lors de l'insertion de l'événement: " . $e->getMessage());
        }

        // 2. Associer les tags et synchroniser category_id
        log_message("🔄 Association des tags d'activité");
        try {
            $stmt = $pdo->prepare("
                INSERT INTO event_category_links (event_id, category_id)
                SELECT ?, category_id FROM draft_event_category_links WHERE draft_event_id = ?
            ");
            $stmt->execute([$eventId, $draftId]);
            log_message("✅ Tags associés");

            // Synchroniser category et category_id avec le premier tag
            $stmt = $pdo->prepare("
                UPDATE events e
                SET e.category_id = (
                    SELECT dcl.category_id 
                    FROM draft_event_category_links dcl 
                    WHERE dcl.draft_event_id = ? 
                    ORDER BY dcl.category_id ASC 
                    LIMIT 1
                ),
                e.category = (
                    SELECT c.code 
                    FROM draft_event_category_links dcl 
                    JOIN event_categories c ON dcl.category_id = c.id 
                    WHERE dcl.draft_event_id = ? 
                    ORDER BY dcl.category_id ASC 
                    LIMIT 1
                )
                WHERE e.id = ?
            ");
            $stmt->execute([$draftId, $draftId, $eventId]);
            log_message("✅ Category_id synchronisé");
        } catch (PDOException $e) {
            log_message("❌ Erreur lors de l'association des tags: " . $e->getMessage(), true);
            throw new Exception("Erreur lors de l'association des tags");
        }

        // 3. Copier les images
        log_message("🔄 Copie des images");
        try {
            $stmt = $pdo->prepare("
                INSERT INTO event_images (event_id, image_path, storage_path, is_main, storage_type)
                SELECT ?, image_path, storage_path, is_main, storage_type
                FROM draft_images WHERE event_id = ?
            ");
            $stmt->execute([$eventId, $draftId]);
            log_message("✅ Images copiées");
        } catch (PDOException $e) {
            log_message("❌ Erreur lors de la copie des images: " . $e->getMessage(), true);
            throw new Exception("Erreur lors de la copie des images");
        }

        // 3. Copier les contacts
        log_message("🔄 Copie des contacts");
        try {
            $stmt = $pdo->prepare("
                INSERT INTO event_contacts (event_id, name, email, phone)
                SELECT ?, name, email, phone
                FROM draft_contacts WHERE event_id = ?
            ");
            $stmt->execute([$eventId, $draftId]);
            log_message("✅ Contacts copiés");
        } catch (PDOException $e) {
            log_message("❌ Erreur lors de la copie des contacts: " . $e->getMessage(), true);
            throw new Exception("Erreur lors de la copie des contacts");
        }

        // 4. Copier les parcours
        log_message("🔄 Copie des parcours");
        try {
            $stmt = $pdo->prepare("
                INSERT INTO event_parcours (
                    event_id, name, distance, elevation_gain, description,
                    gpx_file, gpx_downloadable, price, created_at, updated_at
                )
                SELECT 
                    ?, name, distance, elevation_gain, description,
                    gpx_file, gpx_downloadable, price, NOW(), NOW()
                FROM draft_parcours WHERE event_id = ?
            ");
            $stmt->execute([$eventId, $draftId]);
            log_message("✅ Parcours copiés");
        } catch (PDOException $e) {
            log_message("❌ Erreur lors de la copie des parcours: " . $e->getMessage(), true);
            throw new Exception("Erreur lors de la copie des parcours");
        }

        // 5. Marquer le brouillon comme publié
        log_message("🔄 Mise à jour du statut du brouillon");
        try {
            $stmt = $pdo->prepare("UPDATE draft_events SET status = 'published' WHERE id = ?");
            $stmt->execute([$draftId]);
            log_message("✅ Statut du brouillon mis à jour");
        } catch (PDOException $e) {
            log_message("❌ Erreur lors de la mise à jour du statut: " . $e->getMessage(), true);
            throw new Exception("Erreur lors de la mise à jour du statut");
        }

        // Valider la transaction
        $pdo->commit();
        log_message("✅ Transaction validée avec succès");

        $response = ['success' => true, 'eventId' => $eventId];

        // Envoyer l'email de confirmation
        try {
            // Récupérer les informations de l'événement et de l'utilisateur
            log_message("🔄 Récupération des données pour l'email");
            $stmt = $pdo->prepare("
                SELECT e.*, u.email as user_email 
                FROM events e 
                JOIN users u ON e.user_id = u.id 
                WHERE e.id = ?
            ");
            $stmt->execute([$eventId]);
            $eventData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($eventData) {
                log_message("✅ Données trouvées pour l'email");
                log_message("📝 Données de l'événement: " . json_encode($eventData, JSON_PRETTY_PRINT));

                // Ajouter la clé eventId pour le template
                $eventData['eventId'] = $eventData['id'];

                require_once __DIR__ . '/../../includes/mailer.php';
                log_message("✅ Classe Mailer chargée");

                try {
                    log_message("🔄 Création de l'instance Mailer");
                    $mailer = new Mailer();
                    log_message("✅ Instance Mailer créée");

                    log_message("🔄 Envoi de l'email à " . $eventData['user_email']);
                    $mailer->sendEventPublishedEmail($eventData['user_email'], $eventData);
                    log_message("✅ Email de confirmation envoyé à " . $eventData['user_email']);
                    $response['email'] = 'sent';
                } catch (Exception $e) {
                    log_message("❌ Erreur lors de l'envoi de l'email via Mailer : " . $e->getMessage(), true);
                    log_message("📝 Stack trace: " . $e->getTraceAsString());
                    $response['email'] = 'error';
                    $response['emailError'] = $e->getMessage();
                    $response['emailTrace'] = $e->getTraceAsString();
                }
            } else {
                log_message("❌ Impossible de trouver les données de l'événement pour l'email", true);
                $response['email'] = 'no_data';
            }
        } catch (Exception $e) {
            log_message("❌ Erreur lors de la préparation de l'email : " . $e->getMessage(), true);
            log_message("📝 Stack trace: " . $e->getTraceAsString());
            $response['email'] = 'error';
            $response['emailError'] = $e->getMessage();
            $response['emailTrace'] = $e->getTraceAsString();
        }

        // Renvoyer une réponse détaillée
        echo json_encode($response, JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        $pdo->rollBack();
        log_message("❌ Erreur lors de la publication: " . $e->getMessage(), true);
        log_message("📝 Stack trace: " . $e->getTraceAsString());
        
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], JSON_PRETTY_PRINT);
    }

} catch (Exception $e) {
    log_message("❌ Erreur critique: " . $e->getMessage(), true);
    log_message("📝 Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
