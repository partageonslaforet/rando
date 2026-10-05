<?php
$rootPath = realpath(__DIR__ . '/../..');
require_once $rootPath . '/templates/components/header/header.php';
require_once $rootPath . '/logs/error.log.php';
if (session_status()===PHP_SESSION_NONE) session_start();
$pageTitle = "Gérer mes abonnements";
$token = $_GET['token'] ?? '';

// Sécurité: si un cache appelle encore render_footer(), neutraliser l'appel
if (!function_exists('render_footer')) {
    function render_footer() {}
}

render_header();
?>
<main class="container page-offset">
  <div class="row justify-content-center">
    <div class="col-12 col-md-9 col-lg-7 col-xl-6">
      <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="/">Accueil</a></li>
          <li class="breadcrumb-item active" aria-current="page">Gérer mes abonnements</li>
        </ol>
      </nav>
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h1 class="h4 mb-2">Gérer mes abonnements</h1>
          <p class="text-muted mb-4">Modifiez vos catégories et la fréquence d’envoi, ou désabonnez-vous.</p>
          <div id="manageError" class="alert alert-danger d-none"></div>
          <div id="manageSuccess" class="alert alert-success d-none"></div>
          <form id="manageForm">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" id="manageEmail" name="email" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label d-flex align-items-center gap-2">
                <span>Catégories</span>
                <span class="badge bg-light text-muted" id="catCount">0 sélectionnée</span>
              </label>
              <div class="d-flex align-items-center mb-2">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="catSelectAll">
                  <label class="form-check-label" for="catSelectAll">Tout cocher / décocher</label>
                </div>
              </div>
              <div id="manageCategories"></div>
              <div class="form-text">Cochez au moins une catégorie pour recevoir des notifications correspondantes.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Fréquence</label>
              <div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="notification_frequency" value="immediate" id="freq_immediate">
                  <label class="form-check-label" for="freq_immediate">Immédiat</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="notification_frequency" value="weekly" id="freq_weekly">
                  <label class="form-check-label" for="freq_weekly">Hebdomadaire</label>
                </div>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-success">
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                <span class="btn-label">Enregistrer</span>
              </button>
              <button type="button" id="btnUnsubscribe" class="btn btn-outline-danger">Se désabonner</button>
              <a href="/" class="btn btn-outline-secondary ms-auto">Retour à l’accueil</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>
<!-- Toasts de confirmation -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="prefsToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">Préférences enregistrées.</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button>
    </div>
  </div>
  <div id="unsubToast" class="toast align-items-center text-bg-success border-0 mt-2" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">Vous avez été désabonné.</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button>
    </div>
  </div>
  
</div>
<link rel="stylesheet" href="/assets/css/pages/user/subscribers-manage.css">
<script src="/assets/js/core/subscribers-manage.js"></script>
