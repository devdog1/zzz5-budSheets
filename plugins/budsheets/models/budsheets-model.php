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

    // Auto-create fiscal_years table if missing
    try {
        $fyTable = $pdb->getTableName('fiscal_years');
        $pdb->query("SELECT 1 FROM {$fyTable} LIMIT 1");
    } catch (Exception $e) {
        try {
            $fyTable = $pdb->getTableName('fiscal_years');
            $pdb->query("CREATE TABLE IF NOT EXISTS {$fyTable} (
                year INT PRIMARY KEY,
                is_hidden TINYINT(1) NOT NULL DEFAULT 0,
                is_locked TINYINT(1) NOT NULL DEFAULT 0,
                gst_rate DECIMAL(5,2) DEFAULT NULL,
                pst_rate DECIMAL(5,2) DEFAULT NULL,
                rate_USD DECIMAL(8,4) DEFAULT NULL,
                rate_EUR DECIMAL(8,4) DEFAULT NULL,
                rate_GBP DECIMAL(8,4) DEFAULT NULL,
                rate_AUD DECIMAL(8,4) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (Exception $ex) {
            // Ignore
        }
    }

    // Auto-migrate per-FY tax and FX rate columns in fiscal_years if missing
    try {
        $fyTable = $pdb->getTableName('fiscal_years');
        $pdb->query("SELECT gst_rate FROM {$fyTable} LIMIT 1");
    } catch (Exception $e) {
        try {
            $fyTable = $pdb->getTableName('fiscal_years');
            $pdb->query("ALTER TABLE {$fyTable} ADD COLUMN gst_rate DECIMAL(5,2) DEFAULT NULL");
            $pdb->query("ALTER TABLE {$fyTable} ADD COLUMN pst_rate DECIMAL(5,2) DEFAULT NULL");
            $pdb->query("ALTER TABLE {$fyTable} ADD COLUMN rate_USD DECIMAL(8,4) DEFAULT NULL");
            $pdb->query("ALTER TABLE {$fyTable} ADD COLUMN rate_EUR DECIMAL(8,4) DEFAULT NULL");
            $pdb->query("ALTER TABLE {$fyTable} ADD COLUMN rate_GBP DECIMAL(8,4) DEFAULT NULL");
            $pdb->query("ALTER TABLE {$fyTable} ADD COLUMN rate_AUD DECIMAL(8,4) DEFAULT NULL");
        } catch (Exception $ex) {
            // Ignore
        }
    }

    // Auto-migrate fiscal_year column in lines_of_business table if missing
    try {
        $lobTable = $pdb->getTableName('lines_of_business');
        $pdb->query("SELECT fiscal_year FROM {$lobTable} LIMIT 1");
    } catch (Exception $e) {
        try {
            $lobTable = $pdb->getTableName('lines_of_business');
            $pdb->query("ALTER TABLE {$lobTable} ADD COLUMN fiscal_year INT DEFAULT NULL");
        } catch (Exception $ex) {
            // Ignore
        }
    }

    // Auto-migrate system_directory_link column if missing
    try {
        $itemsTable = $pdb->getTableName('items');
        $pdb->query("SELECT system_directory_link FROM {$itemsTable} LIMIT 1");
    } catch (Exception $e) {
        try {
            $itemsTable = $pdb->getTableName('items');
            $pdb->query("ALTER TABLE {$itemsTable} ADD COLUMN system_directory_link VARCHAR(500) DEFAULT NULL");
        } catch (Exception $ex) {
            // Ignore
        }
    }

    // Auto-migrate billing_frequency column if missing
    try {
        $itemsTable = $pdb->getTableName('items');
        $pdb->query("SELECT billing_frequency FROM {$itemsTable} LIMIT 1");
    } catch (Exception $e) {
        try {
            $itemsTable = $pdb->getTableName('items');
            $pdb->query("ALTER TABLE {$itemsTable} ADD COLUMN billing_frequency ENUM('monthly', 'yearly') NOT NULL DEFAULT 'monthly'");
        } catch (Exception $ex) {
            // Ignore
        }
    }

    // Auto-migrate invoice_month column if missing
    try {
        $itemsTable = $pdb->getTableName('items');
        $pdb->query("SELECT invoice_month FROM {$itemsTable} LIMIT 1");
    } catch (Exception $e) {
        try {
            $itemsTable = $pdb->getTableName('items');
            $pdb->query("ALTER TABLE {$itemsTable} ADD COLUMN invoice_month INT DEFAULT NULL");
        } catch (Exception $ex) {
            // Ignore
        }
    }

    // Auto-migrate access_level column in lob_users table if missing
    try {
        $lobUsersTable = $pdb->getTableName('lob_users');
        $pdb->query("SELECT access_level FROM {$lobUsersTable} LIMIT 1");
    } catch (Exception $e) {
        try {
            $lobUsersTable = $pdb->getTableName('lob_users');
            $pdb->query("ALTER TABLE {$lobUsersTable} ADD COLUMN access_level VARCHAR(20) NOT NULL DEFAULT 'full'");
        } catch (Exception $ex) {
            // Ignore
        }
    }

    // Auto-create class_lobs table if missing
    try {
        $classLobsTable = $pdb->getTableName('class_lobs');
        $pdb->query("SELECT 1 FROM {$classLobsTable} LIMIT 1");
    } catch (Exception $e) {
        try {
            $classLobsTable = $pdb->getTableName('class_lobs');
            $classesTable = $pdb->getTableName('classes');
            $lobTable = $pdb->getTableName('lines_of_business');
            $pdb->query("CREATE TABLE IF NOT EXISTS {$classLobsTable} (
                class_id INT NOT NULL,
                lob_id INT NOT NULL,
                PRIMARY KEY (class_id, lob_id),
                INDEX (lob_id),
                CONSTRAINT fk_budsheets_class_lobs_class FOREIGN KEY (class_id) REFERENCES {$classesTable}(id) ON DELETE CASCADE,
                CONSTRAINT fk_budsheets_class_lobs_lob FOREIGN KEY (lob_id) REFERENCES {$lobTable}(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Migrate existing single lob_id values into class_lobs table
            $rows = $pdb->query("SELECT id, lob_id FROM {$classesTable} WHERE lob_id IS NOT NULL AND lob_id > 0")->fetchAll();
            foreach ($rows as $r) {
                try {
                    $existing = $pdb->query("SELECT class_id FROM {$classLobsTable} WHERE class_id = ? AND lob_id = ?", [(int)$r['id'], (int)$r['lob_id']])->fetch();
                    if (!$existing) {
                        $pdb->query("INSERT INTO {$classLobsTable} (class_id, lob_id) VALUES (?, ?)", [(int)$r['id'], (int)$r['lob_id']]);
                    }
                } catch (Exception $ex) {}
            }
        } catch (Exception $ex) {
            // Ignore
        }
    }

    // Auto-create comments table if missing
    try {
        $commTable = $pdb->getTableName('comments');
        $pdb->query("SELECT 1 FROM {$commTable} LIMIT 1");
    } catch (Exception $e) {
        try {
            $commTable = $pdb->getTableName('comments');
            $pdb->query("CREATE TABLE IF NOT EXISTS {$commTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entity_type ENUM('budget', 'item') NOT NULL,
                entity_id INT NOT NULL,
                comment_text TEXT NOT NULL,
                created_by INT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (entity_type, entity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (Exception $ex) {
            // Ignore
        }
    }

    // Auto-create item_monthly_schedules table if missing
    try {
        $schedTable = $pdb->getTableName('item_monthly_schedules');
        $pdb->query("SELECT 1 FROM {$schedTable} LIMIT 1");
    } catch (Exception $e) {
        try {
            $schedTable = $pdb->getTableName('item_monthly_schedules');
            $itemsTable = $pdb->getTableName('items');
            $pdb->query("CREATE TABLE IF NOT EXISTS {$schedTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT NOT NULL,
                fiscal_year INT NOT NULL,
                month INT NOT NULL,
                amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_budsheets_item_fy_month (item_id, fiscal_year, month),
                CONSTRAINT fk_budsheets_schedules_item FOREIGN KEY (item_id) REFERENCES {$itemsTable}(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (Exception $ex) {
            // Ignore
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
// Tax, Currency Exchange, Starting Year & Lock Settings
// ==========================================

function budsheets_get_settings($fiscal_year = null) {
    $defaultSettings = [
        'starting_fiscal_year'     => (int)date('Y'),
        'gst_rate'                 => 5.0,
        'pst_rate'                 => 7.0,
        'rate_CAD'                 => 1.0,
        'rate_USD'                 => 1.35,
        'rate_EUR'                 => 1.45,
        'rate_GBP'                 => 1.70,
        'rate_AUD'                 => 0.90,
        'fiscal_year_start_month'  => 9, // Default Sept 1st
        'fiscal_year_start_day'    => 1
    ];

    $settings = [];
    foreach ($defaultSettings as $key => $default) {
        if (function_exists('get_plugin_setting')) {
            $val = get_plugin_setting('budsheets', $key, $default);
            $settings[$key] = is_numeric($val) ? (float)$val : $val;
        } else {
            $settings[$key] = $default;
        }
    }
    $settings['starting_fiscal_year'] = (int)($settings['starting_fiscal_year'] ?? date('Y'));

    // Check year-specific tax & exchange rate overrides
    if ($fiscal_year !== null) {
        $pdb = budsheets_db();
        if ($pdb) {
            $fyTable = $pdb->getTableName('fiscal_years');
            $fyRow = $pdb->query("SELECT gst_rate, pst_rate, rate_USD, rate_EUR, rate_GBP, rate_AUD FROM {$fyTable} WHERE year = ?", [(int)$fiscal_year])->fetch();
            if ($fyRow) {
                foreach (['gst_rate', 'pst_rate', 'rate_USD', 'rate_EUR', 'rate_GBP', 'rate_AUD'] as $k) {
                    if (isset($fyRow[$k]) && $fyRow[$k] !== null && $fyRow[$k] !== '') {
                        $settings[$k] = (float)$fyRow[$k];
                    }
                }
            }
        }
    }

    return $settings;
}

function budsheets_save_settings($data) {
    if (!function_exists('set_plugin_setting')) return false;

    $fields = [
        'starting_fiscal_year', 'gst_rate', 'pst_rate', 'rate_CAD', 'rate_USD', 'rate_EUR', 'rate_GBP', 'rate_AUD',
        'fiscal_year_start_month', 'fiscal_year_start_day'
    ];
    foreach ($fields as $f) {
        if (isset($data[$f])) {
            set_plugin_setting('budsheets', $f, (float)$data[$f]);
        }
    }
    return true;
}

function budsheets_save_fy_rates($fiscal_year, $rates) {
    $pdb = budsheets_db();
    if (!$pdb) return false;

    $fy = (int)$fiscal_year;
    budsheets_add_fiscal_year($fy);

    $table = $pdb->getTableName('fiscal_years');
    $sql = "UPDATE {$table} SET gst_rate = ?, pst_rate = ?, rate_USD = ?, rate_EUR = ?, rate_GBP = ?, rate_AUD = ? WHERE year = ?";
    $pdb->query($sql, [
        isset($rates['gst_rate']) && $rates['gst_rate'] !== '' ? (float)$rates['gst_rate'] : null,
        isset($rates['pst_rate']) && $rates['pst_rate'] !== '' ? (float)$rates['pst_rate'] : null,
        isset($rates['rate_USD']) && $rates['rate_USD'] !== '' ? (float)$rates['rate_USD'] : null,
        isset($rates['rate_EUR']) && $rates['rate_EUR'] !== '' ? (float)$rates['rate_EUR'] : null,
        isset($rates['rate_GBP']) && $rates['rate_GBP'] !== '' ? (float)$rates['rate_GBP'] : null,
        isset($rates['rate_AUD']) && $rates['rate_AUD'] !== '' ? (float)$rates['rate_AUD'] : null,
        $fy
    ]);
    return true;
}

// ==========================================
// Managed Financial / Fiscal Years Logic
// ==========================================

function budsheets_get_managed_fiscal_years($includeHidden = false) {
    $pdb = budsheets_db();
    if (!$pdb) return [];

    $table = $pdb->getTableName('fiscal_years');
    $sql = "SELECT * FROM {$table}";
    if (!$includeHidden) {
        $sql .= " WHERE is_hidden = 0";
    }
    $sql .= " ORDER BY year DESC";

    $rows = $pdb->query($sql)->fetchAll();

    if (empty($rows)) {
        $currentFy = budsheets_get_fiscal_year();
        $settings = budsheets_get_settings();
        $startingFy = (int)($settings['starting_fiscal_year'] ?? $currentFy);
        $minYr = min($startingFy, $currentFy - 1);
        $maxYr = max($startingFy + 3, $currentFy + 3);

        for ($y = $minYr; $y <= $maxYr; $y++) {
            try {
                $existing = $pdb->query("SELECT year FROM {$table} WHERE year = ?", [$y])->fetch();
                if (!$existing) {
                    $pdb->query("INSERT INTO {$table} (year, is_hidden, is_locked) VALUES (?, 0, 0)", [$y]);
                }
            } catch (Exception $e) {}
        }
        $rows = $pdb->query($sql)->fetchAll();
    }

    return $rows;
}

function budsheets_add_fiscal_year($year) {
    $pdb = budsheets_db();
    if (!$pdb) return false;

    $table = $pdb->getTableName('fiscal_years');
    $y = (int)$year;
    if ($y < 2000 || $y > 2100) return false;

    $existing = $pdb->query("SELECT year FROM {$table} WHERE year = ?", [$y])->fetch();
    if ($existing) {
        $pdb->query("UPDATE {$table} SET is_hidden = 0 WHERE year = ?", [$y]);
    } else {
        $pdb->query("INSERT INTO {$table} (year, is_hidden, is_locked) VALUES (?, 0, 0)", [$y]);
    }
    return true;
}

function budsheets_hide_fiscal_year($year, $hide = true) {
    $pdb = budsheets_db();
    if (!$pdb) return false;

    $table = $pdb->getTableName('fiscal_years');
    $pdb->query("UPDATE {$table} SET is_hidden = ? WHERE year = ?", [$hide ? 1 : 0, (int)$year]);
    return true;
}

function budsheets_remove_fiscal_year($year) {
    $pdb = budsheets_db();
    if (!$pdb) return false;

    $table = $pdb->getTableName('fiscal_years');
    return $pdb->query("DELETE FROM {$table} WHERE year = ?", [(int)$year]);
}

/**
 * Get list of locked fiscal years
 */
function budsheets_get_locked_fiscal_years() {
    $pdb = budsheets_db();
    if ($pdb) {
        $table = $pdb->getTableName('fiscal_years');
        $rows = $pdb->query("SELECT year FROM {$table} WHERE is_locked = 1")->fetchAll();
        if (!empty($rows)) {
            return array_map('intval', array_column($rows, 'year'));
        }
    }

    if (function_exists('get_plugin_setting')) {
        $val = get_plugin_setting('budsheets', 'locked_fiscal_years', '[]');
        $arr = json_decode($val, true);
        return is_array($arr) ? array_map('intval', $arr) : [];
    }
    return [];
}

/**
 * Check if a given fiscal year is locked
 */
function budsheets_is_fiscal_year_locked($fiscal_year) {
    $lockedYears = budsheets_get_locked_fiscal_years();
    return in_array((int)$fiscal_year, $lockedYears, true);
}

/**
 * Set lock status for a fiscal year
 */
function budsheets_set_fiscal_year_lock($fiscal_year, $isLocked) {
    $pdb = budsheets_db();
    if ($pdb) {
        $table = $pdb->getTableName('fiscal_years');
        $fy = (int)$fiscal_year;
        budsheets_add_fiscal_year($fy);
        $pdb->query("UPDATE {$table} SET is_locked = ? WHERE year = ?", [$isLocked ? 1 : 0, $fy]);
    }

    if (!function_exists('set_plugin_setting')) return false;

    $fy = (int)$fiscal_year;
    $lockedYears = budsheets_get_locked_fiscal_years();

    if ($isLocked) {
        if (!in_array($fy, $lockedYears, true)) {
            $lockedYears[] = $fy;
        }
    } else {
        $lockedYears = array_diff($lockedYears, [$fy]);
    }

    sort($lockedYears);
    return set_plugin_setting('budsheets', 'locked_fiscal_years', json_encode(array_values($lockedYears)));
}

/**
 * Get current active fiscal year number for a given date or timestamp.
 * Rule: The fiscal year is named after the year number that Jan 1st falls into for that period.
 */
function budsheets_get_fiscal_year($dateStr = null) {
    $settings = budsheets_get_settings();
    $fyStartMonth = (int)($settings['fiscal_year_start_month'] ?? 9);
    $fyStartDay = (int)($settings['fiscal_year_start_day'] ?? 1);

    $timestamp = $dateStr ? strtotime($dateStr) : time();
    $year = (int)date('Y', $timestamp);
    $month = (int)date('n', $timestamp);
    $day = (int)date('j', $timestamp);

    if ($fyStartMonth === 1 && $fyStartDay === 1) {
        return $year;
    }

    $isAfterStart = ($month > $fyStartMonth) || ($month === $fyStartMonth && $day >= $fyStartDay);

    if ($isAfterStart) {
        return $year + 1;
    } else {
        return $year;
    }
}

/**
 * Returns the 12 month numbers (in chronological order) for a given Fiscal Year
 */
function budsheets_get_fiscal_year_months() {
    $settings = budsheets_get_settings();
    $startMonth = (int)($settings['fiscal_year_start_month'] ?? 9);

    $months = [];
    for ($i = 0; $i < 12; $i++) {
        $m = (($startMonth - 1 + $i) % 12) + 1;
        $months[] = $m;
    }
    return $months;
}

/**
 * Convert any currency amount to CAD based on settings (supporting year-specific FX rates)
 */
function budsheets_convert_to_cad($amount, $currency = 'CAD', $fiscal_year = null) {
    $settings = budsheets_get_settings($fiscal_year);
    $curr = strtoupper(trim($currency ?: 'CAD'));
    $rateKey = 'rate_' . $curr;
    $rate = $settings[$rateKey] ?? 1.0;
    return (float)$amount * (float)$rate;
}

/**
 * Calculate cost breakdown and tax for an item (supporting monthly or yearly billing frequency and year-specific rates)
 */
function budsheets_calculate_item_cost_and_tax($rawAmount, $billingFrequency, $taxType, $fiscal_year = null) {
    $amount = (float)$rawAmount;
    $freq = strtolower($billingFrequency) === 'yearly' ? 'yearly' : 'monthly';

    $monthlyBase = $freq === 'yearly' ? ($amount / 12.0) : $amount;
    $annualBase = $freq === 'yearly' ? $amount : ($amount * 12.0);

    $settings = budsheets_get_settings($fiscal_year);
    $gstRate = $settings['gst_rate'] / 100.0;
    $pstRate = $settings['pst_rate'] / 100.0;

    $gstMultiplier = 0.0;
    $pstMultiplier = 0.0;

    if ($taxType === 'GSTandPST') {
        $gstMultiplier = $gstRate;
        $pstMultiplier = $pstRate;
    } elseif ($taxType === 'GST only') {
        $gstMultiplier = $gstRate;
    } elseif ($taxType === 'PST only') {
        $pstMultiplier = $pstRate;
    }

    $monthlyGst = $monthlyBase * $gstMultiplier;
    $monthlyPst = $monthlyBase * $pstMultiplier;
    $monthlyTotal = $monthlyBase + $monthlyGst + $monthlyPst;

    $annualGst = $annualBase * $gstMultiplier;
    $annualPst = $annualBase * $pstMultiplier;
    $annualTotal = $annualBase + $annualGst + $annualPst;

    return [
        'monthly_base'  => $monthlyBase,
        'monthly_gst'   => $monthlyGst,
        'monthly_pst'   => $monthlyPst,
        'monthly_total' => $monthlyTotal,
        'annual_base'   => $annualBase,
        'annual_gst'    => $annualGst,
        'annual_pst'    => $annualPst,
        'annual_total'  => $annualTotal
    ];
}

/**
 * Helper wrapper for backward compatibility
 */
function budsheets_calculate_tax($amount, $taxType, $billingFrequency = 'monthly', $fiscal_year = null) {
    $calc = budsheets_calculate_item_cost_and_tax($amount, $billingFrequency, $taxType, $fiscal_year);
    return [
        'base'  => $calc['monthly_base'],
        'gst'   => $calc['monthly_gst'],
        'pst'   => $calc['monthly_pst'],
        'total' => $calc['monthly_total']
    ];
}

// ==========================================
// Monthly Budget Schedule Operations per Item & FY
// ==========================================

function budsheets_get_item_monthly_schedule($item_id, $fiscal_year) {
    $pdb = budsheets_db();
    if (!$pdb) return [];

    $table = $pdb->getTableName('item_monthly_schedules');
    $rows = $pdb->query("SELECT month, amount FROM {$table} WHERE item_id = ? AND fiscal_year = ?", [
        (int)$item_id,
        (int)$fiscal_year
    ])->fetchAll();

    $schedule = [];
    foreach ($rows as $r) {
        $schedule[(int)$r['month']] = (float)$r['amount'];
    }
    return $schedule;
}

function budsheets_save_item_monthly_schedule($item_id, $fiscal_year, $monthly_amounts) {
    if (budsheets_is_fiscal_year_locked($fiscal_year)) {
        die("Access Denied: Fiscal Year FY{$fiscal_year} is locked. Budget schedules cannot be modified.");
    }

    $item = budsheets_get_item($item_id);
    if (!$item) {
        die('Access Denied: Target item not found or access restricted.');
    }

    $pdb = budsheets_db();
    if (!$pdb) return false;

    $table = $pdb->getTableName('item_monthly_schedules');

    for ($m = 1; $m <= 12; $m++) {
        $amt = isset($monthly_amounts[$m]) ? (float)$monthly_amounts[$m] : 0.0;

        $existing = $pdb->query("SELECT id FROM {$table} WHERE item_id = ? AND fiscal_year = ? AND month = ?", [
            (int)$item_id, (int)$fiscal_year, $m
        ])->fetch();

        if ($existing) {
            $pdb->query("UPDATE {$table} SET amount = ? WHERE id = ?", [$amt, (int)$existing['id']]);
        } else {
            $pdb->query("INSERT INTO {$table} (item_id, fiscal_year, month, amount) VALUES (?, ?, ?, ?)", [
                (int)$item_id, (int)$fiscal_year, $m, $amt
            ]);
        }
    }
    return true;
}

/**
 * Calculates item cost and tax for a specific fiscal year taking year-specific rates into account.
 */
function budsheets_calculate_item_fy_cost($item, $fiscal_year = null) {
    if (!$fiscal_year) {
        $fiscal_year = budsheets_get_fiscal_year();
    }

    $sched = budsheets_get_item_monthly_schedule($item['id'], $fiscal_year);

    if (!empty($sched)) {
        $annualBase = array_sum($sched);

        $calc = budsheets_calculate_item_cost_and_tax($annualBase, 'yearly', $item['tax_type'], $fiscal_year);
        $calc['monthly_schedule'] = $sched;
        $calc['has_custom_schedule'] = true;
        return $calc;
    }

    $itemFy = !empty($item['lob_fiscal_year']) ? (int)$item['lob_fiscal_year'] : budsheets_get_fiscal_year($item['contract_start_date'] ?: ($item['created_at'] ?? null));

    $isInitialOrContractYear = ($fiscal_year == $itemFy);

    if (!empty($item['contract_start_date']) && !empty($item['contract_end_date'])) {
        $startFy = budsheets_get_fiscal_year($item['contract_start_date']);
        $endFy = budsheets_get_fiscal_year($item['contract_end_date']);
        if ($fiscal_year >= $startFy && $fiscal_year <= $endFy) {
            $isInitialOrContractYear = true;
        }
    }

    if ($isInitialOrContractYear) {
        $calc = budsheets_calculate_item_cost_and_tax((float)$item['monthly_cost'], $item['billing_frequency'] ?? 'monthly', $item['tax_type'], $fiscal_year);
        $calc['monthly_schedule'] = [];
        $calc['has_custom_schedule'] = false;
        return $calc;
    }

    // Do NOT auto fill / carry over financial info to unallocated fiscal years
    $calc = budsheets_calculate_item_cost_and_tax(0.00, 'monthly', $item['tax_type'], $fiscal_year);
    $calc['monthly_schedule'] = [];
    $calc['has_custom_schedule'] = false;
    return $calc;
}

// ==========================================
// Comments Operations
// ==========================================

function budsheets_get_comments($entity_type, $entity_id) {
    $pdb = budsheets_db();
    if (!$pdb) return [];

    $table = $pdb->getTableName('comments');
    $rows = $pdb->query("SELECT * FROM {$table} WHERE entity_type = ? AND entity_id = ? ORDER BY created_at ASC", [
        $entity_type,
        (int)$entity_id
    ])->fetchAll();

    $users = budsheets_get_all_system_users();
    $userMap = [];
    foreach ($users as $u) {
        $userMap[$u['id']] = $u['name'];
    }

    foreach ($rows as &$r) {
        $uid = (int)($r['created_by'] ?? 0);
        $r['author_name'] = $userMap[$uid] ?? ($uid ? 'User #' . $uid : 'System User');
    }
    unset($r);

    return $rows;
}

function budsheets_add_comment($entity_type, $entity_id, $comment_text) {
    $pdb = budsheets_db();
    if (!$pdb || empty(trim($comment_text))) return false;

    // Check access rights
    if ($entity_type === 'budget') {
        if (!budsheets_user_has_lob_access($entity_id)) {
            die('Access Denied: You do not have permissions for this Budget.');
        }
    } elseif ($entity_type === 'item') {
        $item = budsheets_get_item($entity_id);
        if (!$item) {
            die('Access Denied: Target budget item not found or access restricted.');
        }
    } else {
        return false;
    }

    $table = $pdb->getTableName('comments');
    $userId = $_SESSION['user_id'] ?? null;

    $pdb->query("INSERT INTO {$table} (entity_type, entity_id, comment_text, created_by) VALUES (?, ?, ?, ?)", [
        $entity_type,
        (int)$entity_id,
        trim($comment_text),
        $userId
    ]);
    return budsheets_last_insert_id($pdb);
}

function budsheets_delete_comment($comment_id) {
    $pdb = budsheets_db();
    if (!$pdb) return false;

    $table = $pdb->getTableName('comments');
    $comment = $pdb->query("SELECT * FROM {$table} WHERE id = ?", [(int)$comment_id])->fetch();

    if ($comment) {
        $currentUserId = $_SESSION['user_id'] ?? 0;
        if (!has_permission('budsheets_admin') && (int)$comment['created_by'] !== (int)$currentUserId) {
            die('Access Denied: You cannot delete this comment.');
        }
        return $pdb->query("DELETE FROM {$table} WHERE id = ?", [(int)$comment_id]);
    }
    return false;
}

// ==========================================
// Budgets / Lines of Business (LOB) Operations
// ==========================================

function budsheets_get_lobs($user_id = null, $fiscal_year = null) {
    $pdb = budsheets_db();
    if (!$pdb) return [];
    $table = $pdb->getTableName('lines_of_business');
    $userLobTable = $pdb->getTableName('lob_users');

    $where = [];
    $params = [];

    if (!has_permission('budsheets_admin')) {
        $currentUserId = ($user_id !== null) ? $user_id : ($_SESSION['user_id'] ?? 0);
        $where[] = "l.id IN (SELECT lob_id FROM {$userLobTable} WHERE user_id = ?)";
        $params[] = (int)$currentUserId;
    }

    if ($fiscal_year !== null && $fiscal_year !== '') {
        $where[] = "l.fiscal_year = ?";
        $params[] = (int)$fiscal_year;
    }

    $sql = "SELECT l.* FROM {$table} l";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY l.name ASC";

    return $pdb->query($sql, $params)->fetchAll();
}

function budsheets_user_has_lob_access($lob_id, $user_id = null) {
    if (has_permission('budsheets_admin')) {
        return true;
    }
    $currentUserId = ($user_id !== null) ? $user_id : ($_SESSION['user_id'] ?? 0);
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $userLobTable = $pdb->getTableName('lob_users');

    $sql = "SELECT 1 FROM {$userLobTable} WHERE lob_id = ? AND user_id = ?";
    $row = $pdb->query($sql, [(int)$lob_id, (int)$currentUserId])->fetch();
    return !empty($row);
}

function budsheets_get_lob($id) {
    $pdb = budsheets_db();
    if (!$pdb) return null;
    $table = $pdb->getTableName('lines_of_business');
    return $pdb->query("SELECT * FROM {$table} WHERE id = ?", [(int)$id])->fetch();
}

function budsheets_add_lob($name, $code = '', $description = '', $user_ids = [], $fiscal_year = null) {
    $pdb = budsheets_db();
    if (!$pdb) return false;

    if (!$fiscal_year) {
        $fiscal_year = budsheets_get_fiscal_year();
    }

    $table = $pdb->getTableName('lines_of_business');
    $pdb->query("INSERT INTO {$table} (name, code, description, fiscal_year) VALUES (?, ?, ?, ?)", [
        trim($name),
        trim($code),
        trim($description),
        (int)$fiscal_year
    ]);
    $lob_id = budsheets_last_insert_id($pdb);

    if ($lob_id && !empty($user_ids)) {
        budsheets_set_lob_users($lob_id, $user_ids);
    }
    return $lob_id;
}

function budsheets_update_lob($id, $name, $code = '', $description = '', $user_ids = null, $fiscal_year = null) {
    $pdb = budsheets_db();
    if (!$pdb) return false;

    if (!$fiscal_year) {
        $existing = budsheets_get_lob($id);
        $fiscal_year = $existing['fiscal_year'] ?? budsheets_get_fiscal_year();
    }

    $table = $pdb->getTableName('lines_of_business');
    $res = $pdb->query("UPDATE {$table} SET name = ?, code = ?, description = ?, fiscal_year = ? WHERE id = ?", [
        trim($name),
        trim($code),
        trim($description),
        (int)$fiscal_year,
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

/**
 * Clone a budget (Line of Business) to a target Financial Year.
 * Clone types:
 * - 'full': copies items WITH recurring amounts and custom monthly schedule costs.
 * - 'soft': copies items WITHOUT financials (sets monthly_cost = 0.00 and no schedule).
 */
function budsheets_clone_budget($source_lob_id, $target_name, $target_code, $target_fiscal_year, $clone_type = 'full') {
    $sourceLob = budsheets_get_lob($source_lob_id);
    if (!$sourceLob) return false;

    $assignedUsers = budsheets_get_lob_users($source_lob_id);

    // 1. Create new target Budget assigned to target_fiscal_year
    $newLobId = budsheets_add_lob($target_name, $target_code, $sourceLob['description'], $assignedUsers, $target_fiscal_year);
    if (!$newLobId) return false;

    // 2. Assign same expense classes to new budget
    $sourceClasses = budsheets_get_classes_by_lob($source_lob_id);
    $classLobsTable = budsheets_db()->getTableName('class_lobs');
    foreach ($sourceClasses as $sc) {
        try {
            $existing = budsheets_db()->query("SELECT class_id FROM {$classLobsTable} WHERE class_id = ? AND lob_id = ?", [(int)$sc['id'], (int)$newLobId])->fetch();
            if (!$existing) {
                budsheets_db()->query("INSERT INTO {$classLobsTable} (class_id, lob_id) VALUES (?, ?)", [(int)$sc['id'], (int)$newLobId]);
            }
        } catch (Exception $ex) {}
    }

    // 3. Copy items
    $sourceItems = budsheets_get_items($source_lob_id);
    $sourceFy = !empty($sourceLob['fiscal_year']) ? (int)$sourceLob['fiscal_year'] : budsheets_get_fiscal_year();

    foreach ($sourceItems as $item) {
        $cost = ($clone_type === 'full') ? (float)$item['monthly_cost'] : 0.00;

        $newItemData = [
            'lob_id'                => $newLobId,
            'vendor'                => $item['vendor'],
            'product'               => $item['product'],
            'currency'              => $item['currency'],
            'monthly_cost'          => $cost,
            'billing_frequency'     => $item['billing_frequency'],
            'invoice_month'         => $item['invoice_month'],
            'tax_type'              => $item['tax_type'],
            'class'                 => $item['class'],
            'description'           => $item['description'],
            'invoice_type'          => $item['invoice_type'],
            'invoice_date'          => $item['invoice_date'],
            'contract_start_date'   => $item['contract_start_date'],
            'contract_end_date'     => $item['contract_end_date'],
            'long_description'      => $item['long_description'],
            'system_directory_link' => $item['system_directory_link']
        ];

        $newItemId = budsheets_save_item($newItemData);

        if ($newItemId && $clone_type === 'full') {
            // Copy monthly schedule from source budget FY to target FY if present
            $sourceSched = budsheets_get_item_monthly_schedule($item['id'], $sourceFy);
            if (!empty($sourceSched)) {
                budsheets_save_item_monthly_schedule($newItemId, $target_fiscal_year, $sourceSched);
            }
        }
    }

    return $newLobId;
}

// ==========================================
// Budget User Access Assignments
// ==========================================

function budsheets_get_lob_users($lob_id) {
    $pdb = budsheets_db();
    if (!$pdb) return [];
    $table = $pdb->getTableName('lob_users');
    $rows = $pdb->query("SELECT user_id FROM {$table} WHERE lob_id = ?", [(int)$lob_id])->fetchAll();
    return array_column($rows, 'user_id');
}

function budsheets_get_lob_user_assignments($lob_id) {
    $pdb = budsheets_db();
    if (!$pdb) return [];
    $table = $pdb->getTableName('lob_users');
    $rows = $pdb->query("SELECT user_id, access_level FROM {$table} WHERE lob_id = ?", [(int)$lob_id])->fetchAll();
    $assignments = [];
    foreach ($rows as $r) {
        $assignments[(int)$r['user_id']] = $r['access_level'] ?? 'full';
    }
    return $assignments;
}

function budsheets_set_lob_users($lob_id, $user_assignments = []) {
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $table = $pdb->getTableName('lob_users');

    $pdb->query("DELETE FROM {$table} WHERE lob_id = ?", [(int)$lob_id]);

    if (!empty($user_assignments)) {
        foreach ($user_assignments as $key => $val) {
            if (is_numeric($key)) {
                $uid = (int)$key;
                $level = is_string($val) ? $val : 'full';
            } else {
                $uid = (int)$val;
                $level = 'full';
            }
            if ($uid > 0) {
                if (!in_array($level, ['full', 'read_only', 'hide_costs'], true)) {
                    $level = 'full';
                }
                $pdb->query("INSERT INTO {$table} (lob_id, user_id, access_level) VALUES (?, ?, ?)", [(int)$lob_id, $uid, $level]);
            }
        }
    }
    return true;
}

function budsheets_get_user_lob_access_level($lob_id, $user_id = null) {
    if (has_permission('budsheets_admin')) {
        return 'full';
    }

    $currentUserId = ($user_id !== null) ? $user_id : ($_SESSION['user_id'] ?? 0);
    $pdb = budsheets_db();
    if (!$pdb) return 'none';

    $table = $pdb->getTableName('lob_users');

    $row = $pdb->query("SELECT access_level FROM {$table} WHERE lob_id = ? AND user_id = ?", [(int)$lob_id, (int)$currentUserId])->fetch();
    if ($row && !empty($row['access_level'])) {
        return $row['access_level'];
    }

    return 'none';
}

function budsheets_user_can_edit_lob($lob_id, $user_id = null) {
    if (!has_permission('budsheets_edit')) {
        return false;
    }
    $level = budsheets_get_user_lob_access_level($lob_id, $user_id);
    return $level === 'full';
}

function budsheets_user_should_hide_costs($lob_id, $user_id = null) {
    if (has_permission('budsheets_admin')) {
        return false;
    }
    $level = budsheets_get_user_lob_access_level($lob_id, $user_id);
    return $level === 'hide_costs';
}

function budsheets_get_all_system_users() {
    if (function_exists('get_all_users')) {
        try {
            $users = get_all_users();
            if (!empty($users)) {
                return array_map(function($u) {
                    return [
                        'id' => $u['id'],
                        'name' => $u['display_name'] ?? $u['name'] ?? $u['username'] ?? 'User #' . $u['id'],
                        'email' => $u['email'] ?? $u['username'] ?? ''
                    ];
                }, $users);
            }
        } catch (Exception $e) {
            // Fallback
        }
    }

    try {
        if (function_exists('get_db_connection')) {
            $db = get_db_connection();
            $rows = $db->query("SELECT id, COALESCE(display_name, username) as name, email FROM users ORDER BY name ASC")->fetchAll();
            return $rows ?: [];
        }
    } catch (Exception $e) {
        // Fallback if users table doesn't exist
    }
    return [];
}

// ==========================================
// Classes Operations (Supporting Multiple Budgets per Class)
// ==========================================

function budsheets_get_classes_by_lob($lob_id) {
    $pdb = budsheets_db();
    if (!$pdb || !$lob_id) return [];
    $classTable = $pdb->getTableName('classes');
    $classLobsTable = $pdb->getTableName('class_lobs');

    $sql = "SELECT DISTINCT c.*
            FROM {$classTable} c
            LEFT JOIN {$classLobsTable} cl ON c.id = cl.class_id
            WHERE cl.lob_id = ? OR c.lob_id = ?
            ORDER BY c.name ASC";
    return $pdb->query($sql, [(int)$lob_id, (int)$lob_id])->fetchAll();
}

function budsheets_get_class_lobs($class_id) {
    $pdb = budsheets_db();
    if (!$pdb || !$class_id) return [];
    $classLobsTable = $pdb->getTableName('class_lobs');
    $rows = $pdb->query("SELECT lob_id FROM {$classLobsTable} WHERE class_id = ?", [(int)$class_id])->fetchAll();
    $lobIds = array_column($rows, 'lob_id');

    if (empty($lobIds)) {
        $classTable = $pdb->getTableName('classes');
        $c = $pdb->query("SELECT lob_id FROM {$classTable} WHERE id = ?", [(int)$class_id])->fetch();
        if ($c && !empty($c['lob_id'])) {
            $lobIds = [(int)$c['lob_id']];
        }
    }
    return array_map('intval', $lobIds);
}

function budsheets_get_all_classes() {
    $pdb = budsheets_db();
    if (!$pdb) return [];
    $classTable = $pdb->getTableName('classes');
    $classLobsTable = $pdb->getTableName('class_lobs');
    $lobTable = $pdb->getTableName('lines_of_business');

    $classes = $pdb->query("SELECT * FROM {$classTable} ORDER BY name ASC")->fetchAll();

    foreach ($classes as &$c) {
        $c['lob_ids'] = budsheets_get_class_lobs($c['id']);
        if (!empty($c['lob_ids'])) {
            $placeholders = implode(',', array_fill(0, count($c['lob_ids']), '?'));
            $lobRows = $pdb->query("SELECT name FROM {$lobTable} WHERE id IN ({$placeholders}) ORDER BY name ASC", $c['lob_ids'])->fetchAll();
            $c['lob_names'] = implode(', ', array_column($lobRows, 'name'));
        } else {
            $c['lob_names'] = 'All Budgets / Unassigned';
        }
    }
    unset($c);

    return $classes;
}

function budsheets_add_class($lob_ids, $name, $description = '') {
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $classTable = $pdb->getTableName('classes');
    $classLobsTable = $pdb->getTableName('class_lobs');

    $lobArray = is_array($lob_ids) ? array_map('intval', $lob_ids) : [(int)$lob_ids];
    $firstLob = !empty($lobArray) ? $lobArray[0] : null;

    $pdb->query("INSERT INTO {$classTable} (lob_id, name, description) VALUES (?, ?, ?)", [
        $firstLob,
        trim($name),
        trim($description)
    ]);
    $class_id = budsheets_last_insert_id($pdb);

    if ($class_id && !empty($lobArray)) {
        foreach ($lobArray as $lid) {
            if ($lid > 0) {
                try {
                    $existing = $pdb->query("SELECT class_id FROM {$classLobsTable} WHERE class_id = ? AND lob_id = ?", [(int)$class_id, (int)$lid])->fetch();
                    if (!$existing) {
                        $pdb->query("INSERT INTO {$classLobsTable} (class_id, lob_id) VALUES (?, ?)", [(int)$class_id, (int)$lid]);
                    }
                } catch (Exception $ex) {}
            }
        }
    }

    return $class_id;
}

function budsheets_update_class($id, $lob_ids, $name, $description = '') {
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $classTable = $pdb->getTableName('classes');
    $classLobsTable = $pdb->getTableName('class_lobs');

    $lobArray = is_array($lob_ids) ? array_map('intval', $lob_ids) : [(int)$lob_ids];
    $firstLob = !empty($lobArray) ? $lobArray[0] : null;

    $pdb->query("UPDATE {$classTable} SET lob_id = ?, name = ?, description = ? WHERE id = ?", [
        $firstLob,
        trim($name),
        trim($description),
        (int)$id
    ]);

    $pdb->query("DELETE FROM {$classLobsTable} WHERE class_id = ?", [(int)$id]);

    if (!empty($lobArray)) {
        foreach ($lobArray as $lid) {
            if ($lid > 0) {
                try {
                    $existing = $pdb->query("SELECT class_id FROM {$classLobsTable} WHERE class_id = ? AND lob_id = ?", [(int)$id, (int)$lid])->fetch();
                    if (!$existing) {
                        $pdb->query("INSERT INTO {$classLobsTable} (class_id, lob_id) VALUES (?, ?)", [(int)$id, (int)$lid]);
                    }
                } catch (Exception $ex) {}
            }
        }
    }
    return true;
}

function budsheets_delete_class($id) {
    $pdb = budsheets_db();
    if (!$pdb) return false;
    $classTable = $pdb->getTableName('classes');
    return $pdb->query("DELETE FROM {$classTable} WHERE id = ?", [(int)$id]);
}

// ==========================================
// Budget Items Operations
// ==========================================

function budsheets_get_items($lob_id = null, $fiscal_year = null) {
    $pdb = budsheets_db();
    if (!$pdb) return [];
    $itemsTable = $pdb->getTableName('items');
    $lobTable = $pdb->getTableName('lines_of_business');
    $userLobTable = $pdb->getTableName('lob_users');

    $sql = "SELECT i.*, l.name as lob_name, l.code as lob_code, l.fiscal_year as lob_fiscal_year
            FROM {$itemsTable} i
            LEFT JOIN {$lobTable} l ON i.lob_id = l.id";
    $params = [];
    $where = [];

    if ($lob_id) {
        $where[] = "i.lob_id = ?";
        $params[] = (int)$lob_id;
    }

    if ($fiscal_year !== null && $fiscal_year !== '') {
        $where[] = "l.fiscal_year = ?";
        $params[] = (int)$fiscal_year;
    }

    if (!has_permission('budsheets_admin')) {
        $currentUserId = $_SESSION['user_id'] ?? 0;
        $where[] = "i.lob_id IN (SELECT lob_id FROM {$userLobTable} WHERE user_id = ?)";
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

    $sql = "SELECT i.*, l.name as lob_name, l.code as lob_code, l.fiscal_year as lob_fiscal_year
            FROM {$itemsTable} i
            LEFT JOIN {$lobTable} l ON i.lob_id = l.id
            WHERE i.id = ?";
    $params = [(int)$id];

    if (!has_permission('budsheets_admin')) {
        $currentUserId = $_SESSION['user_id'] ?? 0;
        $sql .= " AND i.lob_id IN (SELECT lob_id FROM {$userLobTable} WHERE user_id = ?)";
        $params[] = (int)$currentUserId;
    }

    return $pdb->query($sql, $params)->fetch();
}

function budsheets_save_item($data, $item_id = null) {
    $pdb = budsheets_db();
    if (!$pdb) return false;

    $lobId = (int)$data['lob_id'];
    if (!budsheets_user_has_lob_access($lobId)) {
        die('Access Denied: You do not have permissions for this Budget.');
    }
    if (!budsheets_user_can_edit_lob($lobId)) {
        die('Access Denied: Read-only access for this Budget.');
    }

    if ($item_id) {
        $existing = budsheets_get_item($item_id);
        if (!$existing) {
            die('Access Denied: Target item not found or access restricted.');
        }
        $itemFy = !empty($existing['lob_fiscal_year']) ? (int)$existing['lob_fiscal_year'] : budsheets_get_fiscal_year($existing['contract_start_date'] ?: ($existing['created_at'] ?? null));
        if (budsheets_is_fiscal_year_locked($itemFy)) {
            die("Access Denied: Fiscal Year FY{$itemFy} is locked. Basic item details cannot be modified.");
        }
    }

    $table = $pdb->getTableName('items');
    $userId = $_SESSION['user_id'] ?? null;

    $billingFreq = ($data['billing_frequency'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
    $invoiceMonth = (!empty($data['invoice_month']) && $billingFreq === 'yearly') ? (int)$data['invoice_month'] : null;

    $fields = [
        'lob_id'                => $lobId,
        'vendor'                => trim($data['vendor']),
        'product'               => trim($data['product']),
        'currency'              => trim($data['currency'] ?? 'USD'),
        'monthly_cost'          => (float)($data['monthly_cost'] ?? 0),
        'billing_frequency'     => $billingFreq,
        'invoice_month'         => $invoiceMonth,
        'tax_type'              => $data['tax_type'] ?? 'no tax',
        'class'                 => trim($data['class'] ?? ''),
        'description'           => trim($data['description'] ?? ''),
        'invoice_type'          => $data['invoice_type'] ?? 'monthly',
        'invoice_date'          => !empty($data['invoice_date']) ? $data['invoice_date'] : null,
        'contract_start_date'   => !empty($data['contract_start_date']) ? $data['contract_start_date'] : null,
        'contract_end_date'     => !empty($data['contract_end_date']) ? $data['contract_end_date'] : null,
        'long_description'      => trim($data['long_description'] ?? ''),
        'system_directory_link' => trim($data['system_directory_link'] ?? '')
    ];

    if ($item_id) {
        $sql = "UPDATE {$table} SET
                    lob_id = ?, vendor = ?, product = ?, currency = ?, monthly_cost = ?,
                    billing_frequency = ?, invoice_month = ?, tax_type = ?, class = ?, description = ?, invoice_type = ?, invoice_date = ?,
                    contract_start_date = ?, contract_end_date = ?, long_description = ?,
                    system_directory_link = ?
                WHERE id = ?";
        $params = array_values($fields);
        $params[] = (int)$item_id;
        $pdb->query($sql, $params);
        return (int)$item_id;
    } else {
        $fields['created_by'] = $userId;
        $sql = "INSERT INTO {$table} (lob_id, vendor, product, currency, monthly_cost, billing_frequency, invoice_month, tax_type, class, description, invoice_type, invoice_date, contract_start_date, contract_end_date, long_description, system_directory_link, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $pdb->query($sql, array_values($fields));
        return budsheets_last_insert_id($pdb);
    }
}

function budsheets_delete_item($id) {
    $item = budsheets_get_item($id);
    if (!$item) {
        die('Access Denied: Budget item not found or access restricted.');
    }
    if (!budsheets_user_can_edit_lob($item['lob_id'])) {
        die('Access Denied: Read-only access for this Budget.');
    }

    $itemFy = !empty($item['lob_fiscal_year']) ? (int)$item['lob_fiscal_year'] : budsheets_get_fiscal_year($item['contract_start_date'] ?: ($item['created_at'] ?? null));
    if (budsheets_is_fiscal_year_locked($itemFy)) {
        die("Access Denied: Fiscal Year FY{$itemFy} is locked. Budget item cannot be deleted.");
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
    if (!budsheets_user_can_edit_lob($item['lob_id'])) {
        die('Access Denied: Read-only access for this Budget.');
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
    if (!budsheets_user_can_edit_lob($item['lob_id'])) {
        die('Access Denied: Read-only access for this Budget.');
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
// Dashboard & Summary Helper (All CAD)
// ==========================================

function budsheets_get_dashboard_summary($fiscal_year = null) {
    $pdb = budsheets_db();
    if (!$pdb) {
        return ['lob_count' => 0, 'item_count' => 0, 'total_invoiced_cad' => 0, 'monthly_cost_cad' => 0, 'annual_cost_cad' => 0];
    }

    if ($fiscal_year === null || $fiscal_year === '') {
        $fiscal_year = budsheets_get_fiscal_year();
    } else {
        $fiscal_year = (int)$fiscal_year;
    }

    $lobs = budsheets_get_lobs(null, $fiscal_year);
    $items = budsheets_get_items(null, $fiscal_year);
    $invTable = $pdb->getTableName('invoices');

    $lobCount = count($lobs);
    $itemCount = count($items);

    $totalInvoicedCad = 0.0;
    if (!empty($items)) {
        $itemIds = array_column($items, 'id');
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $invoices = $pdb->query("SELECT amount_paid, currency, period_year FROM {$invTable} WHERE item_id IN ({$placeholders})", $itemIds)->fetchAll();
        foreach ($invoices as $inv) {
            $invFy = (int)($inv['period_year'] ?? date('Y'));
            $totalInvoicedCad += budsheets_convert_to_cad($inv['amount_paid'], $inv['currency'], $invFy);
        }
    }

    $monthlyCostCad = 0.0;
    $annualCostCad = 0.0;

    foreach ($items as $item) {
        $itemFy = !empty($item['lob_fiscal_year']) ? (int)$item['lob_fiscal_year'] : $fiscal_year;
        $fyCalc = budsheets_calculate_item_fy_cost($item, $itemFy);
        $monthlyCostCad += budsheets_convert_to_cad($fyCalc['monthly_total'], $item['currency'], $itemFy);
        $annualCostCad += budsheets_convert_to_cad($fyCalc['annual_total'], $item['currency'], $itemFy);
    }

    return [
        'lob_count' => $lobCount,
        'item_count' => $itemCount,
        'total_invoiced_cad' => $totalInvoicedCad,
        'monthly_cost_cad' => $monthlyCostCad,
        'annual_cost_cad' => $annualCostCad
    ];
}

// ==========================================
// File Download & Custom FY CSV Export Handlers
// ==========================================

function budsheets_handle_file_download() {
    $fileType = $_GET['file_type'] ?? '';
    $fileId = (int)($_GET['file_id'] ?? 0);
    $disposition = ($_GET['disposition'] ?? 'attachment') === 'inline' ? 'inline' : 'attachment';

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

    $item = budsheets_get_item($itemId);
    if (!$item) {
        die('Access Denied: You do not have permissions to download or view files for this item.');
    }

    $filePath = budsheets_upload_dir() . $storedName;
    if (!file_exists($filePath)) {
        die('File does not exist on disk');
    }

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $mimeTypes = [
        'pdf'  => 'application/pdf',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'txt'  => 'text/plain'
    ];

    $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: ' . $disposition . '; filename="' . basename($origName) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

function budsheets_export_items_csv() {
    $lob_id = isset($_GET['lob_id']) && $_GET['lob_id'] !== '' ? (int)$_GET['lob_id'] : null;
    $fy = isset($_GET['fy']) && $_GET['fy'] !== '' ? (int)$_GET['fy'] : budsheets_get_fiscal_year();

    $items = budsheets_get_items($lob_id, $fy);
    $fyMonthsOrder = budsheets_get_fiscal_year_months();

    $monthsNames = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
    ];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=operational_budget_items_FY' . $fy . '_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');

    // Build header row with dynamic FY month columns
    $headers = [
        'ID',
        'Budget',
        'Vendor Name',
        'Product',
        'Class',
        'Billing Frequency',
        'Vendor Invoice Month',
        'Native Currency',
        'Native Amount',
        'Amount in CAD',
        'Tax Type',
        'Full Financial Year Amount w/ Tax (CAD)',
        'Per Monthly Avg w/ Tax (CAD)'
    ];

    foreach ($fyMonthsOrder as $mNum) {
        $headers[] = $monthsNames[$mNum] . ' FY' . $fy . ' (CAD)';
    }

    fputcsv($output, $headers);

    foreach ($items as $item) {
        $costCalc = budsheets_calculate_item_fy_cost($item, $fy);
        $amountCad = budsheets_convert_to_cad((float)$item['monthly_cost'], $item['currency'], $fy);
        $fullFyCad = budsheets_convert_to_cad($costCalc['annual_total'], $item['currency'], $fy);
        $monthlyAvgCad = budsheets_convert_to_cad($costCalc['monthly_total'], $item['currency'], $fy);

        $invMonthStr = 'N/A';
        if (($item['billing_frequency'] ?? 'monthly') === 'yearly' && !empty($item['invoice_month'])) {
            $invMonthStr = $monthsNames[(int)$item['invoice_month']] ?? 'Month #' . $item['invoice_month'];
        }

        $row = [
            $item['id'],
            $item['lob_name'],
            $item['vendor'],
            $item['product'],
            $item['class'],
            ucfirst($item['billing_frequency'] ?? 'monthly'),
            $invMonthStr,
            $item['currency'],
            number_format((float)$item['monthly_cost'], 2, '.', ''),
            number_format($amountCad, 2, '.', ''),
            $item['tax_type'],
            number_format($fullFyCad, 2, '.', ''),
            number_format($monthlyAvgCad, 2, '.', '')
        ];

        // Append per-month values for the financial year
        $defaultMonthlyBase = ($item['billing_frequency'] === 'yearly') ? ((float)$item['monthly_cost'] / 12.0) : (float)$item['monthly_cost'];
        foreach ($fyMonthsOrder as $mNum) {
            $mAmount = isset($costCalc['monthly_schedule'][$mNum]) ? $costCalc['monthly_schedule'][$mNum] : ($costCalc['has_custom_schedule'] ? 0.0 : $defaultMonthlyBase);
            $mTax = budsheets_calculate_tax($mAmount, $item['tax_type'], 'monthly', $fy);
            $mCad = budsheets_convert_to_cad($mTax['total'], $item['currency'], $fy);
            $row[] = number_format($mCad, 2, '.', '');
        }

        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

// ==========================================
// Email & Weekly Budget Summary Reports
// ==========================================

function budsheets_send_email($to, $subject, $body, $headers = []) {
    if (function_exists('send_email')) {
        return send_email($to, $subject, $body, $headers);
    }
    if (function_exists('wp_mail')) {
        return wp_mail($to, $subject, $body, $headers);
    }
    $defaultHeaders = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: BudSheets Operational Budgets <noreply@portal.local>'
    ];
    $mergedHeaders = array_merge($defaultHeaders, is_array($headers) ? $headers : [$headers]);
    return @mail($to, $subject, $body, implode("\r\n", $mergedHeaders));
}

function budsheets_generate_weekly_summary_html($fiscal_year = null) {
    if ($fiscal_year === null || $fiscal_year === '') {
        $fiscal_year = budsheets_get_fiscal_year();
    } else {
        $fiscal_year = (int)$fiscal_year;
    }

    $summary = budsheets_get_dashboard_summary($fiscal_year);
    $lobs = budsheets_get_lobs(null, $fiscal_year);
    $items = budsheets_get_items(null, $fiscal_year);

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Weekly Operational Budget Summary - FY' . $fiscal_year . '</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #212529; background-color: #f8f9fa; margin: 0; padding: 20px; }
            .container { max-width: 700px; margin: 0 auto; background: #ffffff; padding: 25px; border-radius: 8px; border: 1px solid #e9ecef; }
            .header { border-bottom: 2px solid #0d6efd; padding-bottom: 15px; margin-bottom: 20px; }
            .header h1 { margin: 0; color: #0d6efd; font-size: 22px; }
            .header p { margin: 5px 0 0 0; color: #6c757d; font-size: 13px; }
            .kpi-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
            .kpi-card { background: #f1f5f9; padding: 15px; text-align: center; border-radius: 6px; }
            .kpi-card .number { font-size: 20px; font-weight: bold; color: #0f172a; margin-top: 5px; }
            .kpi-card .label { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; }
            .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
            .data-table th { background: #f8fafc; border-bottom: 2px solid #cbd5e1; text-align: left; padding: 10px; color: #334155; }
            .data-table td { border-bottom: 1px solid #e2e8f0; padding: 10px; color: #1e293b; }
            .footer { margin-top: 25px; pt-3; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8; text-align: center; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Weekly Budget Summary Report (FY' . $fiscal_year . ')</h1>
                <p>Generated on ' . date('F j, Y - H:i T') . ' | Operational Budget Sheets (BudSheets)</p>
            </div>

            <table class="kpi-table">
                <tr>
                    <td style="width: 25%; padding: 4px;">
                        <div class="kpi-card">
                            <div class="label">Budgets</div>
                            <div class="number">' . (int)$summary['lob_count'] . '</div>
                        </div>
                    </td>
                    <td style="width: 25%; padding: 4px;">
                        <div class="kpi-card">
                            <div class="label">Budget Items</div>
                            <div class="number">' . (int)$summary['item_count'] . '</div>
                        </div>
                    </td>
                    <td style="width: 25%; padding: 4px;">
                        <div class="kpi-card">
                            <div class="label">Monthly Total (CAD)</div>
                            <div class="number">$' . number_format((float)$summary['monthly_cost_cad'], 2) . '</div>
                        </div>
                    </td>
                    <td style="width: 25%; padding: 4px;">
                        <div class="kpi-card">
                            <div class="label">Total Invoiced (CAD)</div>
                            <div class="number" style="color: #16a34a;">$' . number_format((float)$summary['total_invoiced_cad'], 2) . '</div>
                        </div>
                    </td>
                </tr>
            </table>

            <h3 style="font-size: 16px; margin-bottom: 12px; color: #0f172a;">Budget Allocations Breakdown (CAD)</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Budget</th>
                        <th>Code</th>
                        <th style="text-align: center;">Items</th>
                        <th>Monthly Avg (w/ Tax)</th>
                        <th>Annual Total (w/ Tax)</th>
                    </tr>
                </thead>
                <tbody>';

    if (empty($lobs)) {
        $html .= '<tr><td colspan="5" style="text-align: center; color: #94a3b8;">No Budgets found for FY' . $fiscal_year . '.</td></tr>';
    } else {
        foreach ($lobs as $lob) {
            $lobItems = array_filter($items, function($i) use ($lob) { return $i['lob_id'] == $lob['id']; });
            $lobFy = !empty($lob['fiscal_year']) ? (int)$lob['fiscal_year'] : $fiscal_year;
            $lobMonthlyTotalCad = 0.0;
            $lobAnnualTotalCad = 0.0;
            foreach ($lobItems as $li) {
                $fyCalc = budsheets_calculate_item_fy_cost($li, $lobFy);
                $lobMonthlyTotalCad += budsheets_convert_to_cad($fyCalc['monthly_total'], $li['currency'], $lobFy);
                $lobAnnualTotalCad += budsheets_convert_to_cad($fyCalc['annual_total'], $li['currency'], $lobFy);
            }

            $html .= '<tr>
                <td><strong>' . htmlspecialchars($lob['name']) . '</strong></td>
                <td>' . htmlspecialchars($lob['code'] ?: 'N/A') . '</td>
                <td style="text-align: center;">' . count($lobItems) . '</td>
                <td style="font-weight: 600; color: #2563eb;">$' . number_format($lobMonthlyTotalCad, 2) . ' CAD</td>
                <td style="font-weight: 600; color: #16a34a;">$' . number_format($lobAnnualTotalCad, 2) . ' CAD</td>
            </tr>';
        }
    }

    $html .= '
                </tbody>
            </table>

            <div class="footer">
                <p>This automated email summary was dispatched by BudSheets Operational Budget Tracker.</p>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}

function budsheets_send_weekly_summary_report($fiscal_year = null, $target_email = null) {
    if ($fiscal_year === null || $fiscal_year === '') {
        $fiscal_year = budsheets_get_fiscal_year();
    } else {
        $fiscal_year = (int)$fiscal_year;
    }

    $subject = "Weekly Operational Budget Summary - FY" . $fiscal_year . " (" . date('M j, Y') . ")";
    $body = budsheets_generate_weekly_summary_html($fiscal_year);

    $recipients = [];

    if (!empty($target_email) && filter_var($target_email, FILTER_VALIDATE_EMAIL)) {
        $recipients[] = trim($target_email);
    } else {
        $systemUsers = budsheets_get_all_system_users();
        foreach ($systemUsers as $u) {
            if (!empty($u['email']) && filter_var($u['email'], FILTER_VALIDATE_EMAIL)) {
                $recipients[] = trim($u['email']);
            }
        }
    }

    $recipients = array_unique($recipients);
    if (empty($recipients)) {
        return false;
    }

    $success = true;
    foreach ($recipients as $email) {
        if (!budsheets_send_email($email, $subject, $body)) {
            $success = false;
        }
    }

    return $success;
}
