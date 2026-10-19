<?php
/**
 * Plugin Name: Operational Budget Sheets (BudSheets)
 * Plugin URI: https://github.com/devdog1/zzz5
 * Description: Enterprise operational budget tracking plugin for zzz5 Portal Framework. Manage Budgets (Lines of Business), expense classes, budget items, contract documents, and invoices.
 * Version: 1.5.0
 * Author: DevDog
 * Author URI: https://github.com/devdog1
 * Permissions: budsheets_view, budsheets_edit, budsheets_admin
 * Roles: admin:budsheets_view,budsheets_edit,budsheets_admin; editor:budsheets_view,budsheets_edit; viewer:budsheets_view
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../');
}

// Require Helper / Model logic
require_once __DIR__ . '/models/budsheets-model.php';

// Auto-load Task files from tasks/ directory
$tasksDir = __DIR__ . '/tasks/';
if (is_dir($tasksDir)) {
    foreach (glob($tasksDir . '*.php') as $taskFile) {
        require_once $taskFile;
    }
}

// Plugin Activation Lifecycle Hook (Executes install.sql)
add_action('plugin_activate_budsheets', function() {
    $sqlFile = __DIR__ . '/sql/install.sql';
    if (file_exists($sqlFile)) {
        try {
            $pdb = budsheets_db();
            if ($pdb) {
                $sqlContent = file_get_contents($sqlFile);
                $statements = array_filter(array_map('trim', explode(';', $sqlContent)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt)) {
                        $pdb->query($stmt);
                    }
                }
            }
        } catch (Throwable $t) {
            error_log("Failed to run BudSheets activation install.sql: " . $t->getMessage());
        }
    }
});

// Plugin Deactivation Lifecycle Hook (Executes uninstall.sql if purge_tables is true)
add_action('plugin_deactivate_budsheets', function($purge_tables = false) {
    if ($purge_tables) {
        $sqlFile = __DIR__ . '/sql/uninstall.sql';
        if (file_exists($sqlFile)) {
            try {
                $pdb = budsheets_db();
                if ($pdb) {
                    $sqlContent = file_get_contents($sqlFile);
                    $statements = array_filter(array_map('trim', explode(';', $sqlContent)));
                    foreach ($statements as $stmt) {
                        if (!empty($stmt)) {
                            $pdb->query($stmt);
                        }
                    }
                }
            } catch (Throwable $t) {
                error_log("Failed to run BudSheets deactivation uninstall.sql: " . $t->getMessage());
            }
        }
    }
});

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
        $children[] = ['label' => 'Budgets', 'icon' => 'fa-solid fa-sitemap', 'route' => 'budsheets_lobs'];
        $children[] = ['label' => 'Settings & Classes', 'icon' => 'fa-solid fa-gear', 'route' => 'budsheets_settings'];
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
                    <div class="small text-muted">Budgets</div>
                    <div class="fs-4 fw-bold"><?= (int)$summary['lob_count'] ?></div>
                </div>
                <div class="col-4">
                    <div class="small text-muted">Budget Items</div>
                    <div class="fs-4 fw-bold"><?= (int)$summary['item_count'] ?></div>
                </div>
                <div class="col-4">
                    <div class="small text-muted">Total Invoiced</div>
                    <div class="fs-4 fw-bold text-primary">$<?= number_format((float)$summary['total_invoiced_cad'], 2) ?> CAD</div>
                </div>
            </div>
        </div>
    </div>
    <?php
});

// Scheduled Weekly Task Scheduler Hooks
add_action('cron_weekly', function() {
    budsheets_send_weekly_summary_report();
});

add_action('plugin_cron_weekly', function() {
    budsheets_send_weekly_summary_report();
});

add_action('schedule_weekly_tasks', function() {
    budsheets_send_weekly_summary_report();
});

add_action('budsheets_weekly_cron', function() {
    budsheets_send_weekly_summary_report();
});

add_action('task_scheduler_run_budsheets_weekly_summary_report', function() {
    budsheets_send_weekly_summary_report();
});

add_action('run_scheduled_task_budsheets_weekly_summary_report', function() {
    budsheets_send_weekly_summary_report();
});

// Framework Scheduler Initialization Hook
add_action('init_scheduler', function($scheduler) {
    if (is_object($scheduler) && method_exists($scheduler, 'registerTask')) {
        $scheduler->registerTask('budsheets_weekly_summary_report', 'budsheets_send_weekly_summary_report', 604800, 'budsheets');
    }
});

// Register Scheduled Tasks for Framework Task Scheduler & Overrides Page
$budsheetsRegisterTask = function($tasks = []) {
    if (!is_array($tasks)) {
        $tasks = [];
    }
    $tasks['budsheets_weekly_summary_report'] = [
        'id'          => 'budsheets_weekly_summary_report',
        'key'         => 'budsheets_weekly_summary_report',
        'name'        => 'BudSheets Weekly Budget Summary Email Report',
        'title'       => 'BudSheets Weekly Budget Summary Email Report',
        'description' => 'Dispatches automated weekly HTML budget summary reports to configured budget notification email recipients and system users.',
        'plugin'      => 'budsheets',
        'schedule'    => 'weekly',
        'interval'    => 604800,
        'hook'        => 'budsheets_weekly_cron',
        'callback'    => 'budsheets_send_weekly_summary_report',
        'enabled'     => true
    ];
    return $tasks;
};

add_filter('register_scheduled_tasks', $budsheetsRegisterTask);
add_filter('task_scheduler_tasks', $budsheetsRegisterTask);
add_filter('scheduled_tasks', $budsheetsRegisterTask);
add_filter('plugin_scheduled_tasks', $budsheetsRegisterTask);
add_filter('get_scheduled_tasks', $budsheetsRegisterTask);

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

    register_route('budsheets_settings', function() {
        if (!has_permission('budsheets_admin')) {
            die('Access Denied');
        }
        require_once __DIR__ . '/views/settings.php';
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

    register_route('budsheets_export_csv', function() {
        if (!has_permission('budsheets_view')) {
            die('Access Denied');
        }
        budsheets_export_items_csv();
    });

    register_route('budsheets_send_test_email', function() {
        if (!has_permission('budsheets_admin')) {
            die('Access Denied: Admin permissions required.');
        }
        if (function_exists('validate_csrf')) {
            validate_csrf();
        }
        $target = trim($_POST['target_email'] ?? ($_GET['email'] ?? ''));
        $fy = isset($_POST['fiscal_year']) ? (int)$_POST['fiscal_year'] : budsheets_get_fiscal_year();
        $res = budsheets_send_weekly_summary_report($fy, $target);
        if ($res) {
            set_flash_message('success', 'Weekly budget summary report email dispatched successfully.');
        } else {
            set_flash_message('danger', 'Failed to dispatch weekly summary report email. Verify email addresses or mail settings.');
        }
        redirect(url_for('budsheets_settings'));
    });

    register_route('budsheets_run_scheduled_task', function() {
        if (!has_permission('budsheets_admin')) {
            die('Access Denied: Admin permissions required.');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('validate_csrf')) {
            validate_csrf();
        }
        $fy = isset($_POST['fiscal_year']) ? (int)$_POST['fiscal_year'] : (isset($_GET['fy']) ? (int)$_GET['fy'] : budsheets_get_fiscal_year());
        $target = trim($_POST['target_email'] ?? ($_GET['email'] ?? ''));
        $res = budsheets_send_weekly_summary_report($fy, $target ?: null);
        if ($res) {
            set_flash_message('success', "Scheduled Task Executed: Weekly budget summary report (FY{$fy}) generated and sent.");
        } else {
            set_flash_message('danger', "Failed to execute weekly budget summary report task.");
        }
        redirect(url_for('budsheets_settings'));
    });
});
