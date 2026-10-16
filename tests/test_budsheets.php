<?php
/**
 * Test suite for BudSheets plugin
 */

// Mock APP_ROOT and framework globals if not present
define('APP_ROOT', __DIR__ . '/../');

// Mock helper functions
$registered_routes = [];
$registered_actions = [];
$registered_filters = [];
$user_permissions = ['budsheets_view', 'budsheets_edit', 'budsheets_admin'];
$plugin_settings_store = [];

function has_permission($perm) {
    global $user_permissions;
    return in_array($perm, $user_permissions);
}

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function url_for($route) {
    return "index.php?route=" . $route;
}

function set_flash_message($type, $msg) {}

function get_plugin_setting($slug, $key, $default = null) {
    global $plugin_settings_store;
    return $plugin_settings_store[$key] ?? $default;
}

function set_plugin_setting($slug, $key, $val) {
    global $plugin_settings_store;
    $plugin_settings_store[$key] = $val;
    return true;
}

function add_filter($hook, $callback) {
    global $registered_filters;
    $registered_filters[$hook][] = $callback;
}

function add_action($hook, $callback) {
    global $registered_actions;
    $registered_actions[$hook][] = $callback;
}

function register_route($route, $callback) {
    global $registered_routes;
    $registered_routes[$route] = $callback;
}

// Mock PDO Database for SQLite memory testing
class MockPDO extends PDO {
    public function __construct() {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
}

class PluginDatabase {
    private $slug;
    private static $pdo = null;

    public function __construct($slug) {
        $this->slug = $slug;
        if (self::$pdo === null) {
            self::$pdo = new MockPDO();
        }
    }

    public function getTableName($table) {
        return "plug_{$this->slug}_{$table}";
    }

    public function query($sql, $params = []) {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function lastInsertId() {
        return self::$pdo->lastInsertId();
    }
}

// Initialize test DB schema (adapted for SQLite memory)
$pdb = new PluginDatabase('budsheets');

$pdb->query("CREATE TABLE plug_budsheets_fiscal_years (
    year INTEGER PRIMARY KEY,
    is_hidden INTEGER DEFAULT 0,
    is_locked INTEGER DEFAULT 0,
    gst_rate REAL DEFAULT NULL,
    pst_rate REAL DEFAULT NULL,
    rate_USD REAL DEFAULT NULL,
    rate_EUR REAL DEFAULT NULL,
    rate_GBP REAL DEFAULT NULL,
    rate_AUD REAL DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);");

$pdb->query("CREATE TABLE plug_budsheets_lines_of_business (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    code TEXT,
    description TEXT,
    fiscal_year INTEGER,
    notification_emails TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);");

$pdb->query("CREATE TABLE plug_budsheets_lob_users (
    lob_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    PRIMARY KEY (lob_id, user_id)
);");

$pdb->query("CREATE TABLE plug_budsheets_classes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lob_id INTEGER DEFAULT NULL,
    name TEXT NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);");

$pdb->query("CREATE TABLE plug_budsheets_class_lobs (
    class_id INTEGER NOT NULL,
    lob_id INTEGER NOT NULL,
    PRIMARY KEY (class_id, lob_id)
);");

$pdb->query("CREATE TABLE plug_budsheets_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lob_id INTEGER NOT NULL,
    vendor TEXT NOT NULL,
    product TEXT NOT NULL,
    currency TEXT DEFAULT 'USD',
    monthly_cost REAL DEFAULT 0.00,
    billing_frequency TEXT DEFAULT 'monthly',
    invoice_month INTEGER DEFAULT NULL,
    tax_type TEXT DEFAULT 'no tax',
    class TEXT,
    description TEXT,
    invoice_type TEXT DEFAULT 'monthly',
    invoice_date DATE,
    contract_start_date DATE,
    contract_end_date DATE,
    long_description TEXT,
    system_directory_link TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);");

$pdb->query("CREATE TABLE plug_budsheets_item_monthly_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    fiscal_year INTEGER NOT NULL,
    month INTEGER NOT NULL,
    amount REAL DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (item_id, fiscal_year, month)
);");

$pdb->query("CREATE TABLE plug_budsheets_contract_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    original_filename TEXT NOT NULL,
    stored_filename TEXT NOT NULL,
    file_size INTEGER DEFAULT 0,
    mime_type TEXT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INTEGER
);");

$pdb->query("CREATE TABLE plug_budsheets_invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    invoice_number TEXT,
    amount_paid REAL DEFAULT 0.00,
    currency TEXT DEFAULT 'USD',
    period_type TEXT DEFAULT 'monthly',
    period_year INTEGER NOT NULL,
    period_month INTEGER,
    payment_date DATE,
    comments TEXT,
    attachment_original_name TEXT,
    attachment_stored_name TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);");

$pdb->query("CREATE TABLE plug_budsheets_comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entity_type TEXT NOT NULL,
    entity_id INTEGER NOT NULL,
    comment_text TEXT NOT NULL,
    created_by INTEGER DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);");

echo "[TEST] Database tables initialized successfully.\n";

// Load Plugin Entry File and Models
require_once __DIR__ . '/../plugins/budsheets/plugin.php';

// Test 1: Managed Financial Years (Add, Hide, Remove)
echo "[TEST] Testing Financial Years Management (Add, Hide, Remove)...\n";
budsheets_add_fiscal_year(2028);
$fys = budsheets_get_managed_fiscal_years(true);
$fyYears = array_column($fys, 'year');
assert(in_array(2028, $fyYears), 'FY2028 added successfully');

budsheets_hide_fiscal_year(2028, true);
$visibleFys = budsheets_get_managed_fiscal_years(false);
$visibleYears = array_column($visibleFys, 'year');
assert(!in_array(2028, $visibleYears), 'FY2028 hidden from visible years');

budsheets_remove_fiscal_year(2028);
$allFysAfterRemove = budsheets_get_managed_fiscal_years(true);
assert(!in_array(2028, array_column($allFysAfterRemove, 'year')), 'FY2028 removed successfully');
echo "  ✓ Financial Years Management tests passed.\n";

// Test 2: Lines of Business / Budgets CRUD & FY Assignment & User Access
echo "[TEST] Testing Budgets operations, Single-FY Assignment & User Access...\n";
budsheets_add_lob('Information Technology 2026', 'IT-26', 'IT Infrastructure FY26', [10, 11], 2026);
budsheets_add_lob('Human Resources 2026', 'HR-26', 'HR Services FY26', [12], 2026);

$lobs = budsheets_get_lobs();
assert(count($lobs) === 2, 'Should return 2 Budgets for admin');
assert((int)$lobs[0]['fiscal_year'] === 2026, 'IT Budget assigned to FY2026');

$itUsers = budsheets_get_lob_users(1);
assert(count($itUsers) === 2, 'IT Budget should have 2 assigned users');
assert(in_array(10, $itUsers) && in_array(11, $itUsers), 'User 10 and 11 assigned to IT');

// Test access filtering for non-admin user (Default permission: No Access)
$user_permissions = ['budsheets_view', 'budsheets_edit']; // strip budsheets_admin
$_SESSION['user_id'] = 12;
$hrUserLobs = budsheets_get_lobs();
assert(count($hrUserLobs) === 1, 'Non-admin user 12 sees only explicitly assigned HR Budget');

$_SESSION['user_id'] = 99; // Unassigned user
$unassignedUserLobs = budsheets_get_lobs();
assert(empty($unassignedUserLobs), 'Unassigned non-admin user 99 gets no access to budgets by default');

// Restore admin permissions
$user_permissions = ['budsheets_view', 'budsheets_edit', 'budsheets_admin'];
$_SESSION['user_id'] = 1;

budsheets_update_lob(1, 'InfoTech 2026', 'IT-UPDATED', 'Updated description', [10], 2026);
$updatedUsers = budsheets_get_lob_users(1);
assert(count($updatedUsers) === 1 && $updatedUsers[0] == 10, 'IT Budget updated user assignment');
echo "  ✓ Budgets CRUD & Single-FY assignment tests passed.\n";

// Test 3: Tax, Currency Conversion, Global vs Per-FY Rates Override
echo "[TEST] Testing tax, currency conversion & per-FY rates overrides...\n";
budsheets_save_settings([
    'starting_fiscal_year'    => '2024',
    'gst_rate'                => '5.0',
    'pst_rate'                => '7.0',
    'rate_USD'                => '1.35',
    'rate_EUR'                => '1.45',
    'rate_CAD'                => '1.0',
    'fiscal_year_start_month' => '9',
    'fiscal_year_start_day'   => '1'
]);

// Set custom rate overrides for FY2027
budsheets_save_fy_rates(2027, [
    'gst_rate' => '6.0',
    'pst_rate' => '8.0',
    'rate_USD' => '1.40'
]);

$fy2026Settings = budsheets_get_settings(2026);
assert($fy2026Settings['rate_USD'] == 1.35, 'FY2026 inherits default USD rate 1.35');

$fy2027Settings = budsheets_get_settings(2027);
assert($fy2027Settings['rate_USD'] == 1.40, 'FY2027 uses custom USD rate override 1.40');
assert($fy2027Settings['gst_rate'] == 6.0, 'FY2027 uses custom GST rate override 6.0%');

$conv2026 = budsheets_convert_to_cad(100.00, 'USD', 2026);
assert($conv2026 == 135.00, '$100 USD in FY2026 converts to $135.00 CAD');

$conv2027 = budsheets_convert_to_cad(100.00, 'USD', 2027);
assert($conv2027 == 140.00, '$100 USD in FY2027 converts to $140.00 CAD');

echo "  ✓ Tax, Currency conversion & Per-FY rate override tests passed.\n";

// Test 4: Budget Year Lock/Unlock Operations
echo "[TEST] Testing Fiscal Year Locking functionality...\n";
assert(budsheets_is_fiscal_year_locked(2025) === false, 'FY2025 initially unlocked');
budsheets_set_fiscal_year_lock(2025, true);
assert(budsheets_is_fiscal_year_locked(2025) === true, 'FY2025 locked successfully');
budsheets_set_fiscal_year_lock(2025, false);
assert(budsheets_is_fiscal_year_locked(2025) === false, 'FY2025 unlocked successfully');
echo "  ✓ Fiscal Year Locking tests passed.\n";

// Test 5: Expense Classes CRUD & Multi-Budget Assignment
echo "[TEST] Testing Expense Classes operations & Multi-Budget associations...\n";
$classId1 = budsheets_add_class([1, 2], 'Shared Software Subscriptions', 'SaaS and cloud software licenses used across IT and HR');
$classId2 = budsheets_add_class(1, 'Hardware Purchase', 'Servers and laptops');
$classId3 = budsheets_add_class(2, 'Recruitment Services', 'Headhunting and agency fees');

$itClasses = budsheets_get_classes_by_lob(1);
assert(count($itClasses) === 2, 'IT Budget should have 2 classes (Shared Subscriptions & Hardware)');

$hrClasses = budsheets_get_classes_by_lob(2);
assert(count($hrClasses) === 2, 'HR Budget should have 2 classes (Shared Subscriptions & Recruitment)');

budsheets_update_class($classId1, [1, 2], 'Cloud & SaaS Subscriptions', 'Updated SaaS description');
$allClasses = budsheets_get_all_classes();
assert(count($allClasses) === 3, 'All classes count is 3');
assert(count($allClasses[0]['lob_ids']) === 2, 'Shared class is assigned to 2 budgets');
echo "  ✓ Multi-Budget Expense Classes tests passed.\n";

// Test 6: File Extension Whitelisting
echo "[TEST] Testing file extension whitelisting...\n";
assert(budsheets_is_allowed_extension('contract.pdf') === true, 'PDF extension allowed');
assert(budsheets_is_allowed_extension('invoice.png') === true, 'PNG extension allowed');
assert(budsheets_is_allowed_extension('exploit.php') === false, 'PHP extension blocked');
assert(budsheets_is_allowed_extension('script.phtml') === false, 'phtml extension blocked');
assert(budsheets_is_allowed_extension('malware.exe') === false, 'exe extension blocked');
echo "  ✓ File extension security check tests passed.\n";

// Test 7: Budget Item CRUD, Annual Carry-over Isolation & FY Monthly Schedules
echo "[TEST] Testing Budget Item CRUD, Annual Carry-over Isolation and FY Monthly Budget Schedules...\n";
$itemData = [
    'lob_id'                => 1,
    'vendor'                => 'Microsoft',
    'product'               => 'Azure Cloud Services',
    'currency'              => 'USD',
    'monthly_cost'          => '12000.00',
    'billing_frequency'     => 'yearly',
    'invoice_month'         => '5',
    'tax_type'              => 'GSTandPST',
    'class'                 => 'Cloud & SaaS Subscriptions',
    'description'           => 'Annual cloud commitment',
    'invoice_type'          => 'year',
    'invoice_date'          => '2026-01-01',
    'contract_start_date'   => '2026-01-01',
    'contract_end_date'     => '2026-12-31',
    'long_description'      => '1-Year Enterprise Agreement with Azure commitments.',
    'system_directory_link' => 'https://systems.domain.com/directory/azure-cloud'
];

$itemId = budsheets_save_item($itemData);
assert($itemId > 0, 'Item should be saved and return valid ID');

$savedItem = budsheets_get_item($itemId);
assert((int)$savedItem['invoice_month'] === 5, 'Vendor invoice month should be saved as 5 (May)');

// FY2026 is in contract range -> gets $12,000 base
$fy2026Calc = budsheets_calculate_item_fy_cost(budsheets_get_item($itemId), 2026);
assert($fy2026Calc['annual_base'] == 12000.00, 'FY2026 annual schedule total base is $12,000');

// FY2030 is outside contract range without explicit schedule -> base is $0 (NOT carried over)
$fy2030Calc = budsheets_calculate_item_fy_cost(budsheets_get_item($itemId), 2030);
assert($fy2030Calc['annual_base'] == 0.00, 'FY2030 financial info is not auto-filled / carried over');

// Save custom monthly schedule for FY2026
$monthlyBreakdown = [
    9 => 1000, 10 => 1000, 11 => 1000, 12 => 1000,
    1 => 1500, 2 => 1500, 3 => 1500, 4 => 1500,
    5 => 500, 6 => 500, 7 => 500, 8 => 500
];
budsheets_save_item_monthly_schedule($itemId, 2026, $monthlyBreakdown);

$savedSched = budsheets_get_item_monthly_schedule($itemId, 2026);
assert(count($savedSched) === 12, '12 months in saved schedule');
assert($savedSched[1] == 1500, 'Jan amount is 1500');

echo "  ✓ Budget Item CRUD, Carry-over Isolation, and FY Monthly Schedule tests passed.\n";

// Test 8: Full vs Soft Budget Cloning
echo "[TEST] Testing Budget Full Clone and Soft Clone functionality...\n";

// Full Clone IT Budget (1) to FY2027
$fullCloneId = budsheets_clone_budget(1, 'InfoTech 2027 Full', 'IT-27-F', 2027, 'full');
assert($fullCloneId > 0, 'Full budget clone created successfully');

$fullClonedItems = budsheets_get_items($fullCloneId);
assert(count($fullClonedItems) === 1, 'Full cloned budget has 1 item');
assert((float)$fullClonedItems[0]['monthly_cost'] === 12000.00, 'Full cloned item retains monthly cost');

$fullClonedSched = budsheets_get_item_monthly_schedule($fullClonedItems[0]['id'], 2027);
assert(count($fullClonedSched) === 12 && $fullClonedSched[1] == 1500, 'Full cloned item retains schedule breakdown for FY2027');

// Soft Clone IT Budget (1) to FY2027
$softCloneId = budsheets_clone_budget(1, 'InfoTech 2027 Soft', 'IT-27-S', 2027, 'soft');
assert($softCloneId > 0, 'Soft budget clone created successfully');

$softClonedItems = budsheets_get_items($softCloneId);
assert(count($softClonedItems) === 1, 'Soft cloned budget has 1 item');
assert((float)$softClonedItems[0]['monthly_cost'] === 0.00, 'Soft cloned item resets monthly cost to $0.00');

$softClonedSched = budsheets_get_item_monthly_schedule($softClonedItems[0]['id'], 2027);
assert(empty($softClonedSched), 'Soft cloned item has no financial schedule');

echo "  ✓ Full vs Soft Budget Cloning tests passed.\n";

// Test 9: Comments on Budgets and Items
echo "[TEST] Testing Comments on Budgets and Items...\n";
$_SESSION['user_id'] = 10;
$budgetCommId = budsheets_add_comment('budget', 1, 'Initial budget allocation discussion note.');
assert($budgetCommId > 0, 'Budget comment created successfully');

$itemCommId = budsheets_add_comment('item', $itemId, 'Vendor contract renewal discussion note.');
assert($itemCommId > 0, 'Item comment created successfully');

$bComments = budsheets_get_comments('budget', 1);
assert(count($bComments) === 1, 'Budget has 1 comment');
assert($bComments[0]['comment_text'] === 'Initial budget allocation discussion note.', 'Budget comment text matches');

$iComments = budsheets_get_comments('item', $itemId);
assert(count($iComments) === 1, 'Item has 1 comment');
assert($iComments[0]['comment_text'] === 'Vendor contract renewal discussion note.', 'Item comment text matches');

budsheets_delete_comment($itemCommId);
$iCommentsAfterDelete = budsheets_get_comments('item', $itemId);
assert(empty($iCommentsAfterDelete), 'Item comment deleted successfully');
echo "  ✓ Budget and Item Comments tests passed.\n";

// Test 10: Invoices CRUD on Locked Fiscal Years & CAD Summary
echo "[TEST] Testing Invoices operations on locked fiscal years and CAD summary totals...\n";

// Lock FY2026
budsheets_set_fiscal_year_lock(2026, true);

$invData1 = [
    'item_id'        => $itemId,
    'invoice_number' => 'INV-2026-001',
    'amount_paid'    => '1000.00',
    'currency'       => 'USD',
    'period_type'    => 'monthly',
    'period_year'    => '2026',
    'period_month'   => '1',
    'payment_date'   => '2026-01-15',
    'comments'       => 'January Cloud Invoice Paid on Locked Year'
];

// Invoices ARE allowed on locked years
$invId1 = budsheets_save_invoice($invData1);
assert($invId1 > 0, 'Invoice can be recorded on a locked fiscal year');

// Unlock FY2026
budsheets_set_fiscal_year_lock(2026, false);

$invData2 = [
    'item_id'        => $itemId,
    'invoice_number' => 'INV-2026-002',
    'amount_paid'    => '1000.00',
    'currency'       => 'USD',
    'period_type'    => 'monthly',
    'period_year'    => '2026',
    'period_month'   => '2',
    'payment_date'   => '2026-02-15',
    'comments'       => 'February Cloud Invoice Paid'
];

$invId2 = budsheets_save_invoice($invData2);

$invoices = budsheets_get_invoices($itemId);
assert(count($invoices) === 2, 'Item should have 2 invoices');

$summary = budsheets_get_dashboard_summary();
assert($summary['total_invoiced_cad'] == 2700.00, 'Total invoiced sum in CAD ($2000 USD * 1.35) is $2700.00 CAD');
echo "  ✓ Invoices CRUD on locked fiscal years & CAD summary tests passed.\n";

// Test 11: Dynamic Routes & Hooks Verification
echo "[TEST] Verifying plugin hooks and registered routes...\n";
assert(isset($registered_routes['budsheets_dashboard']), 'Route budsheets_dashboard is registered');
assert(isset($registered_routes['budsheets_lobs']), 'Route budsheets_lobs is registered');
assert(isset($registered_routes['budsheets_settings']), 'Route budsheets_settings is registered');
assert(isset($registered_routes['budsheets_items']), 'Route budsheets_items is registered');
assert(isset($registered_routes['budsheets_item_detail']), 'Route budsheets_item_detail is registered');
assert(isset($registered_routes['budsheets_download_file']), 'Route budsheets_download_file is registered');
assert(isset($registered_routes['budsheets_export_csv']), 'Route budsheets_export_csv is registered');

assert(isset($registered_filters['theme_nav_links']), 'theme_nav_links filter hook registered');
assert(isset($registered_actions['index_dashboard_widgets']), 'index_dashboard_widgets action hook registered');
assert(isset($registered_filters['register_scheduled_tasks']), 'Task scheduler filter register_scheduled_tasks registered');
assert(isset($registered_filters['task_scheduler_tasks']), 'Task scheduler filter task_scheduler_tasks registered');

echo "  ✓ Framework hooks & route verification tests passed.\n";

// Test 12: View Structure & FY -> Budget -> Item Hierarchy & Access Filtering
echo "[TEST] Testing Financial Year -> Budget -> Item View Hierarchy & Access Filtering...\n";
$fy2026Lobs = budsheets_get_lobs(null, 2026);
$fy2027Lobs = budsheets_get_lobs(null, 2027);
assert(count($fy2026Lobs) === 2, '2 Budgets in FY2026');
assert(count($fy2027Lobs) === 2, '2 Budgets in FY2027 (cloned ones)');

$fy2026Items = budsheets_get_items(null, 2026);
$fy2027Items = budsheets_get_items(null, 2027);
assert(count($fy2026Items) === 1, '1 Item in FY2026');
assert(count($fy2027Items) === 2, '2 Cloned Items in FY2027');

$summary2026 = budsheets_get_dashboard_summary(2026);
assert($summary2026['lob_count'] === 2 && $summary2026['item_count'] === 1, 'FY2026 Dashboard summary correctly isolated');

$summary2027 = budsheets_get_dashboard_summary(2027);
assert($summary2027['lob_count'] === 2 && $summary2027['item_count'] === 2, 'FY2027 Dashboard summary correctly isolated');

echo "  ✓ View structure & FY -> Budget -> Item hierarchy tests passed.\n";

// Test 13: Read-Only and Hide-Costs LOB User Access Levels
echo "[TEST] Testing Read-Only and Read-Only Hide-Costs User Access Levels...\n";
budsheets_set_lob_users(1, [
    20 => 'read_only',
    21 => 'hide_costs'
]);

$level20 = budsheets_get_user_lob_access_level(1, 20);
assert($level20 === 'read_only', 'User 20 has read_only access level on LOB 1');

$level21 = budsheets_get_user_lob_access_level(1, 21);
assert($level21 === 'hide_costs', 'User 21 has hide_costs access level on LOB 1');

$user_permissions = ['budsheets_view', 'budsheets_edit']; // Non-admin user

assert(budsheets_user_can_edit_lob(1, 20) === false, 'User 20 cannot edit LOB 1 (read_only)');
assert(budsheets_user_should_hide_costs(1, 20) === false, 'User 20 does not hide costs');

assert(budsheets_user_can_edit_lob(1, 21) === false, 'User 21 cannot edit LOB 1 (hide_costs)');
assert(budsheets_user_should_hide_costs(1, 21) === true, 'User 21 should hide costs on LOB 1');

// Restore admin permissions and session user
$user_permissions = ['budsheets_view', 'budsheets_edit', 'budsheets_admin'];
$_SESSION['user_id'] = 1;

echo "  ✓ Read-Only and Hide-Costs access level tests passed.\n";

// Test 14: Weekly Summary Report HTML Generation & Email Dispatch
echo "[TEST] Testing Weekly Summary Report HTML Generation & Email Dispatch...\n";
budsheets_update_lob(1, 'InfoTech 2026', 'IT-UPDATED', 'Updated description', [10], 2026, 'it-leads@domain.com, fin@domain.com');
$itLobUpdated = budsheets_get_lob(1);
assert($itLobUpdated['notification_emails'] === 'it-leads@domain.com, fin@domain.com', 'Budget notification emails saved successfully');

$reportHtml = budsheets_generate_weekly_summary_html(2026);
assert(strpos($reportHtml, 'Weekly Budget Summary Report (FY2026)') !== false, 'Report HTML contains correct title');
assert(strpos($reportHtml, 'Budget Allocations Breakdown') !== false, 'Report HTML contains breakdown table');

$sendResult = budsheets_send_weekly_summary_report(2026);
assert($sendResult !== null, 'Weekly summary report email function executed successfully');
echo "  ✓ Weekly Summary Report HTML Generation & Email Dispatch tests passed.\n";

// Test 15: Local Plugin Versioning & Changelog File Check
echo "[TEST] Testing Local Plugin Versioning and CHANGELOG.md...\n";
assert(defined('BUDSHEETS_VERSION'), 'BUDSHEETS_VERSION constant is defined');
assert(BUDSHEETS_VERSION === '1.2.0', 'BUDSHEETS_VERSION is set to 1.2.0');
$changelogFile = __DIR__ . '/../plugins/budsheets/CHANGELOG.md';
assert(file_exists($changelogFile), 'CHANGELOG.md file exists in plugin folder');
$clContent = file_get_contents($changelogFile);
assert(strpos($clContent, '[1.2.0]') !== false, 'CHANGELOG.md contains version 1.2.0 release notes');
echo "  ✓ Plugin versioning and CHANGELOG.md tests passed.\n";

echo "\nALL BUDSHEETS PLUGIN TESTS PASSED SUCCESSFULLY!\n";
