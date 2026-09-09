<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/Models/organizer_profile.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Vérifier si la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données
$data = json_decode(file_get_contents('php://input'), true);

// Valider les données requises
if (empty($data['name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Le nom est requis']);
    exit;
}

// Nettoyer et valider les données
$profileData = [
    'name' => strip_tags(trim($data['name'])),
    'address' => isset($data['address']) ? strip_tags(trim($data['address'])) : null,
    'description' => isset($data['description']) ? strip_tags(trim($data['description'])) : null,
    'website' => isset($data['website']) ? filter_var(trim($data['website']), FILTER_SANITIZE_URL) : null,
    'phone' => isset($data['phone']) ? strip_tags(trim($data['phone'])) : null,
    'email' => isset($data['email']) ? filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL) : null
];

// Créer ou mettre à jour le profil
$organizerProfile = new OrganizerProfile($db, $_SESSION['user_id']);
$result = $organizerProfile->createOrUpdate($profileData);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Profil mis à jour avec succès']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du profil']);
}
