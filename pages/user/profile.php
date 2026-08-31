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

    // Prénom et initiales pour l'en-tête
    $nameParts = explode(' ', trim($user['name'] ?? ''));
    $firstName = !empty($nameParts[0]) ? $nameParts[0] : 'Vous';
    $initials = strtoupper(substr($nameParts[0], 0, 1));
    if (isset($nameParts[1])) {
        $initials .= strtoupper(substr($nameParts[1], 0, 1));
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

    <?php
    $allowedTabs = ['personal', 'organizer', 'password'];
    $activeTab = in_array($_GET['tab'] ?? 'personal', $allowedTabs) ? ($_GET['tab'] ?? 'personal') : 'personal';
    ?>

    <main class="profile-container" role="main">
        <nav class="profile-breadcrumb" aria-label="Fil d'Ariane">
            <ol>
                <li><a href="/pages/user/profile.php">Mon compte</a></li>
                <li aria-current="page">Profil</li>
            </ol>
        </nav>

        <!-- En-tête de profil -->
        <header class="profile-hero">
            <div class="profile-avatar" aria-hidden="true">
                <?= htmlspecialchars($initials) ?>
            </div>
            <div class="profile-hero-text">
                <h1>Bonjour, <?= htmlspecialchars($firstName) ?></h1>
                <p>Gérez vos informations et vos activités sur Partageons la Forêt.</p>
            </div>
        </header>

        <div class="profile-layout">
            <!-- Menu du compte -->
            <aside class="profile-sidebar" aria-label="Menu du compte">
                <h2 class="profile-sidebar-title">Mon compte</h2>
                <nav class="profile-menu" role="tablist">
                    <a class="profile-menu-item <?= $activeTab === 'personal' ? 'active' : '' ?>" href="?tab=personal" <?= $activeTab === 'personal' ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-person-fill" aria-hidden="true"></i>
                        <span>Informations</span>
                    </a>
                    <a class="profile-menu-item <?= $activeTab === 'organizer' ? 'active' : '' ?>" href="?tab=organizer" <?= $activeTab === 'organizer' ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-building" aria-hidden="true"></i>
                        <span>Organisateur</span>
                    </a>
                    <a class="profile-menu-item <?= $activeTab === 'password' ? 'active' : '' ?>" href="?tab=password" <?= $activeTab === 'password' ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                        <span>Sécurité</span>
                    </a>
                </nav>
            </aside>

            <!-- Panneau principal -->
            <div class="profile-main">
                <!-- Informations personnelles -->
                <section class="profile-section <?= $activeTab === 'personal' ? 'active' : '' ?>" id="personal" aria-labelledby="personal-heading">
                    <header class="section-header">
                        <h2 id="personal-heading">Informations personnelles</h2>
                        <p>Mettez à jour les informations visibles dans votre compte.</p>
                    </header>

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
                                    <label for="email" class="form-label">Adresse e-mail</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                            </div>
                        </div>

                        <p class="form-note">Votre adresse e-mail est utilisée pour les confirmations et les notifications.</p>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                        </div>
                    </form>
                </section>

                <!-- Profil organisateur -->
                <section class="profile-section <?= $activeTab === 'organizer' ? 'active' : '' ?>" id="organizer" aria-labelledby="organizer-heading">
                    <header class="section-header">
                        <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-center gap-2">
                            <div>
                                <h2 id="organizer-heading">Organisateur</h2>
                                <p>Gérez les profils que vous présentez aux participants.</p>
                            </div>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#organizerModal">
                                <i class="bi bi-plus-circle" aria-hidden="true"></i> Nouveau profil
                            </button>
                        </div>
                    </header>

                    <div class="organizer-profiles">
                        <?php if (empty($profiles)): ?>
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
                                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-profile"
                                                        data-profile-id="<?= $profile['id'] ?>">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php if (!empty($profile['description'])): ?>
                                            <p class="card-text"><?= nl2br(htmlspecialchars($profile['description'])) ?></p>
                                        <?php endif; ?>
                                        <div class="profile-details">
                                            <?php if (!empty($profile['email'])): ?>
                                                <p><i class="bi bi-envelope" aria-hidden="true"></i> <?= htmlspecialchars($profile['email']) ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['phone'])): ?>
                                                <p><i class="bi bi-telephone" aria-hidden="true"></i> <?= htmlspecialchars($profile['phone']) ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['website'])): ?>
                                                <p><i class="bi bi-globe" aria-hidden="true"></i> <a href="<?= htmlspecialchars($profile['website']) ?>" target="_blank"><?= htmlspecialchars($profile['website']) ?></a></p>
                                            <?php endif; ?>
                                            <?php if (!empty($profile['address'])): ?>
                                                <p><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= htmlspecialchars($profile['address']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Sécurité / Changer le mot de passe -->
                <section class="profile-section <?= $activeTab === 'password' ? 'active' : '' ?>" id="password" aria-labelledby="password-heading">
                    <header class="section-header">
                        <h2 id="password-heading">Sécurité</h2>
                        <p>Modifiez votre mot de passe pour sécuriser votre compte.</p>
                    </header>

                    <form id="passwordForm" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="currentPassword" class="form-label">Mot de passe actuel</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="currentPassword" name="currentPassword" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="currentPassword" aria-label="Afficher le mot de passe">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="newPassword" class="form-label">Nouveau mot de passe</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="newPassword" name="newPassword" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="newPassword" aria-label="Afficher le mot de passe">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <small class="form-text text-muted">Minimum 8 caractères</small>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Demander le changement de mot de passe</button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </main>

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

<script src="/assets/js/profile.js"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
