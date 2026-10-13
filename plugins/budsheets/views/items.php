<?php
/**
 * Budget Items List and Create/Edit View
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

if (!has_permission('budsheets_view')) {
    die('Access Denied');
}

$currentFy = budsheets_get_fiscal_year();
$filterFyParam = $_GET['fy'] ?? null;
$selectedFy = ($filterFyParam === 'all') ? null : (isset($filterFyParam) && $filterFyParam !== '' ? (int)$filterFyParam : $currentFy);
$filterLob = isset($_GET['lob_id']) && $_GET['lob_id'] !== '' ? (int)$_GET['lob_id'] : null;

$lobs = budsheets_get_lobs(null, $selectedFy);
$allLobs = budsheets_get_lobs();
$managedFys = budsheets_get_managed_fiscal_years();
$message = '';
$error = '';
$action = $_POST['action'] ?? ($_GET['action'] ?? 'list');

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('validate_csrf')) {
        validate_csrf();
    }

    if ($action === 'save_item') {
        if (!has_permission('budsheets_edit')) {
            die('Access Denied: Edit permission required.');
        }

        $itemId = !empty($_POST['item_id']) ? (int)$_POST['item_id'] : null;
        $vendor = trim($_POST['vendor'] ?? '');
        $product = trim($_POST['product'] ?? '');
        $lob_id = (int)($_POST['lob_id'] ?? 0);

        if (!$lob_id || empty($vendor) || empty($product)) {
            $error = 'Budget, Vendor, and Product are required fields.';
        } else {
            $savedId = budsheets_save_item($_POST, $itemId);

            if ($savedId) {
                if (!empty($_FILES['contract_files'])) {
                    budsheets_save_contract_files($savedId, $_FILES['contract_files']);
                }
                set_flash_message('success', 'Budget item saved successfully.');
                redirect(url_for('budsheets_item_detail') . '&id=' . $savedId);
            } else {
                $error = 'Failed to save budget item.';
            }
        }
    } elseif ($action === 'delete_item') {
        if (!has_permission('budsheets_edit')) {
            die('Access Denied: Edit permission required.');
        }

        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId) {
            budsheets_delete_item($itemId);
            set_flash_message('success', 'Budget item deleted successfully.');
            redirect(url_for('budsheets_items'));
        }
    }
}

// Item Edit Data
$editItem = null;
if ($action === 'edit' && !empty($_GET['id'])) {
    $editItem = budsheets_get_item((int)$_GET['id']);
}

$items = budsheets_get_items($filterLob, $selectedFy);

// Build map of classes per Budget
$allClasses = budsheets_get_all_classes();
$lobClassesMap = [];
foreach ($allClasses as $c) {
    if (!empty($c['lob_ids'])) {
        foreach ($c['lob_ids'] as $lid) {
            $lobClassesMap[$lid][] = $c['name'];
        }
    } else {
        foreach ($allLobs as $l) {
            $lobClassesMap[$l['id']][] = $c['name'];
        }
    }
}

$monthsNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>

<div class="container-fluid py-4">
    <?php if ($action === 'new' || $action === 'edit'): ?>
        <?php if (!has_permission('budsheets_edit')) { die('Access Denied'); } ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-file-circle-plus me-2 text-primary"></i>
                    <?= $editItem ? 'Edit Budget Item' : 'New Budget Item' ?>
                </h2>
                <p class="text-muted small mb-0">Fill in operational expense details, contract length, and attach supporting contract documents.</p>
            </div>
            <a href="<?= url_for('budsheets_items') ?>" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Items List
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data">
                    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save_item">
                    <?php if ($editItem): ?>
                        <input type="hidden" name="item_id" value="<?= (int)$editItem['id'] ?>">
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Financial Year <span class="text-danger">*</span></label>
                            <select id="fy_form_select" class="form-select" onchange="filterLobsByFy()">
                                <?php
                                $formFy = $editItem ? (!empty($editItem['lob_fiscal_year']) ? (int)$editItem['lob_fiscal_year'] : $currentFy) : ($selectedFy ?? $currentFy);
                                $fyYears = array_column($managedFys, 'year');
                                if (!in_array($currentFy, $fyYears)) $fyYears[] = $currentFy;
                                if (!in_array($formFy, $fyYears)) $fyYears[] = $formFy;
                                rsort($fyYears);
                                foreach (array_unique($fyYears) as $y):
                                ?>
                                    <option value="<?= $y ?>" <?= $y === $formFy ? 'selected' : '' ?>>
                                        FY<?= $y ?><?= $y === $currentFy ? ' (Current)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Filters available Budgets by Financial Year.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Budget <span class="text-danger">*</span></label>
                            <select name="lob_id" id="lob_select" class="form-select" onchange="updateClassOptions()" required>
                                <option value="">-- Select Budget --</option>
                                <?php foreach ($allLobs as $lob): ?>
                                    <option value="<?= (int)$lob['id'] ?>" data-fy="<?= (int)($lob['fiscal_year'] ?: $currentFy) ?>" <?= ($editItem && $editItem['lob_id'] == $lob['id']) || ($filterLob == $lob['id']) ? 'selected' : '' ?>>
                                        <?= e($lob['name']) ?> (<?= e($lob['code'] ?: 'No Code') ?>) - FY<?= (int)($lob['fiscal_year'] ?: $currentFy) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Class / Category</label>
                            <select name="class" id="class_select" class="form-select">
                                <option value="">-- Select Class --</option>
                                <?php if ($editItem && !empty($editItem['class'])): ?>
                                    <option value="<?= e($editItem['class']) ?>" selected><?= e($editItem['class']) ?></option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Vendor <span class="text-danger">*</span></label>
                            <input type="text" name="vendor" class="form-control" value="<?= e($editItem['vendor'] ?? '') ?>" placeholder="e.g. Microsoft, AWS, Cisco" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Product / Service Name <span class="text-danger">*</span></label>
                            <input type="text" name="product" class="form-control" value="<?= e($editItem['product'] ?? '') ?>" placeholder="e.g. Office 365 E5 Licenses" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Currency</label>
                            <select name="currency" class="form-select">
                                <?php foreach (['USD', 'CAD', 'EUR', 'GBP', 'AUD'] as $curr): ?>
                                    <option value="<?= $curr ?>" <?= ($editItem['currency'] ?? 'USD') === $curr ? 'selected' : '' ?>><?= $curr ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Billing Frequency</label>
                            <select name="billing_frequency" id="billing_frequency" class="form-select" onchange="calculateAmounts(); toggleInvoiceMonth();">
                                <option value="monthly" <?= ($editItem['billing_frequency'] ?? 'monthly') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                <option value="yearly" <?= ($editItem['billing_frequency'] ?? '') === 'yearly' ? 'selected' : '' ?>>Full Year / Annual</option>
                            </select>
                        </div>

                        <div class="col-md-3" id="invoice_month_wrapper" style="display: none;">
                            <label class="form-label fw-semibold">Vendor Invoice Month</label>
                            <select name="invoice_month" id="invoice_month" class="form-select">
                                <option value="">-- Select Month --</option>
                                <?php foreach ($monthsNames as $mNum => $mName): ?>
                                    <option value="<?= $mNum ?>" <?= ($editItem['invoice_month'] ?? '') == $mNum ? 'selected' : '' ?>><?= $mName ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Recurring Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" name="monthly_cost" id="monthly_cost" class="form-control" value="<?= e($editItem['monthly_cost'] ?? '0.00') ?>" oninput="calculateAmounts()" required>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Calculated Equivalent</label>
                            <div class="p-2 bg-light rounded border text-muted small" id="calculated_summary">
                                Entering amount...
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Tax Type</label>
                            <select name="tax_type" class="form-select">
                                <?php foreach (['no tax', 'GSTandPST', 'GST only', 'PST only'] as $tax): ?>
                                    <option value="<?= $tax ?>" <?= ($editItem['tax_type'] ?? 'no tax') === $tax ? 'selected' : '' ?>><?= $tax ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Invoice Type</label>
                            <select name="invoice_type" class="form-select">
                                <option value="monthly" <?= ($editItem['invoice_type'] ?? 'monthly') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                <option value="year" <?= ($editItem['invoice_type'] ?? '') === 'year' ? 'selected' : '' ?>>Yearly</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Contract Review Date</label>
                            <input type="date" name="invoice_date" class="form-control" value="<?= e($editItem['invoice_date'] ?? '') ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Contract Start Date</label>
                            <input type="date" name="contract_start_date" class="form-control" value="<?= e($editItem['contract_start_date'] ?? '') ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Contract End Date</label>
                            <input type="date" name="contract_end_date" class="form-control" value="<?= e($editItem['contract_end_date'] ?? '') ?>">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">External Systems Directory Link (Optional)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-link"></i></span>
                                <input type="url" name="system_directory_link" class="form-control" value="<?= e($editItem['system_directory_link'] ?? '') ?>" placeholder="https://systems.domain.com/directory/item-123">
                            </div>
                            <div class="form-text">Direct URL link to this system entry in the internal Systems Directory.</div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Short Description</label>
                            <input type="text" name="description" class="form-control" value="<?= e($editItem['description'] ?? '') ?>" placeholder="Brief summary of item scope...">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Long Text Description / Notes</label>
                            <textarea name="long_description" class="form-control" rows="4" placeholder="Detailed terms, SLAs, renewal conditions, or internal billing notes..."><?= e($editItem['long_description'] ?? '') ?></textarea>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Upload Contract Documents (Multi-file enabled)</label>
                            <input type="file" name="contract_files[]" class="form-control" multiple>
                            <div class="form-text">Upload PDF, DOCX, XLSX or image copies of vendor contracts or statements.</div>
                        </div>

                        <div class="col-md-12 text-end mt-4">
                            <a href="<?= url_for('budsheets_items') ?>" class="btn btn-light me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Save Item</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script>
            const lobClassesMap = <?= json_encode($lobClassesMap) ?>;
            const currentSelectedClass = <?= json_encode($editItem['class'] ?? '') ?>;

            function filterLobsByFy() {
                const fySelect = document.getElementById('fy_form_select');
                const lobSelect = document.getElementById('lob_select');
                if (!fySelect || !lobSelect) return;

                const selectedFy = fySelect.value;
                const options = lobSelect.querySelectorAll('option');

                let hasSelectedMatch = false;
                options.forEach(function(opt) {
                    if (!opt.value) return; // Keep placeholder '-- Select Budget --'
                    const optFy = opt.getAttribute('data-fy');
                    if (!selectedFy || optFy === selectedFy) {
                        opt.style.display = '';
                        if (opt.selected) hasSelectedMatch = true;
                    } else {
                        opt.style.display = 'none';
                        if (opt.selected) opt.selected = false;
                    }
                });

                updateClassOptions();
            }

            function updateClassOptions() {
                const lobSelect = document.getElementById('lob_select');
                const classSelect = document.getElementById('class_select');
                if (!lobSelect || !classSelect) return;
                const selectedLobId = lobSelect.value;

                classSelect.innerHTML = '<option value="">-- Select Class --</option>';

                if (selectedLobId && lobClassesMap[selectedLobId]) {
                    const uniqueClasses = [...new Set(lobClassesMap[selectedLobId])];
                    uniqueClasses.forEach(function(className) {
                        const opt = document.createElement('option');
                        opt.value = className;
                        opt.textContent = className;
                        if (className === currentSelectedClass) {
                            opt.selected = true;
                        }
                        classSelect.appendChild(opt);
                    });
                } else if (currentSelectedClass) {
                    const opt = document.createElement('option');
                    opt.value = currentSelectedClass;
                    opt.textContent = currentSelectedClass;
                    opt.selected = true;
                    classSelect.appendChild(opt);
                }
            }

            function toggleInvoiceMonth() {
                const freq = document.getElementById('billing_frequency').value;
                const wrapper = document.getElementById('invoice_month_wrapper');
                if (freq === 'yearly') {
                    wrapper.style.display = 'block';
                } else {
                    wrapper.style.display = 'none';
                }
            }

            function calculateAmounts() {
                const freq = document.getElementById('billing_frequency').value;
                const cost = parseFloat(document.getElementById('monthly_cost').value) || 0;
                const summary = document.getElementById('calculated_summary');

                if (freq === 'yearly') {
                    const m = cost / 12.0;
                    summary.innerHTML = '<strong>Monthly Eq:</strong> $' + m.toFixed(2) + '<br><strong>Annual Base:</strong> $' + cost.toFixed(2);
                } else {
                    const a = cost * 12.0;
                    summary.innerHTML = '<strong>Monthly Base:</strong> $' + cost.toFixed(2) + '<br><strong>Annual Eq:</strong> $' + a.toFixed(2);
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                filterLobsByFy();
                updateClassOptions();
                toggleInvoiceMonth();
                calculateAmounts();
            });
        </script>

    <?php else: ?>
        <!-- List View -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Operational Budget Items</h2>
                <p class="text-muted small mb-0">Track vendors, products, contract lengths, recurring costs, and associated invoices in <strong>CAD</strong>.</p>
            </div>
            <div>
                <button type="button" class="btn btn-outline-success me-2" data-bs-toggle="modal" data-bs-target="#exportCsvModal">
                    <i class="fa-solid fa-file-csv me-1"></i> Export Financial Year CSV
                </button>
                <?php if (has_permission('budsheets_edit')): ?>
                    <a href="<?= url_for('budsheets_items') ?>&action=new<?= $filterLob ? '&lob_id=' . $filterLob : '' ?>" class="btn btn-primary">
                        <i class="fa-solid fa-plus me-1"></i> Add Budget Item
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter Bar: Financial Year (selector) -> Budget (LOB) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="route" value="budsheets_items">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light text-primary fw-semibold"><i class="fa-solid fa-calendar-days me-1"></i>FY</span>
                            <select name="fy" class="form-select fw-bold" onchange="this.form.submit()">
                                <option value="all" <?= $filterFyParam === 'all' ? 'selected' : '' ?>>-- All Financial Years --</option>
                                <?php
                                $fyYears = array_column($managedFys, 'year');
                                if (!in_array($currentFy, $fyYears)) $fyYears[] = $currentFy;
                                rsort($fyYears);
                                foreach (array_unique($fyYears) as $y):
                                ?>
                                    <option value="<?= $y ?>" <?= ($selectedFy !== null && $y === $selectedFy) ? 'selected' : '' ?>>
                                        FY<?= $y ?><?= $y === $currentFy ? ' (Current)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary fw-semibold"><i class="fa-solid fa-sitemap me-1"></i>Budget</span>
                            <select name="lob_id" class="form-select" onchange="this.form.submit()">
                                <option value="">-- All Budgets <?= $selectedFy ? 'in FY' . $selectedFy : '' ?> --</option>
                                <?php foreach ($lobs as $lob): ?>
                                    <option value="<?= (int)$lob['id'] ?>" <?= $filterLob == $lob['id'] ? 'selected' : '' ?>>
                                        <?= e($lob['name']) ?> (<?= e($lob['code'] ?: 'No Code') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php if ($filterLob || $filterFyParam !== null): ?>
                        <div class="col-md-2">
                            <a href="<?= url_for('budsheets_items') ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fa-solid fa-xmark me-1"></i>Reset Filters
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Items Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Budget</th>
                                <th>Vendor / Product</th>
                                <th>Class</th>
                                <th>Entered Cost</th>
                                <th>Monthly Total w/ Tax (CAD)</th>
                                <th>Full Year Annual Total (CAD)</th>
                                <th>Tax Type</th>
                                <th>Invoice Type</th>
                                <th>Contract Term</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">No budget items found for the selected Financial Year or Budget.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item):
                                    $itemFy = !empty($item['lob_fiscal_year']) ? (int)$item['lob_fiscal_year'] : ($selectedFy ?? $currentFy);
                                    $costCalc = budsheets_calculate_item_fy_cost($item, $itemFy);
                                    $monthlyCad = budsheets_convert_to_cad($costCalc['monthly_total'], $item['currency'], $itemFy);
                                    $annualCad = budsheets_convert_to_cad($costCalc['annual_total'], $item['currency'], $itemFy);
                                ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-semibold text-dark"><?= e($item['lob_name'] ?: 'Unassigned') ?></div>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">FY<?= $itemFy ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($item['vendor']) ?></div>
                                            <div class="small text-muted"><?= e($item['product']) ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= e($item['class'] ?: 'N/A') ?></span></td>
                                        <td class="fw-semibold text-secondary">
                                            $<?= number_format((float)$item['monthly_cost'], 2) ?> <small><?= e($item['currency']) ?></small>
                                            <div class="small text-muted">(<?= ucfirst(e($item['billing_frequency'] ?? 'monthly')) ?>)</div>
                                        </td>
                                        <td class="fw-bold text-primary">
                                            $<?= number_format($monthlyCad, 2) ?> CAD
                                        </td>
                                        <td class="fw-bold text-success">
                                            $<?= number_format($annualCad, 2) ?> CAD
                                        </td>
                                        <td><span class="badge bg-info-subtle text-info-emphasis"><?= e($item['tax_type']) ?></span></td>
                                        <td>
                                            <span class="badge bg-<?= $item['invoice_type'] === 'year' ? 'primary' : 'secondary' ?>">
                                                <?= ucfirst(e($item['invoice_type'])) ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted">
                                            <?php if ($item['contract_start_date'] || $item['contract_end_date']): ?>
                                                <?= e($item['contract_start_date'] ?: 'N/A') ?> to <?= e($item['contract_end_date'] ?: 'N/A') ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="<?= url_for('budsheets_item_detail') ?>&id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-outline-info me-1" title="View Details & Invoices">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <?php if (has_permission('budsheets_edit')): ?>
                                                <a href="<?= url_for('budsheets_items') ?>&action=edit&id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-outline-secondary me-1">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this budget item and all associated contracts and invoices?');">
                                                    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete_item">
                                                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Export CSV Modal -->
        <div class="modal fade" id="exportCsvModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="GET">
                        <input type="hidden" name="route" value="budsheets_export_csv">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-csv me-2 text-success"></i>Export Financial Year CSV</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Financial Year</label>
                                <select name="fy" class="form-select">
                                    <?php
                                    $fyYears = array_column($managedFys, 'year');
                                    if (!in_array($currentFy, $fyYears)) $fyYears[] = $currentFy;
                                    rsort($fyYears);
                                    foreach (array_unique($fyYears) as $y):
                                    ?>
                                        <option value="<?= $y ?>" <?= ($selectedFy ?? $currentFy) === $y ? 'selected' : '' ?>>FY<?= $y ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Budget</label>
                                <select name="lob_id" class="form-select">
                                    <option value="">-- All Budgets --</option>
                                    <?php foreach ($allLobs as $lob): ?>
                                        <option value="<?= (int)$lob['id'] ?>" <?= $filterLob == $lob['id'] ? 'selected' : '' ?>><?= e($lob['name']) ?> (FY<?= (int)($lob['fiscal_year'] ?: $currentFy) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success"><i class="fa-solid fa-download me-1"></i> Export CSV</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>
