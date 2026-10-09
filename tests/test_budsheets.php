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

$pdb->query("CREATE TABLE plug_budsheets_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lob_id INTEGER NOT NULL,
    vendor TEXT NOT NULL,
    product TEXT NOT NULL,
    currency TEXT DEFAULT 'USD',
    monthly_cost REAL DEFAULT 0.00,
    tax_type TEXT DEFAULT 'no tax',
    class TEXT,
    description TEXT,
    invoice_type TEXT DEFAULT 'monthly',
    invoice_date DATE,
    contract_start_date DATE,
    contract_end_date DATE,
    long_description TEXT,
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

// Test 1: Lines of Business CRUD
echo "[TEST] Testing Lines of Business operations...\n";
budsheets_add_lob('Information Technology', 'IT', 'IT Infrastructure and Software');
budsheets_add_lob('Human Resources', 'HR', 'HR Services');

$lobs = budsheets_get_lobs();
assert(count($lobs) === 2, 'Should return 2 LOBs');
assert($lobs[0]['name'] === 'Human Resources' || $lobs[1]['name'] === 'Human Resources', 'HR LOB exists');

$itLob = budsheets_get_lob(1);
assert($itLob['code'] === 'IT', 'IT LOB code is correct');

budsheets_update_lob(1, 'InfoTech', 'IT-UPDATED', 'Updated description');
$updatedLob = budsheets_get_lob(1);
assert($updatedLob['name'] === 'InfoTech', 'LOB name updated');
echo "  ✓ LOB CRUD tests passed.\n";

// Test 2: Budget Item CRUD
echo "[TEST] Testing Budget Item operations...\n";
$itemData = [
    'lob_id'              => 1,
    'vendor'              => 'Microsoft',
    'product'             => 'Azure Cloud Services',
    'currency'            => 'USD',
    'monthly_cost'        => '1250.50',
    'tax_type'            => 'GSTandPST',
    'class'               => 'Cloud Computing',
    'description'         => 'Monthly cloud consumption',
    'invoice_type'        => 'monthly',
    'invoice_date'        => '2026-01-01',
    'contract_start_date' => '2026-01-01',
    'contract_end_date'   => '2028-12-31',
    'long_description'    => '3-Year Enterprise Agreement with Azure commitments.'
];

$itemId = budsheets_save_item($itemData);
assert($itemId > 0, 'Item should be saved and return valid ID');

$item = budsheets_get_item($itemId);
assert($item['vendor'] === 'Microsoft', 'Vendor is Microsoft');
assert($item['monthly_cost'] == 1250.50, 'Monthly cost matches');
assert($item['tax_type'] === 'GSTandPST', 'Tax type matches');
assert($item['invoice_type'] === 'monthly', 'Invoice type matches');
echo "  ✓ Budget Item CRUD tests passed.\n";

// Test 3: Invoices CRUD against Item
echo "[TEST] Testing Invoices operations...\n";
$invData1 = [
    'item_id'        => $itemId,
    'invoice_number' => 'INV-2026-001',
    'amount_paid'    => '1250.50',
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
    'amount_paid'    => '1250.50',
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
assert($summary['total_invoiced'] == 2501.00, 'Total invoiced sum is correct');
echo "  ✓ Invoices CRUD tests passed.\n";

// Test 4: Dynamic Routes & Hooks Verification
echo "[TEST] Verifying plugin hooks and registered routes...\n";
assert(isset($registered_routes['budsheets_dashboard']), 'Route budsheets_dashboard is registered');
assert(isset($registered_routes['budsheets_lobs']), 'Route budsheets_lobs is registered');
assert(isset($registered_routes['budsheets_items']), 'Route budsheets_items is registered');
assert(isset($registered_routes['budsheets_item_detail']), 'Route budsheets_item_detail is registered');
assert(isset($registered_routes['budsheets_download_file']), 'Route budsheets_download_file is registered');

assert(isset($registered_filters['theme_nav_links']), 'theme_nav_links filter hook registered');
assert(isset($registered_actions['index_dashboard_widgets']), 'index_dashboard_widgets action hook registered');

echo "  ✓ Framework hooks & route verification tests passed.\n";

echo "\nALL BUDSHEETS PLUGIN TESTS PASSED SUCCESSFULLY!\n";
