# Operational Budget Sheets (BudSheets) Plugin

An enterprise operational budget tracking plugin for the **zzz5 Portal Framework**.

## Overview

**BudSheets** provides comprehensive tracking of operational budgets, recurring departmental expenditures, multi-year vendor contracts, and invoice reconciliation across Lines of Business.

---

## Features

- **Lines of Business (LOB) Management**: Categorize budget items under custom Lines of Business / Departments (e.g., IT, HR, Marketing, Operations).
- **Comprehensive Budget Item Tracking**:
  - Vendor & Product / Service names
  - Currency (USD, CAD, EUR, GBP, AUD)
  - Monthly cost & Tax classification (`GSTandPST`, `GST only`, `PST only`, `no tax`)
  - Class / Expense Category (e.g., Software, Hardware, Consulting)
  - Invoice Type (`monthly`, `year`) & Invoice Schedule date
  - Multi-year Contract duration (`contract_start_date` to `contract_end_date`)
  - Short description and formatted long text descriptions / terms / SLAs
- **Multi-File Contract Attachments**: Upload and download multiple PDF/DOCX/XLSX contract copies per item.
- **Multiple Invoice Tracking per Item**: Log invoices against budget items with:
  - Invoice Number & Payment Date
  - Amount Paid & Currency
  - Period selection (`full_year` or `monthly` with Month & Year dropdown selectors)
  - Notes / Comments
  - Invoice document attachments
- **Role-Based Access Control (RBAC)**:
  - `budsheets_view`: View budget items, summaries, contracts, and invoices.
  - `budsheets_edit`: Add/edit budget items, upload contracts, and record invoices.
  - `budsheets_admin`: Full administrative access, including creation and management of Lines of Business.
- **Export & Reporting**: CSV Export capability for budget reports and item lists.

---

## Database Tables

The plugin operates strictly inside isolated database tables:
- `plug_budsheets_lines_of_business`
- `plug_budsheets_items`
- `plug_budsheets_contract_files`
- `plug_budsheets_invoices`

---

## Installation & Uninstallation

- Schema scripts are automatically executed during plugin activation/deactivation via `sql/install.sql` and `sql/uninstall.sql`.
