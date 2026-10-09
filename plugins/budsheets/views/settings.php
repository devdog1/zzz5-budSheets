<?php
/**
 * BudSheets Settings & Class Management View
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

if (!has_permission('budsheets_admin')) {
    die('Access Denied: Admin permissions required.');
}

$message = '';
$error = '';
$lobs = budsheets_get_lobs();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('validate_csrf')) {
        validate_csrf();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_class') {
        $lob_id = (int)($_POST['lob_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!$lob_id || empty($name)) {
            $error = 'Line of Business and Class Name are required fields.';
        } else {
            if (budsheets_add_class($lob_id, $name, $description)) {
                $message = 'Expense Class created successfully.';
            } else {
                $error = 'Failed to create Expense Class.';
            }
        }
    } elseif ($action === 'update_class') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $lob_id = (int)($_POST['lob_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!$class_id || !$lob_id || empty($name)) {
            $error = 'Invalid input for updating Expense Class.';
        } else {
            if (budsheets_update_class($class_id, $lob_id, $name, $description)) {
                $message = 'Expense Class updated successfully.';
            } else {
                $error = 'Failed to update Expense Class.';
            }
        }
    } elseif ($action === 'delete_class') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        if ($class_id) {
            if (budsheets_delete_class($class_id)) {
                $message = 'Expense Class deleted successfully.';
            } else {
                $error = 'Failed to delete Expense Class.';
            }
        }
    }
}

$classes = budsheets_get_all_classes();
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-gear me-2 text-primary"></i>Plugin Settings & Expense Classes</h2>
            <p class="text-muted small mb-0">Configure operational budget expense classes and assign them to specific Lines of Business.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
            <i class="fa-solid fa-plus me-1"></i> Add Expense Class
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

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-tags me-2 text-secondary"></i>Configured Expense Classes by Line of Business</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">ID</th>
                            <th>Class Name</th>
                            <th>Assigned Line of Business</th>
                            <th>Description</th>
                            <th>Created At</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($classes)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No expense classes configured yet. Click "Add Expense Class" to create one.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($classes as $cls): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-muted">#<?= (int)$cls['id'] ?></td>
                                    <td class="fw-bold text-dark"><?= e($cls['name']) ?></td>
                                    <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e($cls['lob_name']) ?></span></td>
                                    <td class="text-muted small"><?= e($cls['description'] ?: '—') ?></td>
                                    <td class="text-muted small"><?= e($cls['created_at']) ?></td>
                                    <td class="text-end pe-3">
                                        <button type="button" class="btn btn-sm btn-outline-secondary me-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editClassModal<?= (int)$cls['id'] ?>">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this Expense Class?');">
                                            <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_class">
                                            <input type="hidden" name="class_id" value="<?= (int)$cls['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Edit Class Modal -->
                                <div class="modal fade" id="editClassModal<?= (int)$cls['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="update_class">
                                                <input type="hidden" name="class_id" value="<?= (int)$cls['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Expense Class</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Assigned Line of Business <span class="text-danger">*</span></label>
                                                        <select name="lob_id" class="form-select" required>
                                                            <option value="">-- Select Line of Business --</option>
                                                            <?php foreach ($lobs as $lob): ?>
                                                                <option value="<?= (int)$lob['id'] ?>" <?= $cls['lob_id'] == $lob['id'] ? 'selected' : '' ?>><?= e($lob['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Class Name <span class="text-danger">*</span></label>
                                                        <input type="text" name="name" class="form-control" value="<?= e($cls['name']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Description</label>
                                                        <textarea name="description" class="form-control" rows="2"><?= e($cls['description']) ?></textarea>
                                                    </div>
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

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                <input type="hidden" name="action" value="create_class">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-tags me-2"></i>Add Expense Class</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Assigned Line of Business <span class="text-danger">*</span></label>
                        <select name="lob_id" class="form-select" required>
                            <option value="">-- Select Line of Business --</option>
                            <?php foreach ($lobs as $lob): ?>
                                <option value="<?= (int)$lob['id'] ?>"><?= e($lob['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Class Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Software Licenses, Hardware, Professional Services" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief description of expenses covered by this class..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Expense Class</button>
                </div>
            </form>
        </div>
    </div>
</div>
