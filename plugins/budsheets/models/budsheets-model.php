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
 * Get PluginDatabase instance for budsheets
 */
function budsheets_db() {
    static $pdb = null;
    if ($pdb === null) {
        if (class_exists('PluginDatabase')) {
            $pdb = new PluginDatabase('budsheets');
        } else {
            $pdb = null;
        }
    }
    return $pdb;
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

// ==========================================
// Lines of Business (LOB) Operations
// ==========================================

function budsheets_get_lobs() {
    $pdb = budsheets_db();
    if (!$pdb) return [];
    $table = $pdb->getTableName('lines_of_business');
    return $pdb->query("SELECT * FROM {$table} ORDER BY name ASC")->fetchAll();
}

function budsheets_get_lob($id) {
    $pdb = budsheets_db();
    if (!$pdb) return null;
    $table = $pdb->getTableName('lines_of_business');
    return $pdb->query("SELECT * FROM {$table} WHERE id = ?", [(int)$id])->fetch();
}

function budsheets_add_lob($name, $code = '', $description = '') {
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $table = $pdb->getTableName('lines_of_business');
    return $pdb->query("INSERT INTO {$table} (name, code, description) VALUES (?, ?, ?)", [
        trim($name),
        trim($code),
        trim($description)
    ]);
}

function budsheets_update_lob($id, $name, $code = '', $description = '') {
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $table = $pdb->getTableName('lines_of_business');
    return $pdb->query("UPDATE {$table} SET name = ?, code = ?, description = ? WHERE id = ?", [
        trim($name),
        trim($code),
        trim($description),
        (int)$id
    ]);
}

function budsheets_delete_lob($id) {
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $table = $pdb->getTableName('lines_of_business');
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

    $sql = "SELECT i.*, l.name as lob_name, l.code as lob_code
            FROM {$itemsTable} i
            LEFT JOIN {$lobTable} l ON i.lob_id = l.id";
    $params = [];

    if ($lob_id) {
        $sql .= " WHERE i.lob_id = ?";
        $params[] = (int)$lob_id;
    }

    $sql .= " ORDER BY i.created_at DESC";
    return $pdb->query($sql, $params)->fetchAll();
}

function budsheets_get_item($id) {
    $pdb = budsheets_db();
    if (!$pdb) return null;
    $itemsTable = $pdb->getTableName('items');
    $lobTable = $pdb->getTableName('lines_of_business');

    return $pdb->query(
        "SELECT i.*, l.name as lob_name, l.code as lob_code
         FROM {$itemsTable} i
         LEFT JOIN {$lobTable} l ON i.lob_id = l.id
         WHERE i.id = ?",
        [(int)$id]
    )->fetch();
}

function budsheets_save_item($data, $item_id = null) {
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $table = $pdb->getTableName('items');

    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    $fields = [
        'lob_id'              => (int)$data['lob_id'],
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
        return $pdb->lastInsertId();
    }
}

function budsheets_delete_item($id) {
    $pdb = budsheets_db();
    if (!$pdb) return false;

    // Also delete uploaded contract files on disk
    $files = budsheets_get_contract_files($id);
    $uploadDir = budsheets_upload_dir();
    foreach ($files as $f) {
        if (!empty($f['stored_filename']) && file_exists($uploadDir . $f['stored_filename'])) {
            @unlink($uploadDir . $f['stored_filename']);
        }
    }

    // Also delete invoice attachment files on disk
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
    $pdb = budsheets_db();
    if (!$pdb || empty($files_array['name'][0])) return;

    $uploadDir = budsheets_upload_dir();
    $table = $pdb->getTableName('contract_files');
    $userId = $_SESSION['user_id'] ?? null;

    $count = count($files_array['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($files_array['error'][$i] === UPLOAD_ERR_OK) {
            $origName = basename($files_array['name'][$i]);
            $ext = pathinfo($origName, PATHINFO_EXTENSION);
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
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $file = budsheets_get_contract_file($file_id);
    if ($file) {
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
    $pdb = budsheets_db();
    if (!$pdb) return false;

    $table = $pdb->getTableName('invoices');
    $userId = $_SESSION['user_id'] ?? null;
    $uploadDir = budsheets_upload_dir();

    $origName = null;
    $storedName = null;

    if ($file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
        $origName = basename($file['name']);
        $ext = pathinfo($origName, PATHINFO_EXTENSION);
        $storedName = 'invoice_' . (int)$data['item_id'] . '_' . uniqid() . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $uploadDir . $storedName);
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
            (int)$data['item_id'],
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
        return $pdb->lastInsertId();
    }
}

function budsheets_delete_invoice($invoice_id) {
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $inv = budsheets_get_invoice($invoice_id);
    if ($inv) {
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

    $lobTable = $pdb->getTableName('lines_of_business');
    $itemsTable = $pdb->getTableName('items');
    $invTable = $pdb->getTableName('invoices');

    $lobCount = (int)$pdb->query("SELECT COUNT(*) as c FROM {$lobTable}")->fetch()['c'];
    $itemCount = (int)$pdb->query("SELECT COUNT(*) as c FROM {$itemsTable}")->fetch()['c'];
    $totalInvoiced = (float)$pdb->query("SELECT SUM(amount_paid) as total FROM {$invTable}")->fetch()['total'];

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

    if ($fileType === 'contract') {
        $file = budsheets_get_contract_file($fileId);
        if ($file) {
            $storedName = $file['stored_filename'];
            $origName = $file['original_filename'];
        }
    } elseif ($fileType === 'invoice') {
        $inv = budsheets_get_invoice($fileId);
        if ($inv) {
            $storedName = $inv['attachment_stored_name'];
            $origName = $inv['attachment_original_name'];
        }
    }

    if (!$storedName || !$origName) {
        die('File not found');
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
