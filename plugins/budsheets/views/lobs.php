<?php
/**
 * LOB Management View
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

if (!has_permission('budsheets_admin')) {
    die('Access Denied: Admin permissions required.');
}

$message = '';
$error = '';
$systemUsers = budsheets_get_all_system_users();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('validate_csrf') && !validate_csrf()) {
        $error = 'CSRF verification failed.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_lob') {
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $assigned_users = $_POST['assigned_users'] ?? [];

            if (empty($name)) {
                $error = 'Line of Business name is required.';
            } else {
                if (budsheets_add_lob($name, $code, $description, $assigned_users)) {
                    $message = 'Line of Business created successfully.';
                } else {
                    $error = 'Failed to create Line of Business.';
                }
            }
        } elseif ($action === 'update_lob') {
            $lob_id = (int)($_POST['lob_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $assigned_users = $_POST['assigned_users'] ?? [];

            if (!$lob_id || empty($name)) {
                $error = 'Invalid input for updating Line of Business.';
            } else {
                if (budsheets_update_lob($lob_id, $name, $code, $description, $assigned_users)) {
                    $message = 'Line of Business updated successfully.';
                } else {
                    $error = 'Failed to update Line of Business.';
                }
            }
        } elseif ($action === 'delete_lob') {
            $lob_id = (int)($_POST['lob_id'] ?? 0);
            if ($lob_id) {
                if (budsheets_delete_lob($lob_id)) {
                    $message = 'Line of Business deleted successfully.';
                } else {
                    $error = 'Failed to delete Line of Business.';
                }
            }
        }
    }
}

$lobs = budsheets_get_lobs();
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-sitemap me-2 text-primary"></i>Lines of Business</h2>
            <p class="text-muted small mb-0">Manage organizational departments and configure specific user access permissions per Line of Business.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLobModal">
            <i class="fa-solid fa-plus me-1"></i> Add Line of Business
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= e($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">ID</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Assigned Users</th>
                            <th>Created At</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lobs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No Lines of Business found. Click "Add Line of Business" to create one.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lobs as $lob):
                                $assignedUids = budsheets_get_lob_users($lob['id']);
                            ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-muted">#<?= (int)$lob['id'] ?></td>
                                    <td><span class="badge bg-secondary"><?= e($lob['code'] ?: 'N/A') ?></span></td>
                                    <td class="fw-semibold text-dark"><?= e($lob['name']) ?></td>
                                    <td class="text-muted small"><?= e($lob['description'] ?: '—') ?></td>
                                    <td>
                                        <?php if (empty($assignedUids)): ?>
                                            <span class="badge bg-light text-muted border">All Admins / Open</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                <i class="fa-solid fa-users me-1"></i><?= count($assignedUids) ?> Assigned User(s)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?= e($lob['created_at']) ?></td>
                                    <td class="text-end pe-3">
                                        <button type="button" class="btn btn-sm btn-outline-secondary me-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editLobModal<?= (int)$lob['id'] ?>">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this Line of Business? Associated items will be deleted.');">
                                            <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_lob">
                                            <input type="hidden" name="lob_id" value="<?= (int)$lob['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editLobModal<?= (int)$lob['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="update_lob">
                                                <input type="hidden" name="lob_id" value="<?= (int)$lob['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Line of Business</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Line of Business Name <span class="text-danger">*</span></label>
                                                        <input type="text" name="name" class="form-control" value="<?= e($lob['name']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Code / Abbreviation</label>
                                                        <input type="text" name="code" class="form-control" value="<?= e($lob['code']) ?>" placeholder="e.g. IT, HR, MKT">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Description</label>
                                                        <textarea name="description" class="form-control" rows="2"><?= e($lob['description']) ?></textarea>
                                                    </div>
                                                    <?php if (!empty($systemUsers)): ?>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Restrict Access to Specific Users</label>
                                                            <div class="border rounded p-2" style="max-height: 150px; overflow-y: auto;">
                                                                <?php foreach ($systemUsers as $u): ?>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="assigned_users[]" value="<?= (int)$u['id'] ?>" id="edit_u_<?= (int)$lob['id'] ?>_<?= (int)$u['id'] ?>" <?= in_array($u['id'], $assignedUids) ? 'checked' : '' ?>>
                                                                        <label class="form-check-label small" for="edit_u_<?= (int)$lob['id'] ?>_<?= (int)$u['id'] ?>">
                                                                            <?= e($u['name']) ?> (<?= e($u['email']) ?>)
                                                                        </label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <div class="form-text">Select users who are authorized to view and access this Line of Business.</div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add LOB Modal -->
<div class="modal fade" id="addLobModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                <input type="hidden" name="action" value="create_lob">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-sitemap me-2"></i>Add Line of Business</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Line of Business Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Information Technology" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Code / Abbreviation</label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. IT, HR, MKT">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief description of this business unit..."></textarea>
                    </div>
                    <?php if (!empty($systemUsers)): ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Restrict Access to Specific Users</label>
                            <div class="border rounded p-2" style="max-height: 150px; overflow-y: auto;">
                                <?php foreach ($systemUsers as $u): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="assigned_users[]" value="<?= (int)$u['id'] ?>" id="add_u_<?= (int)$u['id'] ?>">
                                        <label class="form-check-label small" for="add_u_<?= (int)$u['id'] ?>">
                                            <?= e($u['name']) ?> (<?= e($u['email']) ?>)
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text">Select users who are authorized to view and access this Line of Business.</div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Line of Business</button>
                </div>
            </form>
        </div>
    </div>
</div>
