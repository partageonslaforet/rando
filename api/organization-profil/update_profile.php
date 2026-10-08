<?php
/**
 * Mise à jour du profil organisateur de l'utilisateur connecté.
 */

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Models/organizer_profile.php';

header('Content-Type: application/json');

try {
    // Vérifier si l'utilisateur est connecté
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new Exception('Non autorisé');
    }

    // Initialiser la connexion à la base de données
    $db = getConnection();
    
    // Créer l'instance
    $organizerProfile = new OrganizerProfile($db, $_SESSION['user_id']);

    // Récupérer les données du formulaire
    $profileData = [
        'name' => $_POST['name'] ?? '',
        'address' => $_POST['address'] ?? null,
        'description' => $_POST['description'] ?? null,
        'website' => $_POST['website'] ?? null,
        'phone' => $_POST['phone'] ?? null,
        'email' => $_POST['email'] ?? null
    ];

    // Valider les données requises
    if (empty($profileData['name'])) {
        throw new Exception('Le nom est requis');
    }

    // Nettoyer les données
    $profileData = array_map(function($value) {
        return $value ? strip_tags(trim($value)) : null;
    }, $profileData);

    // Créer ou mettre à jour le profil
    $profile_id = !empty($_POST['profile_id']) ? intval($_POST['profile_id']) : null;
    $result = $organizerProfile->createOrUpdate($profileData, $profile_id);

    // Gérer l'upload du logo si présent
    $logoPath = null;
    if (!empty($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logoPath = $organizerProfile->uploadLogo($_FILES['logo'], $result);
    }

    // Invalider le cache de la pastille header : l'utilisateur a désormais un profil
    $_SESSION['has_organizer_profile'] = true;

    // Préparer la réponse
    $response = [
        'success' => true,
        'message' => 'Profil mis à jour avec succès',
        'profile_id' => $result,
        'logo_path' => $logoPath
    ];

    echo json_encode($response);

} catch (Exception $e) {
    if (!in_array(http_response_code(), [400, 401], true)) {
        http_response_code($e->getMessage() === 'Le nom est requis' ? 400 : 500);
    }
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    echo json_encode($response);
}
