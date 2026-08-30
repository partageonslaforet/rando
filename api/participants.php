<?php
require_once '../includes/config.php';
require_once '../classes/Event.php';

// Vérifier si l'utilisateur est connecté
function requireAuth() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Vous devez être connecté pour effectuer cette action'
        ]);
        exit;
    }
}

// Gérer les CORS
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$eventManager = new Event();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Récupérer les participants d'un événement
            if (!isset($_GET['event_id'])) {
                throw new Exception('ID de l\'événement manquant', 400);
            }
            
            $eventId = filter_var($_GET['event_id'], FILTER_VALIDATE_INT);
            if (!$eventId) {
                throw new Exception('ID de l\'événement invalide', 400);
            }
            
            $participants = $eventManager->getParticipants($eventId);
            echo json_encode(['status' => 'success', 'participants' => $participants]);
            break;

        case 'POST':
            requireAuth();
            
            // Récupérer les données
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['event_id'])) {
                throw new Exception('ID de l\'événement manquant', 422);
            }
            
            $eventId = filter_var($input['event_id'], FILTER_VALIDATE_INT);
            if (!$eventId) {
                throw new Exception('ID de l\'événement invalide', 400);
            }
            
            // Vérifier que l'événement existe et est ouvert aux inscriptions
            $event = $eventManager->getById($eventId);
            if (!$event) {
                throw new Exception('Événement non trouvé', 404);
            }
            
            if ($event['status'] !== 'published') {
                throw new Exception('Les inscriptions sont fermées pour cet événement', 400);
            }
            
            // Vérifier s'il reste des places
            if (!empty($event['max_participants']) && 
                $event['current_participants'] >= $event['max_participants']) {
                throw new Exception('L\'événement est complet', 400);
            }
            
            // Vérifier si l'utilisateur n'est pas déjà inscrit
            if ($eventManager->isUserParticipating($eventId, $_SESSION['user_id'])) {
                throw new Exception('Vous êtes déjà inscrit à cet événement', 400);
            }
            
            // Inscrire l'utilisateur
            $eventManager->addParticipant($eventId, $_SESSION['user_id']);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Inscription réussie'
            ]);
            break;

        case 'DELETE':
            requireAuth();
            
            // Vérifier l'ID de l'événement
            if (!isset($_GET['event_id'])) {
                throw new Exception('ID de l\'événement manquant', 400);
            }
            
            $eventId = filter_var($_GET['event_id'], FILTER_VALIDATE_INT);
            if (!$eventId) {
                throw new Exception('ID de l\'événement invalide', 400);
            }
            
            // Vérifier que l'événement existe
            $event = $eventManager->getById($eventId);
            if (!$event) {
                throw new Exception('Événement non trouvé', 404);
            }
            
            // Vérifier si l'utilisateur est inscrit
            if (!$eventManager->isUserParticipating($eventId, $_SESSION['user_id'])) {
                throw new Exception('Vous n\'êtes pas inscrit à cet événement', 400);
            }
            
            // Désinscrire l'utilisateur
            $eventManager->removeParticipant($eventId, $_SESSION['user_id']);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Désinscription réussie'
            ]);
            break;

        default:
            throw new Exception('Méthode non autorisée', 405);
    }
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
