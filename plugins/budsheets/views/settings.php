<?php
/**
 * BudSheets Settings, Taxes, Exchange Rates & Class Management View
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

    if ($action === 'save_rates') {
        if (budsheets_save_settings($_POST)) {
            $message = 'Tax and Currency Exchange Rates updated successfully.';
        } else {
            $error = 'Failed to update rates.';
        }
    } elseif ($action === 'create_class') {
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

$settings = budsheets_get_settings();
$classes = budsheets_get_all_classes();
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-gear me-2 text-primary"></i>Plugin Settings & Configurations</h2>
            <p class="text-muted small mb-0">Manage PST/GST tax rates, currency conversion rates to CAD, and LOB expense classes.</p>
        </div>
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

    <div class="row g-4 mb-4">
        <!-- Tax Rates & Currency Conversion Box -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-percent me-2 text-primary"></i>Tax Rates & Currency Conversion to CAD</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                        <input type="hidden" name="action" value="save_rates">

                        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-receipt me-1"></i>Tax Rates (%)</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <label class="form-label fw-semibold">GST Rate (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" name="gst_rate" class="form-control" value="<?= e($settings['gst_rate']) ?>" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">PST Rate (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" name="pst_rate" class="form-control" value="<?= e($settings['pst_rate']) ?>" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>

                        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-money-bill-transfer me-1"></i>Exchange Rates (1 Foreign Unit = X CAD)</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">1 USD in CAD</label>
                                <input type="number" step="0.0001" name="rate_USD" class="form-control" value="<?= e($settings['rate_USD']) ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">1 EUR in CAD</label>
                                <input type="number" step="0.0001" name="rate_EUR" class="form-control" value="<?= e($settings['rate_EUR']) ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">1 GBP in CAD</label>
                                <input type="number" step="0.0001" name="rate_GBP" class="form-control" value="<?= e($settings['rate_GBP']) ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">1 AUD in CAD</label>
                                <input type="number" step="0.0001" name="rate_AUD" class="form-control" value="<?= e($settings['rate_AUD']) ?>" required>
                            </div>
                            <input type="hidden" name="rate_CAD" value="1.0">
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Save Rates</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Class Management Side Card -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-tags me-2 text-primary"></i>Configured Expense Classes</h5>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
                        <i class="fa-solid fa-plus me-1"></i> Add Class
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Class Name</th>
                                    <th>Line of Business</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($classes)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">No classes configured yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($classes as $cls): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-dark"><?= e($cls['name']) ?></td>
                                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e($cls['lob_name']) ?></span></td>
                                            <td class="text-end pe-3">
                                                <button type="button" class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#editClassModal<?= (int)$cls['id'] ?>">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this Expense Class?');">
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
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief description..."></textarea>
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
