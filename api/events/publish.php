<?php
/**
 * Publication définitive d'un événement à partir d'un brouillon.
 * Valide, persiste et active l'événement, images et parcours associés.
 *
 * Utilisé par : public/assets/js/event-validation.js
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); // Désactiver l'affichage des erreurs
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/publish.log');

session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../src/Services/Storage.php';

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

        // 1. UPDATE si original_event_id est présent, sinon INSERT
        $origId = 0;
        try {
            $st = $pdo->prepare('SELECT original_event_id FROM draft_events WHERE id = ?');
            $st->execute([$draftId]);
            $origId = (int)($st->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            $origId = 0;
        }

        // Créer le profil organisateur si un nom personnalisé a été saisi en brouillon
        $organizerId = $draftData['organizer_id'] ?? null;
        if (empty($organizerId) && !empty($draftData['organisation'])) {
            require_once __DIR__ . '/../../src/Models/organizer_profile.php';
            $organizerProfile = new OrganizerProfile($pdo, $_SESSION['user_id']);
            $organizerId = $organizerProfile->createOrUpdate([
                'name' => $draftData['organisation'],
                'email' => null,
                'address' => null,
                'description' => null,
                'website' => null,
                'phone' => null,
            ]);
            $pdo->prepare("UPDATE draft_events SET organizer_id = ? WHERE id = ?")->execute([$organizerId, $draftId]);
            $draftData['organizer_id'] = $organizerId;
        }

        if ($origId) {
            log_message("🔁 Mise à jour de l'événement existant ID=$origId -> statut 'pending'");
            $upd = $pdo->prepare("UPDATE events SET 
                title = :title,
                description = :description,
                date = :date,
                registration_opens = :reg_open,
                registration_closes = :reg_close,
                location = :location,
                venue = :venue,
                coordinates = :coordinates,
                meeting_name = :m_name,
                meeting_address = :m_addr,
                meeting_city = :m_city,
                meeting_coordinates = :m_coord,
                organizer_id = :organizer_id,
                organisation = :organisation,
                status = 'pending'
                WHERE id = :id");
            $upd->execute([
                ':title' => $draftData['title'] ?? null,
                ':description' => $draftData['description'] ?? null,
                ':date' => $draftData['date'] ?? null,
                ':reg_open' => $draftData['registration_opens'] ?? null,
                ':reg_close' => $draftData['registration_closes'] ?? null,
                ':location' => $draftData['location'] ?? null,
                ':venue' => $draftData['venue'] ?? null,
                ':coordinates' => $draftData['coordinates'] ?? null,
                ':m_name' => $draftData['meeting_name'] ?? null,
                ':m_addr' => $draftData['meeting_address'] ?? null,
                ':m_city' => $draftData['meeting_city'] ?? null,
                ':m_coord' => $draftData['meeting_coordinates'] ?? null,
                ':organizer_id' => $organizerId,
                ':organisation' => $draftData['organisation'] ?? null,
                ':id' => $origId,
            ]);
            $eventId = $origId;
        } else {
            log_message("🔄 Copie des informations principales de l'événement (INSERT)");
            $stmt = $pdo->prepare("
                INSERT INTO events (
                    user_id, title, description, date,
                    start_time, end_time,
                    registration_opens, registration_closes,
                    location, venue, coordinates,
                    meeting_name, meeting_address, meeting_city, meeting_coordinates,
                    organizer_id, status, organisation
                )
                SELECT
                    user_id, title, description, date,
                    registration_opens, registration_closes,
                    registration_opens, registration_closes,
                    location, venue, coordinates,
                    meeting_name, meeting_address, meeting_city, meeting_coordinates,
                    ?, 'pending', organisation
                FROM draft_events WHERE id = ?
            ");
            try {
                $stmt->execute([$organizerId, $draftId]);
                $eventId = $pdo->lastInsertId();
                log_message("✅ Événement créé avec l'ID: $eventId");
            } catch (PDOException $e) {
                log_message("❌ Erreur lors de l'insertion de l'événement: " . $e->getMessage(), true);
                throw new Exception("Erreur lors de l'insertion de l'événement: " . $e->getMessage());
            }
        }

        // 2. Associer les tags et synchroniser category_id
        log_message("🔄 Association des tags d'activité");
        try {
            if ($origId) {
                $pdo->prepare('DELETE FROM event_category_links WHERE event_id = ?')->execute([$eventId]);
            }
            $stmt = $pdo->prepare("
                INSERT INTO event_category_links (event_id, category_id)
                SELECT ?, category_id FROM draft_event_category_links WHERE draft_event_id = ?
            ");
            $stmt->execute([$eventId, $draftId]);
            log_message("✅ Tags associés");

            // Synchroniser category_id avec le premier tag
            $stmt = $pdo->prepare("
                UPDATE events e
                SET e.category_id = (
                    SELECT dcl.category_id 
                    FROM draft_event_category_links dcl 
                    WHERE dcl.draft_event_id = ? 
                    ORDER BY dcl.category_id ASC 
                    LIMIT 1
                )
                WHERE e.id = ?
            ");
            $stmt->execute([$draftId, $eventId]);
            log_message("✅ Category_id synchronisé");
        } catch (PDOException $e) {
            log_message("❌ Erreur lors de l'association des tags: " . $e->getMessage(), true);
            throw new Exception("Erreur lors de l'association des tags");
        }

        // 3. Copier les images
        log_message("🔄 Copie des images");
        try {
            if ($origId) {
                $pdo->prepare('DELETE FROM event_images WHERE event_id = ?')->execute([$eventId]);
            }
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
            if ($origId) {
                $pdo->prepare('DELETE FROM event_contacts WHERE event_id = ?')->execute([$eventId]);
            }
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
            if ($origId) {
                $pdo->prepare('DELETE FROM event_parcours WHERE event_id = ?')->execute([$eventId]);
            }
            $stmt = $pdo->prepare("
                INSERT INTO event_parcours (
                    event_id, name, category_id, distance, elevation_gain, description,
                    gpx_file, gpx_downloadable, price, created_at, updated_at
                )
                SELECT 
                    ?, name, category_id, distance, elevation_gain, description,
                    gpx_file, gpx_downloadable, price, NOW(), NOW()
                FROM draft_parcours WHERE event_id = ?
            ");
            $stmt->execute([$eventId, $draftId]);
            log_message("✅ Parcours copiés");

            // Les GPX sont uploadés dans gpx/temp/ (upload-gpx.php) : à la publication,
            // on les déplace vers gpx/ et on met à jour gpx_file, sinon les URLs temporaires
            // cassent dès que temp/ est purgé (fetch → HTML 200 → XML Parsing Error).
            try {
                $gpxStmt = $pdo->prepare(
                    "SELECT id, gpx_file FROM event_parcours
                     WHERE event_id = ? AND gpx_file LIKE '%/temp/%'"
                );
                $gpxStmt->execute([$eventId]);
                foreach ($gpxStmt->fetchAll(PDO::FETCH_ASSOC) as $gpxRow) {
                    $basename = basename((string)$gpxRow['gpx_file']);
                    $srcPath = Storage::getStoragePath('gpx', 'temp/' . $basename);
                    $dstPath = Storage::getStoragePath('gpx', $basename);
                    if (!is_file($srcPath)) {
                        // Fichier temporaire déjà perdu : la référence pointerait vers du HTML (rewrite)
                        $pdo->prepare('UPDATE event_parcours SET gpx_file = NULL WHERE id = ?')
                            ->execute([$gpxRow['id']]);
                        $pdo->prepare('UPDATE draft_parcours SET gpx_file = NULL WHERE event_id = ? AND gpx_file = ?')
                            ->execute([$draftId, $gpxRow['gpx_file']]);
                        log_message("⚠️ GPX temporaire introuvable, référence vidée : {$gpxRow['gpx_file']}", true);
                        continue;
                    }
                    Storage::ensureDirectoryExists(dirname($dstPath));
                    if (rename($srcPath, $dstPath)) {
                        $newUrl = Storage::getPublicUrl('gpx', $basename);
                        $pdo->prepare('UPDATE event_parcours SET gpx_file = ? WHERE id = ?')
                            ->execute([$newUrl, $gpxRow['id']]);
                        $pdo->prepare('UPDATE draft_parcours SET gpx_file = ? WHERE event_id = ? AND gpx_file = ?')
                            ->execute([$newUrl, $draftId, $gpxRow['gpx_file']]);
                        log_message("✅ GPX déplacé : temp/{$basename} → {$basename}");
                    } else {
                        log_message("⚠️ Déplacement GPX impossible : {$basename}", true);
                    }
                }
            } catch (Throwable $e) {
                log_message("❌ Erreur déplacement GPX: " . $e->getMessage(), true);
                throw new Exception("Erreur lors de la finalisation des fichiers GPX");
            }
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

        $response = ['success' => true, 'eventId' => $eventId, 'isUpdate' => (bool)$origId];

        // Envoyer l'email de confirmation (sauf si admin connecté)
        try {
            $isAdminSession = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
            if ($isAdminSession) {
                log_message("🛑 Session admin détectée: on n'envoie pas d'e-mails de publication.");
            }
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
                    if (!$isAdminSession) {
                        log_message("🔄 Création de l'instance Mailer");
                        $mailer = new Mailer();
                        log_message("✅ Instance Mailer créée");

                        log_message("🔄 Envoi de l'email à " . $eventData['user_email']);
                        $mailer->sendEventPublishedEmail($eventData['user_email'], $eventData);
                        log_message("✅ Email de confirmation envoyé à " . $eventData['user_email']);

                        // Notification admin pour validation
                        $adminTo = defined('CONTACT_TO_EMAIL') ? CONTACT_TO_EMAIL : 'rando@partageonslaforet.be';
                        try {
                            // Ajouter le timestamp de publication pour l'email
                            $eventData['published_at'] = date('Y-m-d H:i:s');
                            $mailer->sendAdminEventPendingEmail($adminTo, $eventData);
                            log_message("✅ Email admin de validation envoyé à $adminTo");
                        } catch (Throwable $e) {
                            log_message("❌ Erreur envoi email admin: " . $e->getMessage(), true);
                        }
                        $response['email'] = 'sent';
                    } else {
                        $response['email'] = 'skipped_admin';
                    }
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
