<?php
/**
 * BudSheets Dashboard Overview (All Amounts in CAD)
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

if (!has_permission('budsheets_view')) {
    die('Access Denied');
}

$currFy = budsheets_get_fiscal_year();
$selectedFy = isset($_GET['fy']) && $_GET['fy'] !== '' ? (int)$_GET['fy'] : $currFy;
$managedFys = budsheets_get_managed_fiscal_years();

$lobs = budsheets_get_lobs(null, $selectedFy);
$items = budsheets_get_items(null, $selectedFy);
$summary = budsheets_get_dashboard_summary($selectedFy);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-calculator me-2 text-primary"></i>Operational Budgets Dashboard</h2>
            <p class="text-muted small mb-0">Overview of organizational budgets, recurring operational expenses, and invoices in <strong>CAD</strong> (FY<?= $selectedFy ?>).</p>
        </div>
        <div class="d-flex align-items-center flex-wrap gap-2">
            <!-- Financial Year Selector -->
            <form method="GET" class="d-flex align-items-center me-2">
                <input type="hidden" name="route" value="budsheets_dashboard">
                <label for="fy_select" class="form-label mb-0 me-2 fw-semibold text-muted small"><i class="fa-solid fa-calendar-days me-1"></i>Financial Year:</label>
                <select name="fy" id="fy_select" class="form-select form-select-sm fw-bold border-primary text-primary" onchange="this.form.submit()">
                    <?php
                    $fyYears = array_column($managedFys, 'year');
                    if (!in_array($currFy, $fyYears)) {
                        $fyYears[] = $currFy;
                    }
                    if (!in_array($selectedFy, $fyYears)) {
                        $fyYears[] = $selectedFy;
                    }
                    rsort($fyYears);
                    foreach (array_unique($fyYears) as $y):
                    ?>
                        <option value="<?= $y ?>" <?= $y === $selectedFy ? 'selected' : '' ?>>
                            FY<?= $y ?><?= $y === $currFy ? ' (Current)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if (has_permission('budsheets_edit')): ?>
                <a href="<?= url_for('budsheets_items') ?>&action=new&fy=<?= $selectedFy ?>" class="btn btn-primary me-2">
                    <i class="fa-solid fa-plus me-1"></i> Add Budget Item
                </a>
            <?php endif; ?>
            <?php if (has_permission('budsheets_admin')): ?>
                <a href="<?= url_for('budsheets_settings') ?>" class="btn btn-outline-secondary me-2">
                    <i class="fa-solid fa-gear me-1"></i> Settings
                </a>
                <a href="<?= url_for('budsheets_lobs') ?>&fy=<?= $selectedFy ?>" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-sitemap me-1"></i> Budgets
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary KPI Cards (All in CAD) -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-primary-subtle text-primary p-3 rounded-3 me-3">
                            <i class="fa-solid fa-sitemap fa-xl"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase">Budgets</div>
                            <div class="fs-3 fw-bold text-dark"><?= (int)$summary['lob_count'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-info-subtle text-info p-3 rounded-3 me-3">
                            <i class="fa-solid fa-boxes-stacked fa-xl"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase">Budget Items</div>
                            <div class="fs-3 fw-bold text-dark"><?= (int)$summary['item_count'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-warning-subtle text-warning p-3 rounded-3 me-3">
                            <i class="fa-solid fa-calendar-days fa-xl"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase">Monthly Total w/ Tax (CAD)</div>
                            <div class="fs-3 fw-bold text-dark">$<?= number_format((float)$summary['monthly_cost_cad'], 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-success-subtle text-success p-3 rounded-3 me-3">
                            <i class="fa-solid fa-receipt fa-xl"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase">Total Invoiced (CAD)</div>
                            <div class="fs-3 fw-bold text-success">$<?= number_format((float)$summary['total_invoiced_cad'], 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Line of Business Breakdown Table -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-layer-group me-2 text-secondary"></i>Budget Allocations (All converted to CAD - FY<?= $selectedFy ?>)</h5>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6">FY<?= $selectedFy ?> Selected Budget Year</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Budget</th>
                            <th>Code</th>
                            <th>Financial Year</th>
                            <th class="text-center">Assigned Items</th>
                            <th>Monthly Avg Pre-Tax (CAD)</th>
                            <th>Monthly Avg w/ Tax (CAD)</th>
                            <th>Annual Total w/ Tax (CAD)</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lobs)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No Budgets defined for FY<?= $selectedFy ?>.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lobs as $lob):
                                $lobItems = array_filter($items, function($i) use ($lob) { return $i['lob_id'] == $lob['id']; });
                                $lobFy = !empty($lob['fiscal_year']) ? (int)$lob['fiscal_year'] : $selectedFy;
                                $lobMonthlyBaseCad = 0.0;
                                $lobMonthlyTotalCad = 0.0;
                                $lobAnnualTotalCad = 0.0;
                                foreach ($lobItems as $li) {
                                    $fyCalc = budsheets_calculate_item_fy_cost($li, $lobFy);
                                    $lobMonthlyBaseCad += budsheets_convert_to_cad($fyCalc['monthly_base'], $li['currency'], $lobFy);
                                    $lobMonthlyTotalCad += budsheets_convert_to_cad($fyCalc['monthly_total'], $li['currency'], $lobFy);
                                    $lobAnnualTotalCad += budsheets_convert_to_cad($fyCalc['annual_total'], $li['currency'], $lobFy);
                                }
                            ?>
                                <tr>
                                    <td class="ps-3 fw-semibold text-dark"><?= e($lob['name']) ?></td>
                                    <td><span class="badge bg-secondary"><?= e($lob['code'] ?: 'N/A') ?></span></td>
                                    <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle">FY<?= $lobFy ?></span></td>
                                    <td class="text-center fw-bold"><?= count($lobItems) ?></td>
                                    <td class="fw-semibold">$<?= number_format($lobMonthlyBaseCad, 2) ?> CAD</td>
                                    <td class="fw-bold text-primary">$<?= number_format($lobMonthlyTotalCad, 2) ?> CAD</td>
                                    <td class="text-success fw-bold">$<?= number_format($lobAnnualTotalCad, 2) ?> CAD</td>
                                    <td class="text-end pe-3">
                                        <a href="<?= url_for('budsheets_items') ?>&fy=<?= $selectedFy ?>&lob_id=<?= (int)$lob['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fa-solid fa-eye me-1"></i> View Items
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
