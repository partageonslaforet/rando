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
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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
            // Récupérer les commentaires d'un événement
            if (!isset($_GET['event_id'])) {
                throw new Exception('ID de l\'événement manquant', 400);
            }
            
            $eventId = filter_var($_GET['event_id'], FILTER_VALIDATE_INT);
            if (!$eventId) {
                throw new Exception('ID de l\'événement invalide', 400);
            }
            
            $comments = $eventManager->getComments($eventId);
            echo json_encode(['status' => 'success', 'comments' => $comments]);
            break;

        case 'POST':
            requireAuth();
            
            // Récupérer les données
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['event_id']) || !isset($input['content'])) {
                throw new Exception('Données manquantes', 422);
            }
            
            $eventId = filter_var($input['event_id'], FILTER_VALIDATE_INT);
            if (!$eventId) {
                throw new Exception('ID de l\'événement invalide', 400);
            }
            
            // Vérifier que l'événement existe
            $event = $eventManager->getById($eventId);
            if (!$event) {
                throw new Exception('Événement non trouvé', 404);
            }
            
            // Ajouter le commentaire
            $commentId = $eventManager->addComment([
                'event_id' => $eventId,
                'user_id' => $_SESSION['user_id'],
                'content' => trim($input['content'])
            ]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Commentaire ajouté avec succès',
                'comment_id' => $commentId
            ]);
            break;

        case 'PUT':
            requireAuth();
            
            // Vérifier l'ID du commentaire
            if (!isset($_GET['id'])) {
                throw new Exception('ID du commentaire manquant', 400);
            }
            
            $commentId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
            if (!$commentId) {
                throw new Exception('ID du commentaire invalide', 400);
            }
            
            // Récupérer les données
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['content'])) {
                throw new Exception('Contenu manquant', 422);
            }
            
            // Vérifier que l'utilisateur est l'auteur du commentaire
            $comment = $eventManager->getCommentById($commentId);
            if (!$comment || $comment['user_id'] !== $_SESSION['user_id']) {
                throw new Exception('Vous n\'êtes pas autorisé à modifier ce commentaire', 403);
            }
            
            // Mettre à jour le commentaire
            $eventManager->updateComment($commentId, [
                'content' => trim($input['content'])
            ]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Commentaire mis à jour avec succès'
            ]);
            break;

        case 'DELETE':
            requireAuth();
            
            // Vérifier l'ID du commentaire
            if (!isset($_GET['id'])) {
                throw new Exception('ID du commentaire manquant', 400);
            }
            
            $commentId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
            if (!$commentId) {
                throw new Exception('ID du commentaire invalide', 400);
            }
            
            // Vérifier que l'utilisateur est l'auteur du commentaire ou l'organisateur
            $comment = $eventManager->getCommentById($commentId);
            if (!$comment) {
                throw new Exception('Commentaire non trouvé', 404);
            }
            
            $event = $eventManager->getById($comment['event_id']);
            if ($comment['user_id'] !== $_SESSION['user_id'] && 
                $event['organizer_id'] !== $_SESSION['user_id']) {
                throw new Exception('Vous n\'êtes pas autorisé à supprimer ce commentaire', 403);
            }
            
            // Supprimer le commentaire
            $eventManager->deleteComment($commentId);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Commentaire supprimé avec succès'
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
