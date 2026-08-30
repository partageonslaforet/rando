<?php
// Inclure les fonctions
require_once __DIR__ . '/../../includes/functions.php';

// Démarrer la session et vérifier la connexion
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireLogin();

try {
    // Connexion à la base de données
    require_once __DIR__ . '/../../config/database.php';
    $db = getConnection();

    // Récupérer les informations de l'utilisateur
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([getCurrentUserId()]);
    $user = $stmt->fetch();
    
    if (!$user) {
        session_destroy();
        header('Location: /?login=required');
        exit;
    }

    // Vérifier le rôle et rediriger si nécessaire
    if ($user['role'] === 'admin' && strpos($_SERVER['REQUEST_URI'], '/pages/user/') !== false) {
        header('Location: /pages/admin/profile.php');
        exit();
    } elseif ($user['role'] === 'user' && strpos($_SERVER['REQUEST_URI'], '/pages/admin/') !== false) {
        header('Location: /pages/user/profile.php');
        exit();
    }

    // Récupérer le profil organisateur
    require_once __DIR__ . '/../../includes/organizer_profile.php';
    $organizerProfile = new OrganizerProfile($db, getCurrentUserId());
    $profiles = $organizerProfile->getAll();

} catch (Exception $e) {
    error_log('Erreur profile.php: ' . $e->getMessage());
    die('Une erreur est survenue');
}

// Inclure l'en-tête
$pageTitle = "Mon Profil";
require_once __DIR__ . '/../../includes/header-solid.php';
?>

<link rel="stylesheet" href="/assets/css/profile.css">

<div class="profile-container mt-5 pt-5">
    <!-- En-tête du profil -->
    <div class="profile-header text-center">
        <?php if (!empty($profiles)): ?>
            <?php $firstProfile = $profiles[0]; ?>
            <div class="profile-header-image">
                <img src="<?= !empty($firstProfile['logo_path']) ? htmlspecialchars($firstProfile['logo_path']) : '/assets/images/events/default-event.jpg' ?>" 
                     alt="Logo <?= htmlspecialchars($firstProfile['name']) ?>" 
                     class="img-fluid">
            </div>
            <div class="profile-header-content">
                <h1><?= htmlspecialchars($firstProfile['name']) ?></h1>
                <?php if (!empty($firstProfile['description'])): ?>
                    <p><?= nl2br(htmlspecialchars($firstProfile['description'])) ?></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="profile-header-image">
                <img src="/assets/images/events/default-event.jpg" alt="Image par défaut" class="img-fluid">
            </div>
            <div class="profile-header-content">
                <h1>Mon Profil</h1>
            </div>
        <?php endif; ?>
    </div>

    <!-- Navigation -->
    <div class="profile-nav">
        <ul class="nav nav-tabs" id="profileTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= empty($_GET['tab']) || $_GET['tab'] === 'personal' ? 'active' : '' ?>" 
                   id="personal-tab" 
                   data-bs-toggle="tab" 
                   href="#personal" 
                   role="tab">
                    <i class="bi bi-person-fill"></i> Informations personnelles
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= isset($_GET['tab']) && $_GET['tab'] === 'organizer' ? 'active' : '' ?>" 
                   id="organizer-tab" 
                   data-bs-toggle="tab" 
                   href="#organizer" 
                   role="tab">
                    <i class="bi bi-building"></i> Profils Organisateur
                </a>
            </li>
        </ul>
    </div>

    <!-- Contenu des onglets -->
    <div class="tab-content" id="profileTabsContent">
        <!-- Informations personnelles -->
        <div class="tab-pane fade <?= empty($_GET['tab']) || $_GET['tab'] === 'personal' ? 'show active' : '' ?>" 
             id="personal" 
             role="tabpanel">
            <div class="profile-section">
                <h2>Informations personnelles</h2>
                <?php if (isset($_SESSION['profile_message'])): ?>
                    <div class="alert alert-<?= $_SESSION['profile_message_type'] ?? 'info' ?>">
                        <?= $_SESSION['profile_message'] ?>
                    </div>
                    <?php 
                    unset($_SESSION['profile_message']);
                    unset($_SESSION['profile_message_type']);
                    ?>
                <?php endif; ?>

                <form id="profileForm" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name" class="form-label">Nom d'utilisateur</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                    </div>
                </form>

                <!-- Section changement de mot de passe -->
                <div class="mt-5">
                    <h3>Changer le mot de passe</h3>
                    <p class="text-muted">Un email de confirmation vous sera envoyé pour valider le changement.</p>
                    <form id="passwordForm" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="currentPassword" class="form-label">Mot de passe actuel</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="currentPassword" name="currentPassword" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="currentPassword">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="newPassword" class="form-label">Nouveau mot de passe</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="newPassword" name="newPassword" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="newPassword">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <small class="form-text text-muted">Minimum 8 caractères</small>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-warning">Demander le changement de mot de passe</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Profils Organisateur -->
        <div class="tab-pane fade <?= isset($_GET['tab']) && $_GET['tab'] === 'organizer' ? 'show active' : '' ?>" 
             id="organizer" 
             role="tabpanel">
            <div class="profile-section">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Profils Organisateur</h2>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#organizerModal">
                        <i class="bi bi-plus-circle"></i> Nouveau profil
                    </button>
                </div>

                <!-- Liste des profils -->
                <div class="organizer-profiles">
                    <?php
                    if (empty($profiles)): ?>
                        <div class="alert alert-info">
                            Vous n'avez pas encore de profil organisateur. Créez-en un pour pouvoir organiser des événements.
                        </div>
                    <?php else: ?>
                        <?php foreach ($profiles as $profile): ?>
                            <div class="card mb-3" data-profile-id="<?= $profile['id'] ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="d-flex align-items-center">
                                            <div class="profile-logo-container me-3">
                                                <img src="<?= !empty($profile['logo_path']) ? htmlspecialchars($profile['logo_path']) : '/assets/images/events/default-event.jpg' ?>" 
                                                     alt="Logo <?= htmlspecialchars($profile['name']) ?>" 
                                                     class="profile-logo">
                                            </div>
                                            <div>
                                                <h3 class="card-title mb-0"><?= htmlspecialchars($profile['name']) ?></h3>
                                                <small class="text-muted">Créé le <?= (new DateTime($profile['created_at']))->format('d/m/Y') ?></small>
                                            </div>
                                        </div>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary edit-profile" 
                                                    data-profile-id="<?= $profile['id'] ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-profile" 
                                                    data-profile-id="<?= $profile['id'] ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php if (!empty($profile['description'])): ?>
                                        <p class="card-text"><?= nl2br(htmlspecialchars($profile['description'])) ?></p>
                                    <?php endif; ?>
                                    <div class="profile-details">
                                        <?php if (!empty($profile['email'])): ?>
                                            <p><i class="bi bi-envelope"></i> <?= htmlspecialchars($profile['email']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($profile['phone'])): ?>
                                            <p><i class="bi bi-telephone"></i> <?= htmlspecialchars($profile['phone']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($profile['website'])): ?>
                                            <p><i class="bi bi-globe"></i> <a href="<?= htmlspecialchars($profile['website']) ?>" target="_blank"><?= htmlspecialchars($profile['website']) ?></a></p>
                                        <?php endif; ?>
                                        <?php if (!empty($profile['address'])): ?>
                                            <p><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($profile['address']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour le profil organisateur -->
<div class="modal fade" id="organizerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="organizerProfileForm" class="needs-validation" novalidate enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Profil Organisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="profile_id" name="profile_id">
                    
                    <div class="mb-3">
                        <label for="org_logo" class="form-label">Logo de l'organisation</label>
                        <div class="logo-preview-container mb-2" style="display: none;">
                            <div class="image-container">
                                <img id="logo_preview" src="" alt="Aperçu du logo">
                                <button type="button" class="btn-remove" id="remove_logo" title="Supprimer le logo">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </div>
                        </div>
                        <input type="file" class="form-control" id="org_logo" name="logo" accept="image/*">
                        <div class="form-text">Format accepté : JPG, PNG, GIF (max 5MB)</div>
                    </div>

                    <div class="mb-3">
                        <label for="org_name" class="form-label">Nom de l'organisation *</label>
                        <input type="text" class="form-control" id="org_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="org_description" class="form-label">Description</label>
                        <textarea class="form-control" id="org_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="org_email" class="form-label">Email de contact</label>
                                <input type="email" class="form-control" id="org_email" name="email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="org_phone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="org_phone" name="phone">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="org_website" class="form-label">Site web</label>
                                <input type="url" class="form-control" id="org_website" name="website">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="org_address" class="form-label">Adresse</label>
                                <input type="text" class="form-control" id="org_address" name="address">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
