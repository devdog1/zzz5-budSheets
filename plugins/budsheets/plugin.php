<?php
/**
 * Plugin Name: Operational Budget Sheets (BudSheets)
 * Description: Operational budget tracking plugin. Manage Lines of Business, budget items, contract documents, and invoices.
 * Version: 1.0.0
 * Author: DevDog
 * Permissions: budsheets_view, budsheets_edit, budsheets_admin
 * Roles: admin:budsheets_view,budsheets_edit,budsheets_admin; editor:budsheets_view,budsheets_edit; viewer:budsheets_view
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../');
}

// Require Helper / Model logic
require_once __DIR__ . '/models/budsheets-model.php';

// Navigation Hook
add_filter('theme_nav_links', function ($links) {
    if (!has_permission('budsheets_view')) {
        return $links;
    }

    $children = [
        ['label' => 'Overview & Dashboard', 'icon' => 'fa-solid fa-chart-line', 'route' => 'budsheets_dashboard'],
        ['label' => 'Budget Items', 'icon' => 'fa-solid fa-file-invoice-dollar', 'route' => 'budsheets_items']
    ];

    if (has_permission('budsheets_admin')) {
        $children[] = ['label' => 'Lines of Business', 'icon' => 'fa-solid fa-sitemap', 'route' => 'budsheets_lobs'];
    }

    $links[] = [
        'label' => 'Operational Budgets',
        'icon'  => 'fa-solid fa-calculator',
        'route' => 'budsheets_dashboard',
        'children' => $children
    ];

    return $links;
});

// Dashboard Widget Hook
add_action('index_dashboard_widgets', function ($userContext) {
    if (!has_permission('budsheets_view')) {
        return;
    }

    $summary = budsheets_get_dashboard_summary();
    ?>
    <div class="card shadow-sm border-start border-4 border-success mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="fw-bold text-success mb-0">
                    <i class="fa-solid fa-calculator me-2"></i>Operational Budgets Overview
                </h5>
                <a href="<?= url_for('budsheets_dashboard') ?>" class="btn btn-sm btn-outline-success">View Details</a>
            </div>
            <div class="row text-center mt-3">
                <div class="col-4">
                    <div class="small text-muted">Lines of Business</div>
                    <div class="fs-4 fw-bold"><?= (int)$summary['lob_count'] ?></div>
                </div>
                <div class="col-4">
                    <div class="small text-muted">Budget Items</div>
                    <div class="fs-4 fw-bold"><?= (int)$summary['item_count'] ?></div>
                </div>
                <div class="col-4">
                    <div class="small text-muted">Total Invoiced</div>
                    <div class="fs-4 fw-bold text-primary">$<?= number_format((float)$summary['total_invoiced'], 2) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php
});

// Routes Registration
add_action('register_routes', function() {
    register_route('budsheets_dashboard', function() {
        if (!has_permission('budsheets_view')) {
            die('Access Denied');
        }
        require_once __DIR__ . '/views/dashboard.php';
    });

    register_route('budsheets_lobs', function() {
        if (!has_permission('budsheets_admin')) {
            die('Access Denied');
        }
        require_once __DIR__ . '/views/lobs.php';
    });

    register_route('budsheets_items', function() {
        if (!has_permission('budsheets_view')) {
            die('Access Denied');
        }
        require_once __DIR__ . '/views/items.php';
    });

    register_route('budsheets_item_detail', function() {
        if (!has_permission('budsheets_view')) {
            die('Access Denied');
        }
        require_once __DIR__ . '/views/item_detail.php';
    });

    register_route('budsheets_download_file', function() {
        if (!has_permission('budsheets_view')) {
            die('Access Denied');
        }
        budsheets_handle_file_download();
    });
});
