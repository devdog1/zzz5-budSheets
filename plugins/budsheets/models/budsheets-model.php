<?php
/**
 * BudSheets Models & Helper functions
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

if (file_exists(APP_ROOT . 'PluginDatabase.php')) {
    require_once APP_ROOT . 'PluginDatabase.php';
}

/**
 * Ensure all required database tables exist
 */
function budsheets_ensure_tables_exist($pdb) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $lobTable = $pdb->getTableName('lines_of_business');
        $pdb->query("SELECT 1 FROM {$lobTable} LIMIT 1");
    } catch (Exception $e) {
        $sqlFile = __DIR__ . '/../sql/install.sql';
        if (file_exists($sqlFile)) {
            $sqlContent = file_get_contents($sqlFile);
            $statements = array_filter(array_map('trim', explode(';', $sqlContent)));
            foreach ($statements as $stmt) {
                if (!empty($stmt)) {
                    try {
                        $pdb->query($stmt);
                    } catch (Exception $ex) {
                        // Ignore if table already exists
                    }
                }
            }
        }
    }
}

/**
 * Get PluginDatabase instance for budsheets
 */
function budsheets_db() {
    static $pdb = null;
    if ($pdb === null) {
        if (class_exists('PluginDatabase')) {
            $pdb = new PluginDatabase('budsheets');
            budsheets_ensure_tables_exist($pdb);
        } else {
            $pdb = null;
        }
    }
    return $pdb;
}

/**
 * Get last insert ID safely across PluginDatabase versions or direct PDO
 */
function budsheets_last_insert_id($pdb) {
    if (method_exists($pdb, 'lastInsertId')) {
        return $pdb->lastInsertId();
    }
    if (function_exists('get_db_connection')) {
        return get_db_connection()->lastInsertId();
    }
    return 0;
}

/**
 * Get upload directory path
 */
function budsheets_upload_dir() {
    $dir = __DIR__ . '/../uploads/';
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Validate safe file extension
 */
function budsheets_is_allowed_extension($filename) {
    $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'webp'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowed, true);
}

// ==========================================
// Lines of Business (LOB) Operations
// ==========================================

function budsheets_get_lobs($user_id = null) {
    $pdb = budsheets_db();
    if (!$pdb) return [];
    $table = $pdb->getTableName('lines_of_business');
    $userLobTable = $pdb->getTableName('lob_users');

    // Admin users can see all LOBs
    if (has_permission('budsheets_admin')) {
        return $pdb->query("SELECT * FROM {$table} ORDER BY name ASC")->fetchAll();
    }

    // Non-admin users see LOBs assigned to them OR open LOBs (no specific user restrictions)
    $currentUserId = ($user_id !== null) ? $user_id : ($_SESSION['user_id'] ?? 0);
    $sql = "SELECT l.*
            FROM {$table} l
            WHERE l.id IN (SELECT lob_id FROM {$userLobTable} WHERE user_id = ?)
               OR l.id NOT IN (SELECT DISTINCT lob_id FROM {$userLobTable})
            ORDER BY l.name ASC";
    return $pdb->query($sql, [(int)$currentUserId])->fetchAll();
}

function budsheets_user_has_lob_access($lob_id, $user_id = null) {
    if (has_permission('budsheets_admin')) {
        return true;
    }
    $currentUserId = ($user_id !== null) ? $user_id : ($_SESSION['user_id'] ?? 0);
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $userLobTable = $pdb->getTableName('lob_users');

    $sql = "SELECT 1 FROM {$userLobTable} WHERE lob_id = ? AND user_id = ?
            UNION
            SELECT 1 WHERE NOT EXISTS (SELECT 1 FROM {$userLobTable} WHERE lob_id = ?)";
    $row = $pdb->query($sql, [(int)$lob_id, (int)$currentUserId, (int)$lob_id])->fetch();
    return !empty($row);
}

function budsheets_get_lob($id) {
    $pdb = budsheets_db();
    if (!$pdb) return null;
    $table = $pdb->getTableName('lines_of_business');
    return $pdb->query("SELECT * FROM {$table} WHERE id = ?", [(int)$id])->fetch();
}

function budsheets_add_lob($name, $code = '', $description = '', $user_ids = []) {
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $table = $pdb->getTableName('lines_of_business');
    $pdb->query("INSERT INTO {$table} (name, code, description) VALUES (?, ?, ?)", [
        trim($name),
        trim($code),
        trim($description)
    ]);
    $lob_id = budsheets_last_insert_id($pdb);

    if ($lob_id && !empty($user_ids)) {
        budsheets_set_lob_users($lob_id, $user_ids);
    }
    return $lob_id;
}

function budsheets_update_lob($id, $name, $code = '', $description = '', $user_ids = null) {
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $table = $pdb->getTableName('lines_of_business');
    $res = $pdb->query("UPDATE {$table} SET name = ?, code = ?, description = ? WHERE id = ?", [
        trim($name),
        trim($code),
        trim($description),
        (int)$id
    ]);

    if ($user_ids !== null) {
        budsheets_set_lob_users($id, $user_ids);
    }
    return $res;
}

function budsheets_delete_lob($id) {
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $table = $pdb->getTableName('lines_of_business');
    return $pdb->query("DELETE FROM {$table} WHERE id = ?", [(int)$id]);
}

// ==========================================
// LOB User Access Assignments
// ==========================================

function budsheets_get_lob_users($lob_id) {
    $pdb = budsheets_db();
    if (!$pdb) return [];
    $table = $pdb->getTableName('lob_users');
    $rows = $pdb->query("SELECT user_id FROM {$table} WHERE lob_id = ?", [(int)$lob_id])->fetchAll();
    return array_column($rows, 'user_id');
}

function budsheets_set_lob_users($lob_id, $user_ids = []) {
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $table = $pdb->getTableName('lob_users');

    $pdb->query("DELETE FROM {$table} WHERE lob_id = ?", [(int)$lob_id]);

    if (!empty($user_ids)) {
        foreach ($user_ids as $uid) {
            $pdb->query("INSERT INTO {$table} (lob_id, user_id) VALUES (?, ?)", [(int)$lob_id, (int)$uid]);
        }
    }
    return true;
}

function budsheets_get_all_system_users() {
    try {
        $pdb = budsheets_db();
        if ($pdb) {
            return $pdb->query("SELECT id, name, email FROM users ORDER BY name ASC")->fetchAll();
        }
    } catch (Exception $e) {
        // Fallback if users table doesn't exist
    }
    return [];
}

// ==========================================
// Classes Operations
// ==========================================

function budsheets_get_classes_by_lob($lob_id) {
    $pdb = budsheets_db();
    if (!$pdb || !$lob_id) return [];
    $table = $pdb->getTableName('classes');
    return $pdb->query("SELECT * FROM {$table} WHERE lob_id = ? ORDER BY name ASC", [(int)$lob_id])->fetchAll();
}

function budsheets_get_all_classes() {
    $pdb = budsheets_db();
    if (!$pdb) return [];
    $classTable = $pdb->getTableName('classes');
    $lobTable = $pdb->getTableName('lines_of_business');
    $sql = "SELECT c.*, l.name as lob_name
            FROM {$classTable} c
            LEFT JOIN {$lobTable} l ON c.lob_id = l.id
            ORDER BY l.name ASC, c.name ASC";
    return $pdb->query($sql)->fetchAll();
}

function budsheets_add_class($lob_id, $name, $description = '') {
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $table = $pdb->getTableName('classes');
    $pdb->query("INSERT INTO {$table} (lob_id, name, description) VALUES (?, ?, ?)", [
        (int)$lob_id,
        trim($name),
        trim($description)
    ]);
    return budsheets_last_insert_id($pdb);
}

function budsheets_update_class($id, $lob_id, $name, $description = '') {
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $table = $pdb->getTableName('classes');
    return $pdb->query("UPDATE {$table} SET lob_id = ?, name = ?, description = ? WHERE id = ?", [
        (int)$lob_id,
        trim($name),
        trim($description),
        (int)$id
    ]);
}

function budsheets_delete_class($id) {
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $table = $pdb->getTableName('classes');
    return $pdb->query("DELETE FROM {$table} WHERE id = ?", [(int)$id]);
}

// ==========================================
// Budget Items Operations
// ==========================================

function budsheets_get_items($lob_id = null) {
    $pdb = budsheets_db();
    if (!$pdb) return [];
    $itemsTable = $pdb->getTableName('items');
    $lobTable = $pdb->getTableName('lines_of_business');
    $userLobTable = $pdb->getTableName('lob_users');

    $sql = "SELECT i.*, l.name as lob_name, l.code as lob_code
            FROM {$itemsTable} i
            LEFT JOIN {$lobTable} l ON i.lob_id = l.id";
    $params = [];
    $where = [];

    if ($lob_id) {
        $where[] = "i.lob_id = ?";
        $params[] = (int)$lob_id;
    }

    if (!has_permission('budsheets_admin')) {
        $currentUserId = $_SESSION['user_id'] ?? 0;
        $where[] = "(i.lob_id IN (SELECT lob_id FROM {$userLobTable} WHERE user_id = ?) OR i.lob_id NOT IN (SELECT DISTINCT lob_id FROM {$userLobTable}))";
        $params[] = (int)$currentUserId;
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY i.created_at DESC";
    return $pdb->query($sql, $params)->fetchAll();
}

function budsheets_get_item($id) {
    $pdb = budsheets_db();
    if (!$pdb) return null;
    $itemsTable = $pdb->getTableName('items');
    $lobTable = $pdb->getTableName('lines_of_business');
    $userLobTable = $pdb->getTableName('lob_users');

    $sql = "SELECT i.*, l.name as lob_name, l.code as lob_code
            FROM {$itemsTable} i
            LEFT JOIN {$lobTable} l ON i.lob_id = l.id
            WHERE i.id = ?";
    $params = [(int)$id];

    if (!has_permission('budsheets_admin')) {
        $currentUserId = $_SESSION['user_id'] ?? 0;
        $sql .= " AND (i.lob_id IN (SELECT lob_id FROM {$userLobTable} WHERE user_id = ?) OR i.lob_id NOT IN (SELECT DISTINCT lob_id FROM {$userLobTable}))";
        $params[] = (int)$currentUserId;
    }

    return $pdb->query($sql, $params)->fetch();
}

function budsheets_save_item($data, $item_id = null) {
    $pdb = budsheets_db();
    if (!$pdb) return false;

    $lobId = (int)$data['lob_id'];
    if (!budsheets_user_has_lob_access($lobId)) {
        die('Access Denied: You do not have permissions for this Line of Business.');
    }

    $table = $pdb->getTableName('items');
    $userId = $_SESSION['user_id'] ?? null;

    $fields = [
        'lob_id'              => $lobId,
        'vendor'              => trim($data['vendor']),
        'product'             => trim($data['product']),
        'currency'            => trim($data['currency'] ?? 'USD'),
        'monthly_cost'        => (float)($data['monthly_cost'] ?? 0),
        'tax_type'            => $data['tax_type'] ?? 'no tax',
        'class'               => trim($data['class'] ?? ''),
        'description'         => trim($data['description'] ?? ''),
        'invoice_type'        => $data['invoice_type'] ?? 'monthly',
        'invoice_date'        => !empty($data['invoice_date']) ? $data['invoice_date'] : null,
        'contract_start_date' => !empty($data['contract_start_date']) ? $data['contract_start_date'] : null,
        'contract_end_date'   => !empty($data['contract_end_date']) ? $data['contract_end_date'] : null,
        'long_description'    => trim($data['long_description'] ?? '')
    ];

    if ($item_id) {
        $existing = budsheets_get_item($item_id);
        if (!$existing) {
            die('Access Denied: Target item not found or access restricted.');
        }

        $sql = "UPDATE {$table} SET
                    lob_id = ?, vendor = ?, product = ?, currency = ?, monthly_cost = ?,
                    tax_type = ?, class = ?, description = ?, invoice_type = ?, invoice_date = ?,
                    contract_start_date = ?, contract_end_date = ?, long_description = ?
                WHERE id = ?";
        $params = array_values($fields);
        $params[] = (int)$item_id;
        $pdb->query($sql, $params);
        return (int)$item_id;
    } else {
        $fields['created_by'] = $userId;
        $sql = "INSERT INTO {$table} (lob_id, vendor, product, currency, monthly_cost, tax_type, class, description, invoice_type, invoice_date, contract_start_date, contract_end_date, long_description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $pdb->query($sql, array_values($fields));
        return budsheets_last_insert_id($pdb);
    }
}

function budsheets_delete_item($id) {
    $item = budsheets_get_item($id);
    if (!$item) {
        die('Access Denied: Budget item not found or access restricted.');
    }

    $pdb = budsheets_db();
    if (!$pdb) return false;

    $files = budsheets_get_contract_files($id);
    $uploadDir = budsheets_upload_dir();
    foreach ($files as $f) {
        if (!empty($f['stored_filename']) && file_exists($uploadDir . $f['stored_filename'])) {
            @unlink($uploadDir . $f['stored_filename']);
        }
    }

    $invoices = budsheets_get_invoices($id);
    foreach ($invoices as $inv) {
        if (!empty($inv['attachment_stored_name']) && file_exists($uploadDir . $inv['attachment_stored_name'])) {
            @unlink($uploadDir . $inv['attachment_stored_name']);
        }
    }

    $table = $pdb->getTableName('items');
    return $pdb->query("DELETE FROM {$table} WHERE id = ?", [(int)$id]);
}

// ==========================================
// Contract Files Operations
// ==========================================

function budsheets_get_contract_files($item_id) {
    $pdb = budsheets_db();
    if (!$pdb) return [];
    $table = $pdb->getTableName('contract_files');
    return $pdb->query("SELECT * FROM {$table} WHERE item_id = ? ORDER BY uploaded_at DESC", [(int)$item_id])->fetchAll();
}

function budsheets_get_contract_file($file_id) {
    $pdb = budsheets_db();
    if (!$pdb) return null;
    $table = $pdb->getTableName('contract_files');
    return $pdb->query("SELECT * FROM {$table} WHERE id = ?", [(int)$file_id])->fetch();
}

function budsheets_save_contract_files($item_id, $files_array) {
    $item = budsheets_get_item($item_id);
    if (!$item) {
        die('Access Denied: Budget item not found or access restricted.');
    }

    $pdb = budsheets_db();
    if (!$pdb || empty($files_array['name'][0])) return;

    $uploadDir = budsheets_upload_dir();
    $table = $pdb->getTableName('contract_files');
    $userId = $_SESSION['user_id'] ?? null;

    $count = count($files_array['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($files_array['error'][$i] === UPLOAD_ERR_OK) {
            $origName = basename($files_array['name'][$i]);

            if (!budsheets_is_allowed_extension($origName)) {
                continue; // Skip dangerous or unallowed extensions
            }

            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $storedName = 'contract_' . (int)$item_id . '_' . uniqid() . '.' . $ext;
            $targetPath = $uploadDir . $storedName;

            if (move_uploaded_file($files_array['tmp_name'][$i], $targetPath)) {
                $pdb->query(
                    "INSERT INTO {$table} (item_id, original_filename, stored_filename, file_size, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)",
                    [
                        (int)$item_id,
                        $origName,
                        $storedName,
                        (int)$files_array['size'][$i],
                        $files_array['type'][$i],
                        $userId
                    ]
                );
            }
        }
    }
}

function budsheets_delete_contract_file($file_id) {
    $file = budsheets_get_contract_file($file_id);
    if ($file) {
        $item = budsheets_get_item($file['item_id']);
        if (!$item) {
            die('Access Denied: Associated item not found or access restricted.');
        }

        $pdb = budsheets_db();
        if (!$pdb) return false;

        $path = budsheets_upload_dir() . $file['stored_filename'];
        if (file_exists($path)) {
            @unlink($path);
        }
        $table = $pdb->getTableName('contract_files');
        return $pdb->query("DELETE FROM {$table} WHERE id = ?", [(int)$file_id]);
    }
    return false;
}

// ==========================================
// Invoices Operations
// ==========================================

function budsheets_get_invoices($item_id) {
    $pdb = budsheets_db();
    if (!$pdb) return [];
    $table = $pdb->getTableName('invoices');
    return $pdb->query("SELECT * FROM {$table} WHERE item_id = ? ORDER BY period_year DESC, period_month DESC, payment_date DESC", [(int)$item_id])->fetchAll();
}

function budsheets_get_invoice($invoice_id) {
    $pdb = budsheets_db();
    if (!$pdb) return null;
    $table = $pdb->getTableName('invoices');
    return $pdb->query("SELECT * FROM {$table} WHERE id = ?", [(int)$invoice_id])->fetch();
}

function budsheets_save_invoice($data, $file = null, $invoice_id = null) {
    $itemId = (int)$data['item_id'];
    $item = budsheets_get_item($itemId);
    if (!$item) {
        die('Access Denied: Target budget item not found or access restricted.');
    }

    $pdb = budsheets_db();
    if (!$pdb) return false;

    $table = $pdb->getTableName('invoices');
    $userId = $_SESSION['user_id'] ?? null;
    $uploadDir = budsheets_upload_dir();

    $origName = null;
    $storedName = null;

    if ($file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
        $candidateName = basename($file['name']);
        if (budsheets_is_allowed_extension($candidateName)) {
            $origName = $candidateName;
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $storedName = 'invoice_' . $itemId . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($file['tmp_name'], $uploadDir . $storedName);
        }
    }

    if ($invoice_id) {
        $existing = budsheets_get_invoice($invoice_id);
        if (!$origName && $existing) {
            $origName = $existing['attachment_original_name'];
            $storedName = $existing['attachment_stored_name'];
        } elseif ($origName && $existing && !empty($existing['attachment_stored_name'])) {
            @unlink($uploadDir . $existing['attachment_stored_name']);
        }

        $sql = "UPDATE {$table} SET
                    invoice_number = ?, amount_paid = ?, currency = ?, period_type = ?,
                    period_year = ?, period_month = ?, payment_date = ?, comments = ?,
                    attachment_original_name = ?, attachment_stored_name = ?
                WHERE id = ?";
        $pdb->query($sql, [
            trim($data['invoice_number'] ?? ''),
            (float)($data['amount_paid'] ?? 0),
            trim($data['currency'] ?? 'USD'),
            $data['period_type'] ?? 'monthly',
            (int)$data['period_year'],
            !empty($data['period_month']) ? (int)$data['period_month'] : null,
            !empty($data['payment_date']) ? $data['payment_date'] : null,
            trim($data['comments'] ?? ''),
            $origName,
            $storedName,
            (int)$invoice_id
        ]);
        return (int)$invoice_id;
    } else {
        $sql = "INSERT INTO {$table} (item_id, invoice_number, amount_paid, currency, period_type, period_year, period_month, payment_date, comments, attachment_original_name, attachment_stored_name, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $pdb->query($sql, [
            $itemId,
            trim($data['invoice_number'] ?? ''),
            (float)($data['amount_paid'] ?? 0),
            trim($data['currency'] ?? 'USD'),
            $data['period_type'] ?? 'monthly',
            (int)$data['period_year'],
            !empty($data['period_month']) ? (int)$data['period_month'] : null,
            !empty($data['payment_date']) ? $data['payment_date'] : null,
            trim($data['comments'] ?? ''),
            $origName,
            $storedName,
            $userId
        ]);
        return budsheets_last_insert_id($pdb);
    }
}

function budsheets_delete_invoice($invoice_id) {
    $inv = budsheets_get_invoice($invoice_id);
    if ($inv) {
        $item = budsheets_get_item($inv['item_id']);
        if (!$item) {
            die('Access Denied: Associated item not found or access restricted.');
        }

        $pdb = budsheets_db();
        if (!$pdb) return false;

        if (!empty($inv['attachment_stored_name'])) {
            $path = budsheets_upload_dir() . $inv['attachment_stored_name'];
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        $table = $pdb->getTableName('invoices');
        return $pdb->query("DELETE FROM {$table} WHERE id = ?", [(int)$invoice_id]);
    }
    return false;
}

// ==========================================
// Dashboard & Summary Helper
// ==========================================

function budsheets_get_dashboard_summary() {
    $pdb = budsheets_db();
    if (!$pdb) {
        return ['lob_count' => 0, 'item_count' => 0, 'total_invoiced' => 0];
    }

    $lobs = budsheets_get_lobs();
    $items = budsheets_get_items();
    $invTable = $pdb->getTableName('invoices');

    $lobCount = count($lobs);
    $itemCount = count($items);

    $totalInvoiced = 0;
    if (!empty($items)) {
        $itemIds = array_column($items, 'id');
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $totalInvoiced = (float)$pdb->query("SELECT SUM(amount_paid) as total FROM {$invTable} WHERE item_id IN ({$placeholders})", $itemIds)->fetch()['total'];
    }

    return [
        'lob_count' => $lobCount,
        'item_count' => $itemCount,
        'total_invoiced' => $totalInvoiced
    ];
}

// ==========================================
// File Download & CSV Export Handlers
// ==========================================

function budsheets_handle_file_download() {
    $fileType = $_GET['file_type'] ?? '';
    $fileId = (int)($_GET['file_id'] ?? 0);

    if (!$fileId) {
        die('Invalid file parameters');
    }

    $storedName = null;
    $origName = null;
    $itemId = null;

    if ($fileType === 'contract') {
        $file = budsheets_get_contract_file($fileId);
        if ($file) {
            $storedName = $file['stored_filename'];
            $origName = $file['original_filename'];
            $itemId = $file['item_id'];
        }
    } elseif ($fileType === 'invoice') {
        $inv = budsheets_get_invoice($fileId);
        if ($inv) {
            $storedName = $inv['attachment_stored_name'];
            $origName = $inv['attachment_original_name'];
            $itemId = $inv['item_id'];
        }
    }

    if (!$storedName || !$origName || !$itemId) {
        die('File not found');
    }

    // Access check: verify user has access to item's LOB
    $item = budsheets_get_item($itemId);
    if (!$item) {
        die('Access Denied: You do not have permissions to download files for this item.');
    }

    $filePath = budsheets_upload_dir() . $storedName;
    if (!file_exists($filePath)) {
        die('File does not exist on disk');
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($origName) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

function budsheets_export_items_csv() {
    $lob_id = isset($_GET['lob_id']) ? (int)$_GET['lob_id'] : null;
    $items = budsheets_get_items($lob_id);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=operational_budget_items_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'ID',
        'Line of Business',
        'Vendor',
        'Product',
        'Class',
        'Currency',
        'Monthly Cost',
        'Tax Type',
        'Invoice Schedule',
        'Invoice Date',
        'Contract Start',
        'Contract End',
        'Description'
    ]);

    foreach ($items as $item) {
        fputcsv($output, [
            $item['id'],
            $item['lob_name'],
            $item['vendor'],
            $item['product'],
            $item['class'],
            $item['currency'],
            $item['monthly_cost'],
            $item['tax_type'],
            $item['invoice_type'],
            $item['invoice_date'],
            $item['contract_start_date'],
            $item['contract_end_date'],
            $item['description']
        ]);
    }

    fclose($output);
    exit;
}
