<?php
$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/templates/components/header/header.php';
require_once $rootPath . '/templates/components/footer/footer.php';
if (session_status()===PHP_SESSION_NONE) session_start();
$pageTitle = "Gérer mes abonnements";
render_header();
$token = $_GET['token'] ?? '';
?>
<main class="container page-offset">
  <div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h1 class="h4 mb-3">Gérer mes abonnements</h1>
          <div id="manageError" class="alert alert-danger d-none"></div>
          <form id="manageForm">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" id="manageEmail" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Catégories</label>
              <div id="manageCategories"></div>
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
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-success">Enregistrer</button>
              <button type="button" id="btnUnsubscribe" class="btn btn-outline-danger">Se désabonner</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>
<?php render_footer(); ?>
<script src="/assets/js/core/subscribers-manage.js"></script>
