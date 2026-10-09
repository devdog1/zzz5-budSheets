<?php
/**
 * Item Detail & Invoice Management View
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

if (!has_permission('budsheets_view')) {
    die('Access Denied');
}

$itemId = (int)($_GET['id'] ?? 0);
$item = budsheets_get_item($itemId);

if (!$item) {
    die('Budget item not found.');
}

$message = '';
$error = '';

// Handle Actions (Invoices & Contract Deletion)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('validate_csrf')) {
        validate_csrf();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_invoice') {
        if (!has_permission('budsheets_edit')) {
            die('Access Denied: Edit permission required.');
        }

        $invoice_id = !empty($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : null;
        $data = [
            'item_id'        => $itemId,
            'invoice_number' => $_POST['invoice_number'] ?? '',
            'amount_paid'    => $_POST['amount_paid'] ?? 0,
            'currency'       => $_POST['currency'] ?? 'USD',
            'period_type'    => $_POST['period_type'] ?? 'monthly',
            'period_year'    => $_POST['period_year'] ?? date('Y'),
            'period_month'   => $_POST['period_month'] ?? null,
            'payment_date'   => $_POST['payment_date'] ?? null,
            'comments'       => $_POST['comments'] ?? ''
        ];

        $file = $_FILES['invoice_attachment'] ?? null;
        if (budsheets_save_invoice($data, $file, $invoice_id)) {
            $message = 'Invoice recorded successfully.';
        } else {
            $error = 'Failed to save invoice.';
        }
    } elseif ($action === 'delete_invoice') {
        if (!has_permission('budsheets_edit')) {
            die('Access Denied: Edit permission required.');
        }
        $invoice_id = (int)($_POST['invoice_id'] ?? 0);
        if ($invoice_id) {
            budsheets_delete_invoice($invoice_id);
            $message = 'Invoice deleted successfully.';
        }
    } elseif ($action === 'delete_contract_file') {
        if (!has_permission('budsheets_edit')) {
            die('Access Denied: Edit permission required.');
        }
        $file_id = (int)($_POST['file_id'] ?? 0);
        if ($file_id) {
            budsheets_delete_contract_file($file_id);
            $message = 'Contract attachment removed.';
        }
    } elseif ($action === 'upload_contract_files') {
        if (!has_permission('budsheets_edit')) {
            die('Access Denied: Edit permission required.');
        }
        if (!empty($_FILES['contract_files'])) {
            budsheets_save_contract_files($itemId, $_FILES['contract_files']);
            $message = 'Contract file(s) uploaded successfully.';
        }
    }
}

$contracts = budsheets_get_contract_files($itemId);
$invoices = budsheets_get_invoices($itemId);

$taxCalc = budsheets_calculate_tax((float)$item['monthly_cost'], $item['tax_type']);
$monthlyCad = budsheets_convert_to_cad($taxCalc['total'], $item['currency']);

$totalInvoicedCad = 0.0;
foreach ($invoices as $inv) {
    $totalInvoicedCad += budsheets_convert_to_cad($inv['amount_paid'], $inv['currency']);
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="text-muted small fw-semibold text-uppercase mb-1">
                <i class="fa-solid fa-sitemap me-1"></i><?= e($item['lob_name']) ?>
            </div>
            <h2 class="fw-bold text-dark mb-0"><?= e($item['vendor']) ?> - <?= e($item['product']) ?></h2>
        </div>
        <div>
            <a href="<?= url_for('budsheets_items') ?>" class="btn btn-outline-secondary me-2">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Items
            </a>
            <?php if (has_permission('budsheets_edit')): ?>
                <a href="<?= url_for('budsheets_items') ?>&action=edit&id=<?= (int)$item['id'] ?>" class="btn btn-primary">
                    <i class="fa-solid fa-pen-to-square me-1"></i> Edit Item
                </a>
            <?php endif; ?>
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
        <!-- Item Overview Details -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-circle-info me-2 text-primary"></i>Item Specifications & Cost Breakdown (CAD)</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="small text-muted">Monthly Base Cost</div>
                            <div class="fs-5 fw-bold text-dark">$<?= number_format((float)$item['monthly_cost'], 2) ?> <small><?= e($item['currency']) ?></small></div>
                        </div>

                        <div class="col-md-4">
                            <div class="small text-muted">Monthly Total w/ Tax (CAD)</div>
                            <div class="fs-5 fw-bold text-success">$<?= number_format($monthlyCad, 2) ?> CAD</div>
                        </div>

                        <div class="col-md-4">
                            <div class="small text-muted">Tax Type & Calculated Taxes</div>
                            <div class="fs-6 fw-semibold text-dark mb-1">
                                <span class="badge bg-info-subtle text-info-emphasis me-1"><?= e($item['tax_type']) ?></span>
                            </div>
                            <div class="small text-muted">
                                GST: $<?= number_format($taxCalc['gst'], 2) ?> | PST: $<?= number_format($taxCalc['pst'], 2) ?>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="small text-muted">Invoice Schedule</div>
                            <div class="fs-6 fw-semibold text-dark"><span class="badge bg-secondary"><?= ucfirst(e($item['invoice_type'])) ?></span></div>
                        </div>

                        <div class="col-md-4">
                            <div class="small text-muted">Class / Category</div>
                            <div class="fw-semibold text-dark"><?= e($item['class'] ?: 'None Specified') ?></div>
                        </div>

                        <div class="col-md-4">
                            <div class="small text-muted">Invoice Date</div>
                            <div class="fw-semibold text-dark"><?= e($item['invoice_date'] ?: 'N/A') ?></div>
                        </div>

                        <div class="col-md-4">
                            <div class="small text-muted">Contract Term</div>
                            <div class="fw-semibold text-dark">
                                <?php if ($item['contract_start_date'] || $item['contract_end_date']): ?>
                                    <?= e($item['contract_start_date'] ?: 'N/A') ?> &rarr; <?= e($item['contract_end_date'] ?: 'N/A') ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-12 mt-3">
                            <div class="small text-muted">Short Description</div>
                            <p class="text-dark mb-0"><?= e($item['description'] ?: 'No short description provided.') ?></p>
                        </div>

                        <?php if ($item['long_description']): ?>
                            <div class="col-12 mt-3">
                                <div class="small text-muted">Detailed Notes & Contract Terms</div>
                                <div class="p-3 bg-light rounded border text-secondary small" style="white-space: pre-wrap;"><?= e($item['long_description']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contract Documents Box -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-contract me-2 text-primary"></i>Contract Copies</h5>
                    <?php if (has_permission('budsheets_edit')): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadContractModal">
                            <i class="fa-solid fa-upload"></i>
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($contracts)): ?>
                        <div class="text-center py-4 text-muted small">No contract files uploaded yet.</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($contracts as $c): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div class="text-truncate me-2" style="max-width: 200px;">
                                        <i class="fa-solid fa-paperclip me-1 text-muted"></i>
                                        <a href="<?= url_for('budsheets_download_file') ?>&file_type=contract&file_id=<?= (int)$c['id'] ?>" class="fw-semibold text-decoration-none">
                                            <?= e($c['original_filename']) ?>
                                        </a>
                                        <div class="small text-muted"><?= round($c['file_size'] / 1024, 1) ?> KB</div>
                                    </div>
                                    <?php if (has_permission('budsheets_edit')): ?>
                                        <form method="POST" onsubmit="return confirm('Remove this contract document?');">
                                            <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_contract_file">
                                            <input type="hidden" name="file_id" value="<?= (int)$c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-link text-danger p-0 ms-2"><i class="fa-solid fa-xmark"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoices Section -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-receipt me-2 text-primary"></i>Invoices Paid Against Item</h5>
                <span class="small text-muted">Total Invoiced: <strong>$<?= number_format($totalInvoicedCad, 2) ?> CAD</strong></span>
            </div>
            <?php if (has_permission('budsheets_edit')): ?>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addInvoiceModal">
                    <i class="fa-solid fa-plus me-1"></i> Record Invoice
                </button>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Invoice #</th>
                            <th>Period</th>
                            <th>Payment Date</th>
                            <th>Amount Paid (Native)</th>
                            <th>Amount Paid (CAD)</th>
                            <th>Attachment</th>
                            <th>Comments</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No invoices logged for this item yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $inv):
                                $invCad = budsheets_convert_to_cad($inv['amount_paid'], $inv['currency']);
                            ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-dark"><?= e($inv['invoice_number'] ?: 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?php if ($inv['period_type'] === 'full_year'): ?>
                                                Full Year <?= (int)$inv['period_year'] ?>
                                            <?php else: ?>
                                                <?= date('F', mktime(0, 0, 0, $inv['period_month'] ?: 1, 10)) ?> <?= (int)$inv['period_year'] ?>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= e($inv['payment_date'] ?: 'N/A') ?></td>
                                    <td class="fw-semibold text-secondary">$<?= number_format((float)$inv['amount_paid'], 2) ?> <small><?= e($inv['currency']) ?></small></td>
                                    <td class="fw-bold text-success">$<?= number_format($invCad, 2) ?> CAD</td>
                                    <td>
                                        <?php if ($inv['attachment_original_name']): ?>
                                            <a href="<?= url_for('budsheets_download_file') ?>&file_type=invoice&file_id=<?= (int)$inv['id'] ?>" class="badge bg-secondary text-decoration-none">
                                                <i class="fa-solid fa-paperclip me-1"></i><?= e($inv['attachment_original_name']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= e($inv['comments'] ?: '—') ?></td>
                                    <td class="text-end pe-3">
                                        <?php if (has_permission('budsheets_edit')): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#editInvoiceModal<?= (int)$inv['id'] ?>">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this invoice record?');">
                                                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_invoice">
                                                <input type="hidden" name="invoice_id" value="<?= (int)$inv['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- Edit Invoice Modal -->
                                <div class="modal fade" id="editInvoiceModal<?= (int)$inv['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" enctype="multipart/form-data">
                                                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="save_invoice">
                                                <input type="hidden" name="invoice_id" value="<?= (int)$inv['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Invoice Record</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Invoice Number</label>
                                                        <input type="text" name="invoice_number" class="form-control" value="<?= e($inv['invoice_number']) ?>">
                                                    </div>
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-8">
                                                            <label class="form-label fw-semibold">Amount Paid <span class="text-danger">*</span></label>
                                                            <input type="number" step="0.01" name="amount_paid" class="form-control" value="<?= e($inv['amount_paid']) ?>" required>
                                                        </div>
                                                        <div class="col-4">
                                                            <label class="form-label fw-semibold">Currency</label>
                                                            <select name="currency" class="form-select">
                                                                <?php foreach (['USD', 'CAD', 'EUR', 'GBP', 'AUD'] as $curr): ?>
                                                                    <option value="<?= $curr ?>" <?= $inv['currency'] === $curr ? 'selected' : '' ?>><?= $curr ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-6">
                                                            <label class="form-label fw-semibold">Period Type</label>
                                                            <select name="period_type" class="form-select">
                                                                <option value="monthly" <?= $inv['period_type'] === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                                                <option value="full_year" <?= $inv['period_type'] === 'full_year' ? 'selected' : '' ?>>Full Year</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-3">
                                                            <label class="form-label fw-semibold">Year</label>
                                                            <select name="period_year" class="form-select">
                                                                <?php for ($y = date('Y') - 5; $y <= date('Y') + 5; $y++): ?>
                                                                    <option value="<?= $y ?>" <?= $inv['period_year'] == $y ? 'selected' : '' ?>><?= $y ?></option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-3">
                                                            <label class="form-label fw-semibold">Month</label>
                                                            <select name="period_month" class="form-select">
                                                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                                                    <option value="<?= $m ?>" <?= $inv['period_month'] == $m ? 'selected' : '' ?>><?= date('M', mktime(0, 0, 0, $m, 10)) ?></option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Payment Date</label>
                                                        <input type="date" name="payment_date" class="form-control" value="<?= e($inv['payment_date']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Comments / Notes</label>
                                                        <textarea name="comments" class="form-control" rows="2"><?= e($inv['comments']) ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Attachment (PDF/Image)</label>
                                                        <input type="file" name="invoice_attachment" class="form-control">
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

<!-- Add Invoice Modal -->
<div class="modal fade" id="addInvoiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_invoice">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-receipt me-2"></i>Record New Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Invoice Number</label>
                        <input type="text" name="invoice_number" class="form-control" placeholder="e.g. INV-2026-001">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label fw-semibold">Amount Paid <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount_paid" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Currency</label>
                            <select name="currency" class="form-select">
                                <?php foreach (['USD', 'CAD', 'EUR', 'GBP', 'AUD'] as $curr): ?>
                                    <option value="<?= $curr ?>" <?= $item['currency'] === $curr ? 'selected' : '' ?>><?= $curr ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Period Type</label>
                            <select name="period_type" class="form-select">
                                <option value="monthly">Monthly</option>
                                <option value="full_year">Full Year</option>
                            </select>
                        </div>
                        <div class="col-3">
                            <label class="form-label fw-semibold">Year</label>
                            <select name="period_year" class="form-select">
                                <?php for ($y = date('Y') - 5; $y <= date('Y') + 5; $y++): ?>
                                    <option value="<?= $y ?>" <?= date('Y') == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-3">
                            <label class="form-label fw-semibold">Month</label>
                            <select name="period_month" class="form-select">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= date('n') == $m ? 'selected' : '' ?>><?= date('M', mktime(0, 0, 0, $m, 10)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Comments / Notes</label>
                        <textarea name="comments" class="form-control" rows="2" placeholder="Payment reference or comments..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Upload Invoice Attachment</label>
                        <input type="file" name="invoice_attachment" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Contract Modal -->
<div class="modal fade" id="uploadContractModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                <input type="hidden" name="action" value="upload_contract_files">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-upload me-2"></i>Upload Contract Documents</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Files (Multiple allowed)</label>
                        <input type="file" name="contract_files[]" class="form-control" multiple required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload Files</button>
                </div>
            </form>
        </div>
    </div>
</div>
