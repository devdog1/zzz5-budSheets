CREATE TABLE IF NOT EXISTS `plug_budsheets_lines_of_business` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `code` VARCHAR(50) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `plug_budsheets_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lob_id` INT NOT NULL,
    `vendor` VARCHAR(255) NOT NULL,
    `product` VARCHAR(255) NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
    `monthly_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `tax_type` ENUM('GSTandPST', 'GST only', 'PST only', 'no tax') NOT NULL DEFAULT 'no tax',
    `class` VARCHAR(100) DEFAULT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `invoice_type` ENUM('monthly', 'year') NOT NULL DEFAULT 'monthly',
    `invoice_date` DATE DEFAULT NULL,
    `contract_start_date` DATE DEFAULT NULL,
    `contract_end_date` DATE DEFAULT NULL,
    `long_description` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`lob_id`),
    CONSTRAINT `fk_budsheets_items_lob` FOREIGN KEY (`lob_id`) REFERENCES `plug_budsheets_lines_of_business`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `plug_budsheets_contract_files` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_id` INT NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `stored_filename` VARCHAR(255) NOT NULL,
    `file_size` INT NOT NULL DEFAULT 0,
    `mime_type` VARCHAR(100) DEFAULT NULL,
    `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `uploaded_by` INT DEFAULT NULL,
    INDEX (`item_id`),
    CONSTRAINT `fk_budsheets_contracts_item` FOREIGN KEY (`item_id`) REFERENCES `plug_budsheets_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `plug_budsheets_invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_id` INT NOT NULL,
    `invoice_number` VARCHAR(100) DEFAULT NULL,
    `amount_paid` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `currency` VARCHAR(10) DEFAULT 'USD',
    `period_type` ENUM('full_year', 'monthly') NOT NULL DEFAULT 'monthly',
    `period_year` INT NOT NULL,
    `period_month` INT DEFAULT NULL,
    `payment_date` DATE DEFAULT NULL,
    `comments` TEXT DEFAULT NULL,
    `attachment_original_name` VARCHAR(255) DEFAULT NULL,
    `attachment_stored_name` VARCHAR(255) DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`item_id`),
    CONSTRAINT `fk_budsheets_invoices_item` FOREIGN KEY (`item_id`) REFERENCES `plug_budsheets_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
