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

$lobs = budsheets_get_lobs();
$message = '';
$error = '';
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');
$filterLob = isset($_GET['lob_id']) ? (int)$_GET['lob_id'] : null;

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
            $error = 'Line of Business, Vendor, and Product are required fields.';
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

$items = budsheets_get_items($filterLob);
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
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Line of Business <span class="text-danger">*</span></label>
                            <select name="lob_id" class="form-select" required>
                                <option value="">-- Select Line of Business --</option>
                                <?php foreach ($lobs as $lob): ?>
                                    <option value="<?= (int)$lob['id'] ?>" <?= ($editItem && $editItem['lob_id'] == $lob['id']) || ($filterLob == $lob['id']) ? 'selected' : '' ?>>
                                        <?= e($lob['name']) ?> (<?= e($lob['code'] ?: 'No Code') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Class / Category</label>
                            <input type="text" name="class" class="form-control" value="<?= e($editItem['class'] ?? '') ?>" placeholder="e.g. Software, Hardware, Consulting">
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
                            <label class="form-label fw-semibold">Monthly Cost</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" name="monthly_cost" class="form-control" value="<?= e($editItem['monthly_cost'] ?? '0.00') ?>" required>
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

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Invoice Date</label>
                            <input type="date" name="invoice_date" class="form-control" value="<?= e($editItem['invoice_date'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Contract Start Date</label>
                            <input type="date" name="contract_start_date" class="form-control" value="<?= e($editItem['contract_start_date'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Contract End Date</label>
                            <input type="date" name="contract_end_date" class="form-control" value="<?= e($editItem['contract_end_date'] ?? '') ?>">
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

    <?php else: ?>
        <!-- List View -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Operational Budget Items</h2>
                <p class="text-muted small mb-0">Track vendors, products, contract lengths, recurring costs, and associated invoices.</p>
            </div>
            <div>
                <a href="<?= url_for('budsheets_export_csv') ?><?= $filterLob ? '&lob_id=' . $filterLob : '' ?>" class="btn btn-outline-success me-2">
                    <i class="fa-solid fa-file-csv me-1"></i> Export CSV
                </a>
                <?php if (has_permission('budsheets_edit')): ?>
                    <a href="<?= url_for('budsheets_items') ?>&action=new<?= $filterLob ? '&lob_id=' . $filterLob : '' ?>" class="btn btn-primary">
                        <i class="fa-solid fa-plus me-1"></i> Add Budget Item
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="route" value="budsheets_items">
                    <div class="col-md-4">
                        <select name="lob_id" class="form-select" onchange="this.form.submit()">
                            <option value="">-- All Lines of Business --</option>
                            <?php foreach ($lobs as $lob): ?>
                                <option value="<?= (int)$lob['id'] ?>" <?= $filterLob == $lob['id'] ? 'selected' : '' ?>>
                                    <?= e($lob['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($filterLob): ?>
                        <div class="col-md-2">
                            <a href="<?= url_for('budsheets_items') ?>" class="btn btn-sm btn-outline-secondary">Clear Filter</a>
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
                                <th class="ps-3">Line of Business</th>
                                <th>Vendor / Product</th>
                                <th>Class</th>
                                <th>Monthly Cost</th>
                                <th>Tax Type</th>
                                <th>Invoice Type</th>
                                <th>Contract Term</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No budget items found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <span class="fw-semibold text-dark"><?= e($item['lob_name'] ?: 'Unassigned') ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($item['vendor']) ?></div>
                                            <div class="small text-muted"><?= e($item['product']) ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= e($item['class'] ?: 'N/A') ?></span></td>
                                        <td class="fw-bold text-success">
                                            $<?= number_format((float)$item['monthly_cost'], 2) ?> <small class="text-muted"><?= e($item['currency']) ?></small>
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
    <?php endif; ?>
</div>
