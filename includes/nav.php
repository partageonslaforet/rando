<?php
// Démarrer la session si elle n'est pas déjà démarrée
require_once __DIR__ . '/init.php';

// Debug log
error_log("Session dans nav.php: " . print_r($_SESSION, true));

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="/">Partageons La Forêt</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>" href="/">
                        Événements
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'create.php' ? 'active' : ''; ?>" href="/create.php">
                        Créer un événement
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php error_log("Utilisateur connecté - ID: " . $_SESSION['user_id']); ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            Mon compte
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="/pages/user/profile.php">
                                <i class="bi bi-person-fill"></i> Mon Profil
                            </a>
                            <a class="dropdown-item" href="/pages/user/my-events.php">
                                <i class="bi bi-calendar-event-fill"></i> Mes Événements
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#" onclick="logout(); return false;">
                                <i class="bi bi-box-arrow-right"></i> Déconnexion
                            </a>
                        </div>
                    </li>
                <?php else: ?>
                    <?php error_log("Utilisateur non connecté"); ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#loginModal">
                            Connexion
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#registerModal">
                            Inscription
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
