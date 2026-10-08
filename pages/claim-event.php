<?php
/**
 * localisation: pages/claim-event.php
 * Role: Page publique de revendication d'événement — l'organisateur clique sur le
 *       lien tokenisé reçu par email pour reprendre la main sur son événement.
 * Usage: GET  /pages/claim-event.php?token=X  → affiche l'événement + actions
 *        POST /pages/claim-event.php          → effectue la revendication (login requis)
 * Dépendances: includes/config.php (PDO), logs/error.log.php,
 *              templates/components/header|footer.
 * Sécurité: token hex 64 chars, comparaison hash_equals, token invalidé après usage.
 */

$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/includes/config.php';
require_once $rootPath . '/logs/error.log.php';
require_once $rootPath . '/templates/components/header/header.php';
require_once $rootPath . '/templates/components/footer/footer.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$token   = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$event   = null;
$state   = 'invalid';   // invalid | claimable | done | error
$message = '';

// Charge l'événement associé au token (+ email du contact destinataire du lien)
if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = $pdo->prepare(
        'SELECT e.id, e.title, e.date, e.start_time, e.location, e.venue, e.user_id, e.organizer_id,
                (SELECT ec.email FROM event_contacts ec
                  WHERE ec.event_id = e.id AND ec.email IS NOT NULL AND ec.email <> ""
                  ORDER BY ec.id LIMIT 1) AS contact_email
         FROM events e WHERE e.claim_token = ?'
    );
    $stmt->execute([$token]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($event) {
        $state = 'claimable';
    }
}

// Email du compte actuellement connecté (pour vérifier la correspondance)
$userEmail = null;
if ($event && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $userEmail = $stmt->fetchColumn() ?: null;
}

// Le lien est destiné au contact_email de l'événement : le compte connecté doit correspondre.
// Si l'événement n'a pas de contact_email, tout compte connecté peut revendiquer (token secret = preuve).
$emailMismatch = $event !== null && !empty($event['contact_email']) && $userEmail !== null
    && strcasecmp($userEmail, $event['contact_email']) !== 0;

// Aiguillage auth : le compte destinataire existe-t-il déjà ?
// → oui : modal Connexion ; non : modal Créer un compte.
$contactHasAccount = false;
if ($event && !empty($event['contact_email'])) {
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$event['contact_email']]);
    $contactHasAccount = (bool)$stmt->fetchColumn();
}

// Le claimer possède-t-il déjà un profil organisateur ? (pour le CTA post-claim)
$hasOrganizerProfile = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT 1 FROM organizer_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $hasOrganizerProfile = (bool)$stmt->fetchColumn();
}

// POST : l'utilisateur connecté revendique l'événement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $state === 'claimable') {
    if (!isset($_SESSION['user_id'])) {
        $state = 'error';
        $message = 'Vous devez être connecté pour revendiquer cet événement.';
    } elseif ($emailMismatch) {
        // Connecté avec un autre compte que celui destinataire du lien → refus
        $state = 'error';
        $message = 'Vous êtes connecté avec le compte ' . $userEmail . ', mais ce lien est destiné au compte '
                 . $event['contact_email'] . '. Déconnectez-vous puis reconnectez-vous avec le bon compte.';
        logError('pages/claim-event.php', 'Claim refusé : compte connecté ≠ destinataire', [
            'event_id' => $event['id'],
            'session_email' => $userEmail,
            'contact_email' => $event['contact_email'],
        ]);
    } else {
        // Transfert de propriété + invalidation du token (atomique)
        $upd = $pdo->prepare('UPDATE events SET user_id = ?, claim_token = NULL WHERE id = ? AND claim_token = ?');
        $upd->execute([$_SESSION['user_id'], $event['id'], $token]);

        if ($upd->rowCount() === 1) {
            // Rattachement du profil organisateur (non bloquant)
            try {
                $organizerId   = (int)($event['organizer_id'] ?? 0);
                $attachProfile = false;

                if ($organizerId === 0) {
                    $attachProfile = true; // aucun profil lié à l'événement
                } else {
                    $op = $pdo->prepare('SELECT user_id FROM organizer_profiles WHERE id = ?');
                    $op->execute([$organizerId]);
                    $ownerId = $op->fetchColumn();

                    if ($ownerId === false) {
                        // Profil inexistant → détacher
                        $pdo->prepare('UPDATE events SET organizer_id = NULL WHERE id = ?')
                            ->execute([$event['id']]);
                        $attachProfile = true;
                    } elseif ($ownerId === null) {
                        // Profil orphelin = celui du vrai organisateur → le claimer l'adopte
                        $pdo->prepare('UPDATE organizer_profiles SET user_id = ? WHERE id = ?')
                            ->execute([$_SESSION['user_id'], $organizerId]);
                    } elseif ((int)$ownerId !== (int)$_SESSION['user_id']) {
                        // Profil appartenant à un autre compte (ex. admin) → détacher sans le voler
                        $pdo->prepare('UPDATE events SET organizer_id = NULL WHERE id = ?')
                            ->execute([$event['id']]);
                        $attachProfile = true;
                    }
                    // ownerId == claimer : déjà lié à son propre profil, rien à faire
                }

                // Plus aucun profil lié → lier le 1er profil du claimer s'il en possède un.
                // S'il n'en a pas, organizer_id reste NULL : l'affichage retombe sur
                // 'organisation' puis sur les infos du compte (EventDisplayBuilder::fetchOrganizer).
                if ($attachProfile) {
                    $p = $pdo->prepare('SELECT id FROM organizer_profiles WHERE user_id = ? ORDER BY id ASC LIMIT 1');
                    $p->execute([$_SESSION['user_id']]);
                    $pid = $p->fetchColumn();
                    if ($pid) {
                        $pdo->prepare('UPDATE events SET organizer_id = ?, organisation = NULL WHERE id = ?')
                            ->execute([(int)$pid, $event['id']]);
                    }
                }
            } catch (Throwable $e) {
                logError('pages/claim-event.php', 'Liaison organizer_profiles échouée (non bloquant)', [
                    'event_id' => $event['id'], 'error' => $e->getMessage(),
                ]);
            }
            $state = 'done';
        } else {
            $state = 'error';
            $message = 'Ce lien a déjà été utilisé ou a expiré.';
            logError('pages/claim-event.php', 'Claim token invalide ou déjà utilisé', [
                'event_id' => $event['id'], 'user_id' => $_SESSION['user_id'],
            ]);
        }
    }
}

$pageTitle = 'Revendiquer un événement';
$additionalStyles = '<link rel="stylesheet" href="/assets/css/pages/claim-event.css">';
render_header();

// Arrière-plan : la vraie home page (hero, filtres, calendrier, carte, événements)
// — la modal de revendication s'ouvre par-dessus via claim-event.js.
require $rootPath . '/templates/home.php';
?>

<?php
// Modal de revendication — rendue pour TOUS les états, ouverte auto par claim-event.js.
// Succès (done) : backdrop statique, pas de croix → l'utilisateur choisit une action.
$isDone      = ($state === 'done');
$isClaimable = ($state === 'claimable');
$reportMail  = 'mailto:rando@partageonslaforet.be?subject='
             . ($event ? rawurlencode('Signalement événement #' . $event['id'] . ' — ' . $event['title']) : 'Signalement%20revendication');
?>
<div class="modal fade" id="claimResultModal" tabindex="-1"<?= $isDone ? ' data-bs-backdrop="static" data-bs-keyboard="false"' : '' ?> aria-labelledby="claimResultTitle">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="claimResultTitle">
          <?php if ($isDone): ?>
            <i class="bi bi-check-circle-fill text-success"></i> Événement revendiqué
          <?php elseif ($isClaimable): ?>
            Revendiquer cet événement
          <?php elseif ($state === 'invalid'): ?>
            <i class="bi bi-x-circle-fill text-danger"></i> Lien invalide
          <?php else: ?>
            <i class="bi bi-x-circle-fill text-danger"></i> Revendication impossible
          <?php endif; ?>
        </h5>
        <?php if (!$isDone): ?>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
        <?php endif; ?>
      </div>
      <div class="modal-body">
        <?php if ($event): ?>
          <div class="border rounded p-3 mb-3 bg-light">
            <div class="fw-bold"><?= htmlspecialchars($event['title']) ?></div>
            <div class="text-muted small">
              📅 <?= date('d/m/Y', strtotime($event['date'])) ?>
              <?php if (!empty($event['start_time'])): ?> à <?= substr($event['start_time'], 0, 5) ?><?php endif; ?>
              <?php if (!empty($event['location'])): ?> · 📍 <?= htmlspecialchars($event['location']) ?><?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($isDone): ?>
          <p class="mb-0">L'événement <strong><?= htmlspecialchars($event['title']) ?></strong> est maintenant
             rattaché à votre compte. Vous pouvez le modifier et suivre ses vues depuis votre espace.</p>
          <?php if (!$hasOrganizerProfile): ?>
            <p class="text-muted small mt-2 mb-0">Pour afficher le nom et le logo de votre club ou association
               sur cet événement, créez votre <strong>profil organisateur</strong> en 1 minute.</p>
          <?php endif; ?>
        <?php elseif ($isClaimable && isset($_SESSION['user_id'])): ?>
          <?php if ($emailMismatch): ?>
            <p>Vous êtes connecté avec le compte <strong><?= htmlspecialchars($userEmail) ?></strong>,
               mais ce lien de revendication est destiné au compte
               <strong><?= htmlspecialchars($event['contact_email']) ?></strong>.</p>
            <p class="text-muted small mb-0">Déconnectez-vous puis reconnectez-vous (ou créez un compte)
               avec cette adresse pour revendiquer cet événement.</p>
          <?php else: ?>
            <p class="mb-1">En confirmant, cet événement sera rattaché à votre compte
               (<strong><?= htmlspecialchars($userEmail ?? '') ?></strong>) et vous pourrez
               le gérer vous-même.</p>
            <p class="text-muted small mb-0">Ce n'est pas votre compte ? Déconnectez-vous d'abord.</p>
          <?php endif; ?>
        <?php elseif ($isClaimable): ?>
          <?php if ($contactHasAccount): ?>
            <p class="mb-1">Un compte existe déjà pour <strong><?= htmlspecialchars($event['contact_email']) ?></strong>.
               Connectez-vous pour revendiquer cet événement.</p>
          <?php else: ?>
            <p class="mb-1">Aucun compte n'existe encore pour
               <strong><?= htmlspecialchars($event['contact_email'] ?? '') ?></strong>.
               Créez-le gratuitement pour revendiquer cet événement.</p>
          <?php endif; ?>
          <p class="text-muted small mb-0">Après connexion, revenez sur le lien reçu par email.</p>
        <?php else: ?>
          <p class="mb-0"><?= htmlspecialchars($message !== '' ? $message : 'Ce lien de revendication est invalide, a déjà été utilisé ou a expiré.') ?></p>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <?php if ($isDone): ?>
          <a href="/event/<?= (int)$event['id'] ?>" class="btn btn-claim-secondary">Voir l'événement</a>
          <?php if (!$hasOrganizerProfile): ?>
            <a href="/pages/user/profile.php?tab=organizer&new=1" class="btn btn-claim-primary">
              <i class="bi bi-building-add"></i> Créer mon profil organisateur
            </a>
          <?php else: ?>
            <a href="/pages/user/my-events.php" class="btn btn-claim-primary">Voir mes événements</a>
          <?php endif; ?>
        <?php elseif ($isClaimable && isset($_SESSION['user_id'])): ?>
          <a href="<?= $reportMail ?>" class="btn-claim-link">Signaler une erreur</a>
          <?php if ($emailMismatch): ?>
            <button type="button" class="btn btn-claim-secondary" data-claim-logout>Changer de compte</button>
          <?php else: ?>
            <form method="post" class="m-0">
              <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
              <button type="submit" class="btn btn-claim-primary">C'est mon événement</button>
            </form>
          <?php endif; ?>
        <?php elseif ($isClaimable): ?>
          <a href="<?= $reportMail ?>" class="btn-claim-link">Signaler une erreur</a>
          <button type="button" class="btn btn-claim-primary" data-claim-auth
                  data-auth-target="<?= $contactHasAccount ? 'login' : 'register' ?>"
                  data-auth-email="<?= htmlspecialchars($event['contact_email'] ?? '') ?>">
            <?= $contactHasAccount ? 'Se connecter' : 'Créer un compte gratuit' ?>
          </button>
        <?php else: ?>
          <a href="<?= $reportMail ?>" class="btn-claim-link">Signaler une erreur</a>
          <button type="button" class="btn btn-claim-secondary" data-bs-dismiss="modal">Fermer</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Bundle Bootstrap requis pour la modal (non chargé par le composant header) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- contact.js : expose la fonction globale showToast() utilisée par auth.js (sinon fallback alert() natif) -->
<script src="/assets/js/core/contact.js"></script>
<!-- auth.js : soumission AJAX des formulaires #loginModal / #registerModal (inclus via layouts/modals.php dans le header) -->
<script src="/assets/js/core/auth.js"></script>
<script src="/assets/js/pages/claim-event.js"></script>

<?php render_footer(); ?>
</body>
</html>
