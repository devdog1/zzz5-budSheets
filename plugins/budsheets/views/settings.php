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
            $message = 'Default Tax, Currency Exchange Rates, and Fiscal Year settings updated successfully.';
        } else {
            $error = 'Failed to update settings.';
        }
    } elseif ($action === 'save_fy_rates') {
        $fyTarget = (int)($_POST['target_fy'] ?? 0);
        if ($fyTarget) {
            budsheets_save_fy_rates($fyTarget, $_POST);
            $message = "Tax and Exchange Rates for Financial Year FY{$fyTarget} updated successfully.";
        } else {
            $error = "Invalid Financial Year target.";
        }
    } elseif ($action === 'add_fy') {
        $fyVal = (int)($_POST['new_fy'] ?? 0);
        if ($fyVal >= 2000 && $fyVal <= 2100) {
            budsheets_add_fiscal_year($fyVal);
            $message = "Financial Year FY{$fyVal} added successfully.";
        } else {
            $error = "Invalid Financial Year number.";
        }
    } elseif ($action === 'toggle_fy_hide') {
        $fyVal = (int)($_POST['target_fy'] ?? 0);
        $hideStatus = !empty($_POST['hide_status']);
        if ($fyVal) {
            budsheets_hide_fiscal_year($fyVal, $hideStatus);
            $message = $hideStatus ? "Financial Year FY{$fyVal} is now hidden." : "Financial Year FY{$fyVal} is now visible.";
        }
    } elseif ($action === 'remove_fy') {
        $fyVal = (int)($_POST['target_fy'] ?? 0);
        if ($fyVal) {
            budsheets_remove_fiscal_year($fyVal);
            $message = "Financial Year FY{$fyVal} removed.";
        }
    } elseif ($action === 'toggle_year_lock') {
        $fyTarget = (int)($_POST['target_fy'] ?? 0);
        $lockStatus = !empty($_POST['lock_status']);
        if ($fyTarget) {
            budsheets_set_fiscal_year_lock($fyTarget, $lockStatus);
            $message = $lockStatus ? "Fiscal Year FY{$fyTarget} has been locked." : "Fiscal Year FY{$fyTarget} has been unlocked.";
        }
    } elseif ($action === 'create_class') {
        $lob_ids = $_POST['lob_ids'] ?? [];
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($lob_ids) || empty($name)) {
            $error = 'At least one Budget and Class Name are required fields.';
        } else {
            if (budsheets_add_class($lob_ids, $name, $description)) {
                $message = 'Expense Class created successfully.';
            } else {
                $error = 'Failed to create Expense Class.';
            }
        }
    } elseif ($action === 'update_class') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $lob_ids = $_POST['lob_ids'] ?? [];
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!$class_id || empty($lob_ids) || empty($name)) {
            $error = 'At least one Budget and Class Name are required.';
        } else {
            if (budsheets_update_class($class_id, $lob_ids, $name, $description)) {
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

$monthsList = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$currentFy = budsheets_get_fiscal_year();
$managedFyList = budsheets_get_managed_fiscal_years(true);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-gear me-2 text-primary"></i>Plugin Settings & Configurations</h2>
            <p class="text-muted small mb-0">Manage Financial Years, PST/GST tax rates, currency exchange rates to CAD, and Budget expense classes.</p>
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
        <!-- Fiscal Year Definition Box -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-sliders me-2 text-primary"></i>Global Fiscal Year Configuration</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                        <input type="hidden" name="action" value="save_rates">

                        <h6 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-calendar-days me-1"></i>Financial / Fiscal Year Definition</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-4">
                                <label class="form-label fw-semibold">Starting Year</label>
                                <input type="number" min="2000" max="2100" name="starting_fiscal_year" class="form-control" value="<?= e($settings['starting_fiscal_year']) ?>" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-semibold">Fiscal Start Month</label>
                                <select name="fiscal_year_start_month" class="form-select" required>
                                    <?php foreach ($monthsList as $mNum => $mName): ?>
                                        <option value="<?= $mNum ?>" <?= ((int)$settings['fiscal_year_start_month'] === $mNum) ? 'selected' : '' ?>>
                                            <?= $mName ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-semibold">Start Day</label>
                                <input type="number" min="1" max="28" name="fiscal_year_start_day" class="form-control" value="<?= e($settings['fiscal_year_start_day'] ?? 1) ?>" required>
                            </div>
                            <div class="col-12">
                                <div class="p-2 bg-light rounded border text-muted small">
                                    <i class="fa-solid fa-circle-info me-1 text-info"></i> Current calculated active Fiscal Year: <strong>FY<?= $currentFy ?></strong>
                                    <br><span class="text-secondary">(Note: Fiscal Year is named after the calendar year number that Jan 1st falls into. Tax and exchange rates are configured per Financial Year in the table on the right.)</span>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Save Global Settings</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Weekly Email Summary Reports Box -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-envelope me-2 text-primary"></i>Weekly Budget Email Summary Reports</h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">Send or test automated HTML weekly budget summary reports for active Lines of Business and expenses.</p>
                    <form method="POST" action="<?= url_for('budsheets_send_test_email') ?>">
                        <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Recipient Email Address (Optional Test Email)</label>
                            <input type="email" name="target_email" class="form-control form-control-sm" placeholder="e.g. admin@domain.com (Leave blank for all users)">
                            <div class="form-text">Leave blank to dispatch to all registered system users, or enter a specific email address for a test run.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Financial Year Summary</label>
                            <select name="fiscal_year" class="form-select form-select-sm">
                                <?php foreach ($managedFyList as $fyRow): ?>
                                    <option value="<?= (int)$fyRow['year'] ?>" <?= (int)$fyRow['year'] === $currentFy ? 'selected' : '' ?>>
                                        FY<?= (int)$fyRow['year'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-paper-plane me-1"></i> Dispatch Weekly Summary Email</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: Managed Financial Years & Expense Classes -->
        <div class="col-lg-6">
            <!-- Financial Years Management Box -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-calendar-check me-2 text-primary"></i>Financial Years Management</h5>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addFyModal">
                        <i class="fa-solid fa-plus me-1"></i> Add Year
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Financial Year</th>
                                    <th>Visibility</th>
                                    <th>Lock Status</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($managedFyList)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No financial years defined.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($managedFyList as $fyRow):
                                        $y = (int)$fyRow['year'];
                                        $isHidden = !empty($fyRow['is_hidden']);
                                        $isLocked = !empty($fyRow['is_locked']);
                                        $fySettings = budsheets_get_settings($y);
                                    ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-dark">FY<?= $y ?></td>
                                            <td>
                                                <?php if ($isHidden): ?>
                                                    <span class="badge bg-secondary"><i class="fa-solid fa-eye-slash me-1"></i>Hidden</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info"><i class="fa-solid fa-eye me-1"></i>Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($isLocked): ?>
                                                    <span class="badge bg-danger"><i class="fa-solid fa-lock me-1"></i>Locked</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success"><i class="fa-solid fa-lock-open me-1"></i>Editable</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <!-- Edit Year Tax & FX Rates -->
                                                <button type="button" class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editFyRatesModal<?= $y ?>" title="Configure FY<?= $y ?> Tax & FX Rates">
                                                    <i class="fa-solid fa-percent me-1"></i>Rates
                                                </button>

                                                <!-- Toggle Hide/Unhide -->
                                                <form method="POST" class="d-inline me-1">
                                                    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="toggle_fy_hide">
                                                    <input type="hidden" name="target_fy" value="<?= $y ?>">
                                                    <input type="hidden" name="hide_status" value="<?= $isHidden ? '0' : '1' ?>">
                                                    <button type="submit" class="btn btn-sm <?= $isHidden ? 'btn-outline-info' : 'btn-outline-secondary' ?>" title="<?= $isHidden ? 'Unhide' : 'Hide' ?>">
                                                        <i class="fa-solid <?= $isHidden ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                                                    </button>
                                                </form>

                                                <!-- Toggle Lock/Unlock -->
                                                <form method="POST" class="d-inline me-1">
                                                    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="toggle_year_lock">
                                                    <input type="hidden" name="target_fy" value="<?= $y ?>">
                                                    <input type="hidden" name="lock_status" value="<?= $isLocked ? '0' : '1' ?>">
                                                    <button type="submit" class="btn btn-sm <?= $isLocked ? 'btn-outline-success' : 'btn-outline-warning' ?>" title="<?= $isLocked ? 'Unlock FY' . $y : 'Lock FY' . $y ?>">
                                                        <i class="fa-solid <?= $isLocked ? 'fa-lock-open' : 'fa-lock' ?>"></i>
                                                    </button>
                                                </form>

                                                <!-- Remove FY -->
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Remove Financial Year FY<?= $y ?>?');">
                                                    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="remove_fy">
                                                    <input type="hidden" name="target_fy" value="<?= $y ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove Year">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>

                                        <!-- Edit FY Rates Modal -->
                                        <div class="modal fade" id="editFyRatesModal<?= $y ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                                        <input type="hidden" name="action" value="save_fy_rates">
                                                        <input type="hidden" name="target_fy" value="<?= $y ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-percent me-2 text-primary"></i>Financial Year FY<?= $y ?> Tax & FX Rates</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p class="small text-muted mb-3">Configure specific tax rates and foreign exchange rates to CAD for FY<?= $y ?>.</p>
                                                            <h6 class="fw-bold text-secondary mb-2">Tax Rates (%)</h6>
                                                            <div class="row g-2 mb-3">
                                                                <div class="col-6">
                                                                    <label class="form-label small fw-semibold">GST Rate (%)</label>
                                                                    <div class="input-group input-group-sm">
                                                                        <input type="number" step="0.01" name="gst_rate" class="form-control" value="<?= e($fySettings['gst_rate']) ?>" required>
                                                                        <span class="input-group-text">%</span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-6">
                                                                    <label class="form-label small fw-semibold">PST Rate (%)</label>
                                                                    <div class="input-group input-group-sm">
                                                                        <input type="number" step="0.01" name="pst_rate" class="form-control" value="<?= e($fySettings['pst_rate']) ?>" required>
                                                                        <span class="input-group-text">%</span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <h6 class="fw-bold text-secondary mb-2">Exchange Rates to CAD</h6>
                                                            <div class="row g-2 mb-3">
                                                                <div class="col-6">
                                                                    <label class="form-label small fw-semibold">1 USD in CAD</label>
                                                                    <input type="number" step="0.0001" name="rate_USD" class="form-control form-control-sm" value="<?= e($fySettings['rate_USD']) ?>" required>
                                                                </div>
                                                                <div class="col-6">
                                                                    <label class="form-label small fw-semibold">1 EUR in CAD</label>
                                                                    <input type="number" step="0.0001" name="rate_EUR" class="form-control form-control-sm" value="<?= e($fySettings['rate_EUR']) ?>" required>
                                                                </div>
                                                                <div class="col-6">
                                                                    <label class="form-label small fw-semibold">1 GBP in CAD</label>
                                                                    <input type="number" step="0.0001" name="rate_GBP" class="form-control form-control-sm" value="<?= e($fySettings['rate_GBP']) ?>" required>
                                                                </div>
                                                                <div class="col-6">
                                                                    <label class="form-label small fw-semibold">1 AUD in CAD</label>
                                                                    <input type="number" step="0.0001" name="rate_AUD" class="form-control form-control-sm" value="<?= e($fySettings['rate_AUD']) ?>" required>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Save FY<?= $y ?> Rates</button>
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

            <!-- Expense Classes Card -->
            <div class="card border-0 shadow-sm">
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
                                    <th>Assigned Budgets</th>
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
                                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e($cls['lob_names']) ?></span></td>
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
                                                                <label class="form-label fw-semibold">Class Name <span class="text-danger">*</span></label>
                                                                <input type="text" name="name" class="form-control" value="<?= e($cls['name']) ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-semibold">Assigned Budgets <span class="text-danger">*</span></label>
                                                                <div class="border rounded p-3 bg-light" style="max-height: 180px; overflow-y: auto;">
                                                                    <?php foreach ($lobs as $lob): ?>
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="checkbox" name="lob_ids[]" value="<?= (int)$lob['id'] ?>" id="edit_class_<?= (int)$cls['id'] ?>_lob_<?= (int)$lob['id'] ?>" <?= in_array($lob['id'], $cls['lob_ids']) ? 'checked' : '' ?>>
                                                                            <label class="form-check-label" for="edit_class_<?= (int)$cls['id'] ?>_lob_<?= (int)$lob['id'] ?>">
                                                                                <?= e($lob['name']) ?> (<?= e($lob['code'] ?: 'No Code') ?>)
                                                                            </label>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                                <div class="form-text">Select one or multiple budgets where this class applies.</div>
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

<!-- Add Financial Year Modal -->
<div class="modal fade" id="addFyModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                <input type="hidden" name="action" value="add_fy">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-calendar-plus me-2"></i>Add Financial Year</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Year Number <span class="text-danger">*</span></label>
                        <input type="number" min="2000" max="2100" name="new_fy" class="form-control" value="<?= (int)date('Y') + 1 ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Year</button>
                </div>
            </form>
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
                        <label class="form-label fw-semibold">Class Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Software Licenses, Hardware, Professional Services" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Assigned Budgets <span class="text-danger">*</span></label>
                        <div class="border rounded p-3 bg-light" style="max-height: 180px; overflow-y: auto;">
                            <?php foreach ($lobs as $lob): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="lob_ids[]" value="<?= (int)$lob['id'] ?>" id="add_class_lob_<?= (int)$lob['id'] ?>" checked>
                                    <label class="form-check-label" for="add_class_lob_<?= (int)$lob['id'] ?>">
                                        <?= e($lob['name']) ?> (<?= e($lob['code'] ?: 'No Code') ?>)
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">Select one or multiple budgets where this class applies.</div>
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
