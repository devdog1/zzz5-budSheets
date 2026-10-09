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
$pdb->query("CREATE TABLE plug_budsheets_lines_of_business (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    code TEXT,
    description TEXT,
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
    lob_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);");

$pdb->query("CREATE TABLE plug_budsheets_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lob_id INTEGER NOT NULL,
    vendor TEXT NOT NULL,
    product TEXT NOT NULL,
    currency TEXT DEFAULT 'USD',
    monthly_cost REAL DEFAULT 0.00,
    billing_frequency TEXT DEFAULT 'monthly',
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

echo "[TEST] Database tables initialized successfully.\n";

// Load Plugin Entry File and Models
require_once __DIR__ . '/../plugins/budsheets/plugin.php';

// Test 1: Lines of Business CRUD & User Assignments
echo "[TEST] Testing Lines of Business operations & user assignments...\n";
budsheets_add_lob('Information Technology', 'IT', 'IT Infrastructure and Software', [10, 11]);
budsheets_add_lob('Human Resources', 'HR', 'HR Services', [12]);

$lobs = budsheets_get_lobs();
assert(count($lobs) === 2, 'Should return 2 LOBs for admin');

$itUsers = budsheets_get_lob_users(1);
assert(count($itUsers) === 2, 'IT LOB should have 2 assigned users');
assert(in_array(10, $itUsers) && in_array(11, $itUsers), 'User 10 and 11 assigned to IT');

// Test access filtering for non-admin user
$user_permissions = ['budsheets_view', 'budsheets_edit']; // strip budsheets_admin
$_SESSION['user_id'] = 12;
$hrUserLobs = budsheets_get_lobs();
assert(count($hrUserLobs) === 2, 'Non-admin user 12 sees assigned HR LOB and unassigned open LOBs');

// Restore admin permissions
$user_permissions = ['budsheets_view', 'budsheets_edit', 'budsheets_admin'];
$_SESSION['user_id'] = 1;

budsheets_update_lob(1, 'InfoTech', 'IT-UPDATED', 'Updated description', [10]);
$updatedUsers = budsheets_get_lob_users(1);
assert(count($updatedUsers) === 1 && $updatedUsers[0] == 10, 'IT LOB updated user assignment');
echo "  ✓ LOB CRUD & User assignment tests passed.\n";

// Test 2: Tax & Currency Conversion Settings
echo "[TEST] Testing tax and currency conversion logic...\n";
budsheets_save_settings([
    'gst_rate' => '5.0',
    'pst_rate' => '7.0',
    'rate_USD' => '1.35',
    'rate_EUR' => '1.45',
    'rate_CAD' => '1.0'
]);

$taxCalc = budsheets_calculate_tax(100.00, 'GSTandPST');
assert($taxCalc['gst'] == 5.0, 'GST amount is $5.00');
assert($taxCalc['pst'] == 7.0, 'PST amount is $7.00');
assert($taxCalc['total'] == 112.00, 'Total with GST+PST is $112.00');

$convertedCad = budsheets_convert_to_cad(100.00, 'USD');
assert($convertedCad == 135.00, '$100 USD converts to $135.00 CAD at 1.35 rate');
echo "  ✓ Tax and Currency conversion tests passed.\n";

// Test 3: Expense Classes CRUD & LOB Assignment
echo "[TEST] Testing Expense Classes operations & LOB associations...\n";
$classId1 = budsheets_add_class(1, 'Software Subscriptions', 'SaaS and cloud software licenses');
$classId2 = budsheets_add_class(1, 'Hardware Purchase', 'Servers and laptops');
$classId3 = budsheets_add_class(2, 'Recruitment Services', 'Headhunting and agency fees');

$itClasses = budsheets_get_classes_by_lob(1);
assert(count($itClasses) === 2, 'IT LOB should have 2 classes');
assert($itClasses[0]['name'] === 'Hardware Purchase' || $itClasses[1]['name'] === 'Hardware Purchase', 'Hardware class exists');

budsheets_update_class($classId1, 1, 'Cloud & SaaS Subscriptions', 'Updated SaaS description');
$allClasses = budsheets_get_all_classes();
assert(count($allClasses) === 3, 'All classes count is 3');
echo "  ✓ Expense Classes tests passed.\n";

// Test 4: File Extension Whitelisting
echo "[TEST] Testing file extension whitelisting...\n";
assert(budsheets_is_allowed_extension('contract.pdf') === true, 'PDF extension allowed');
assert(budsheets_is_allowed_extension('invoice.png') === true, 'PNG extension allowed');
assert(budsheets_is_allowed_extension('exploit.php') === false, 'PHP extension blocked');
assert(budsheets_is_allowed_extension('script.phtml') === false, 'phtml extension blocked');
assert(budsheets_is_allowed_extension('malware.exe') === false, 'exe extension blocked');
echo "  ✓ File extension security check tests passed.\n";

// Test 5: Budget Item CRUD, Systems Directory Link & Yearly Billing Frequency
echo "[TEST] Testing Budget Item operations, Systems Directory Link, and Billing Frequency...\n";
$itemData = [
    'lob_id'                => 1,
    'vendor'                => 'Microsoft',
    'product'               => 'Azure Cloud Services',
    'currency'              => 'USD',
    'monthly_cost'          => '12000.00',
    'billing_frequency'     => 'yearly',
    'tax_type'              => 'GSTandPST',
    'class'                 => 'Cloud & SaaS Subscriptions',
    'description'           => 'Annual cloud commitment',
    'invoice_type'          => 'year',
    'invoice_date'          => '2026-01-01',
    'contract_start_date'   => '2026-01-01',
    'contract_end_date'     => '2028-12-31',
    'long_description'      => '3-Year Enterprise Agreement with Azure commitments.',
    'system_directory_link' => 'https://systems.domain.com/directory/azure-cloud'
];

$itemId = budsheets_save_item($itemData);
assert($itemId > 0, 'Item should be saved and return valid ID');

$item = budsheets_get_item($itemId);
assert($item['vendor'] === 'Microsoft', 'Vendor is Microsoft');
assert($item['monthly_cost'] == 12000.00, 'Cost matches');
assert($item['billing_frequency'] === 'yearly', 'Billing frequency is yearly');

$itemCostCalc = budsheets_calculate_item_cost_and_tax((float)$item['monthly_cost'], $item['billing_frequency'], $item['tax_type']);
assert($itemCostCalc['monthly_base'] == 1000.00, 'Monthly base calculated from $12,000 yearly is $1,000');
assert($itemCostCalc['annual_base'] == 12000.00, 'Annual base is $12,000');
echo "  ✓ Budget Item CRUD, Systems Directory Link, and Yearly Billing Frequency tests passed.\n";

// Test 6: Invoices CRUD & CAD Conversion Summary
echo "[TEST] Testing Invoices operations and CAD summary totals...\n";
$invData1 = [
    'item_id'        => $itemId,
    'invoice_number' => 'INV-2026-001',
    'amount_paid'    => '1000.00',
    'currency'       => 'USD',
    'period_type'    => 'monthly',
    'period_year'    => '2026',
    'period_month'   => '1',
    'payment_date'   => '2026-01-15',
    'comments'       => 'January Cloud Invoice Paid'
];

$invId1 = budsheets_save_invoice($invData1);

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
echo "  ✓ Invoices CRUD & CAD summary tests passed.\n";

// Test 7: Dynamic Routes & Hooks Verification
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

echo "  ✓ Framework hooks & route verification tests passed.\n";

echo "\nALL BUDSHEETS PLUGIN TESTS PASSED SUCCESSFULLY!\n";
