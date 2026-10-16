# Changelog - Operational Budget Sheets (BudSheets)

All notable changes to the BudSheets plugin will be documented in this file.

## [1.1.0] - 2026-03-31

### Added
- **Financial Year Hierarchy**: Top-level Financial Year selector across Dashboard, Budgets, and Items views.
- **Granular User Permissions**: Introduced `full`, `read_only`, and `hide_costs` access levels per Budget (Line of Business).
- **Cost Masking**: Automated masking (`****`) of budget amounts, monthly costs, and totals for users with `hide_costs` access level.
- **Weekly Summary Reports**: Automated weekly HTML budget summary email generation and scheduled task cron hooks (`plugin_cron_weekly` and `budsheets_weekly_cron`).
- **Settings & Changelog View**: Embedded local changelog viewer in plugin settings.

### Changed
- **Default Budget Access**: Non-admin users default to having no access to unassigned budgets.
- **Item Detail View**: Streamlined UI by removing redundant Financial Year selector from individual item details (financial year is derived directly from the parent budget).
- **Settings Cleanup**: Removed deprecated global tax and FX rate defaults in favor of per-FY rates.

### Fixed
- **Budget Isolation**: Ensured items strictly belong to a single budget (LOB) which belongs to a single Financial Year.

---

## [1.0.0] - 2026-01-15

### Added
- Initial release of Operational Budget Sheets (BudSheets) plugin for zzz5 Portal Framework.
- Line of Business (Budget) management, budget item tracking, and contract document uploads.
- Invoice reconciliation and payment tracking.
- Per-FY exchange rates, tax calculations, and lock/unlock fiscal year functionality.
- CSV export capability and dashboard overview widget.
