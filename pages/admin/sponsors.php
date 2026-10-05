<?php
/**
 * Gestion des sponsors (pub) — card sous Galerie (page événement) + footer accueil.
 * CRUD : ajout (upload image OU URL externe), activer/désactiver, ordre, suppression.
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Accès réservé admin
if (empty($_SESSION['user_id'])) {
    header('Location: /?showLogin=1&redirect=' . urlencode('/pages/admin/sponsors.php'));
    exit;
}
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: /?error=admin_required');
    exit;
}

const UPLOAD_DIR = '/assets/images/sponsors/';
const MAX_UPLOAD_BYTES = 2 * 1024 * 1024; // 2 Mo
const ALLOWED_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

$flash = ['type' => '', 'text' => ''];

/** Normalise/valide une URL externe http(s). Retourne l'URL ou ''. */
function validHttpUrl(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    if (!filter_var($url, FILTER_VALIDATE_URL)) return '';
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : '';
}

/** Traite l'upload du champ 'image'. Retourne le chemin public ou ''. */
function handleImageUpload(): string {
    if (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    $f = $_FILES['image'];
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Image invalide ou trop lourde (max 2 Mo).');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset(ALLOWED_MIME[$mime])) {
        throw new RuntimeException('Format non supporté (jpg, png, webp, gif).');
    }
    $dir = dirname(__DIR__, 2) . '/public' . UPLOAD_DIR;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Impossible de créer le dossier des visuels.');
    }
    $name = 'sponsor_' . bin2hex(random_bytes(8)) . '.' . ALLOWED_MIME[$mime];
    if (!move_uploaded_file($f['tmp_name'], $dir . $name)) {
        throw new RuntimeException("Échec de l'enregistrement de l'image.");
    }
    return UPLOAD_DIR . $name;
}

// ------------------------------ actions POST (PRG) ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $flash = ['type' => 'danger', 'text' => 'Jeton de sécurité invalide.'];
    } else {
        $action = $_POST['action'] ?? '';
        try {
            switch ($action) {
                case 'add':
                    $name = trim((string) ($_POST['name'] ?? ''));
                    if ($name === '') {
                        throw new RuntimeException('Le nom du partenaire est requis.');
                    }
                    $imagePath = handleImageUpload();
                    if ($imagePath === '') {
                        $imagePath = validHttpUrl((string) ($_POST['image_url'] ?? ''));
                    }
                    if ($imagePath === '') {
                        throw new RuntimeException('Fournissez un fichier image ou une URL d\'image valide.');
                    }
                    $linkUrl = validHttpUrl((string) ($_POST['link_url'] ?? ''));
                    $alt = trim((string) ($_POST['alt_text'] ?? ''));
                    $position = (int) ($_POST['position'] ?? 0);
                    $db->prepare(
                        'INSERT INTO sponsors (name, image_path, link_url, alt_text, position, active)
                         VALUES (?, ?, ?, ?, ?, 1)'
                    )->execute([$name, $imagePath, $linkUrl !== '' ? $linkUrl : null, $alt !== '' ? $alt : null, $position]);
                    $flash = ['type' => 'success', 'text' => 'Sponsor ajouté.'];
                    break;

                case 'toggle':
                    $db->prepare('UPDATE sponsors SET active = 1 - active WHERE id = ?')
                       ->execute([(int) ($_POST['id'] ?? 0)]);
                    $flash = ['type' => 'success', 'text' => 'Statut mis à jour.'];
                    break;

                case 'update':
                    $id = (int) ($_POST['id'] ?? 0);
                    $name = trim((string) ($_POST['name'] ?? ''));
                    if ($name === '') {
                        throw new RuntimeException('Le nom du partenaire est requis.');
                    }
                    $linkUrl = validHttpUrl((string) ($_POST['link_url'] ?? ''));
                    $alt = trim((string) ($_POST['alt_text'] ?? ''));
                    $position = (int) ($_POST['position'] ?? 0);
                    $db->prepare('UPDATE sponsors SET name = ?, link_url = ?, alt_text = ?, position = ? WHERE id = ?')
                       ->execute([$name, $linkUrl !== '' ? $linkUrl : null, $alt !== '' ? $alt : null, $position, $id]);
                    $flash = ['type' => 'success', 'text' => 'Sponsor mis à jour.'];
                    break;

                case 'delete':
                    $id = (int) ($_POST['id'] ?? 0);
                    $row = $db->prepare('SELECT image_path FROM sponsors WHERE id = ?');
                    $row->execute([$id]);
                    $sponsor = $row->fetch();
                    $db->prepare('DELETE FROM sponsors WHERE id = ?')->execute([$id]);
                    // Nettoie le fichier local (pas les URL externes)
                    if ($sponsor && strpos($sponsor['image_path'], UPLOAD_DIR) === 0) {
                        $file = dirname(__DIR__, 2) . '/public' . $sponsor['image_path'];
                        if (is_file($file)) {
                            @unlink($file);
                        }
                    }
                    $flash = ['type' => 'success', 'text' => 'Sponsor supprimé.'];
                    break;

                default:
                    throw new RuntimeException('Action inconnue.');
            }
        } catch (Throwable $e) {
            logError(__FILE__, 'sponsors action failed', ['action' => $action, 'error' => $e->getMessage()]);
            $flash = ['type' => 'danger', 'text' => $e->getMessage()];
        }
    }
    $_SESSION['flash_sponsors'] = $flash;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Flash suite à redirection
if (!empty($_SESSION['flash_sponsors'])) {
    $flash = $_SESSION['flash_sponsors'];
    unset($_SESSION['flash_sponsors']);
}

$sponsors = $db->query('SELECT * FROM sponsors ORDER BY position ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Administration - Sponsors';
$additionalStyles = '<link rel="stylesheet" href="/assets/css/pages/admin/dashboard.css">';
include __DIR__ . '/../../templates/layouts/header-solid.php';
?>

<div class="container mt-5 pt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-megaphone"></i> Gestion des sponsors</h1>
        <a href="/pages/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">← Tableau de bord</a>
    </div>

    <?php if ($flash['text'] !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['text']) ?></div>
    <?php endif; ?>

    <!-- Formulaire d'ajout -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title"><i class="bi bi-plus-circle"></i> Ajouter un partenaire</h5>
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add">
                <div class="col-md-4">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="name" class="form-control" required maxlength="255">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Image (fichier, max 2 Mo)</label>
                    <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                </div>
                <div class="col-md-4">
                    <label class="form-label">ou URL de l'image</label>
                    <input type="url" name="image_url" class="form-control" placeholder="https://…">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Lien du site partenaire</label>
                    <input type="url" name="link_url" class="form-control" placeholder="https://…">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Texte alternatif</label>
                    <input type="text" name="alt_text" class="form-control" maxlength="255">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Ordre</label>
                    <input type="number" name="position" class="form-control" value="0">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste -->
    <div class="table-responsive">
        <table class="table table-hover align-middle sponsors-table">
            <thead>
                <tr>
                    <th class="col-visual">Visuel</th>
                    <th>Nom / Lien / Alt</th>
                    <th class="col-order">Ordre</th>
                    <th class="col-active">Actif</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sponsors as $s): ?>
                <tr>
                    <td>
                        <img src="<?= htmlspecialchars($s['image_path']) ?>" alt=""
                             class="sponsor-thumb">
                    </td>
                    <td>
                        <form method="post" class="row g-2 align-items-center sponsor-edit" id="edit-<?= (int) $s['id'] ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                            <div class="col-md-4">
                                <input type="text" name="name" class="form-control form-control-sm"
                                       value="<?= htmlspecialchars($s['name']) ?>" required maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <input type="url" name="link_url" class="form-control form-control-sm"
                                       value="<?= htmlspecialchars($s['link_url'] ?? '') ?>" placeholder="Lien https://…">
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="alt_text" class="form-control form-control-sm"
                                       value="<?= htmlspecialchars($s['alt_text'] ?? '') ?>" placeholder="Texte alternatif" maxlength="255">
                            </div>
                        </form>
                    </td>
                    <td>
                        <input type="number" name="position" form="edit-<?= (int) $s['id'] ?>"
                               class="form-control form-control-sm position-input"
                               value="<?= (int) $s['position'] ?>">
                    </td>
                    <td>
                        <form method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $s['active'] ? 'btn-success' : 'btn-outline-secondary' ?>"
                                    title="<?= $s['active'] ? 'Visible — cliquer pour masquer' : 'Masqué — cliquer pour afficher' ?>">
                                <i class="bi <?= $s['active'] ? 'bi-eye' : 'bi-eye-slash' ?>"></i>
                            </button>
                        </form>
                    </td>
                    <td class="text-end">
                        <button type="submit" form="edit-<?= (int) $s['id'] ?>" class="btn btn-sm btn-primary me-1" title="Enregistrer">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <form method="post" class="d-inline" onsubmit="return confirm('Supprimer ce sponsor ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Supprimer">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($sponsors)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Aucun sponsor — la card ne s'affiche nulle part.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../templates/layouts/footer.php'; ?>
