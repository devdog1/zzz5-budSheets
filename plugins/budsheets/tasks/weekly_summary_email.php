<?php
/**
 * Task: BudSheets Weekly Budget Summary Email Report
 * Plugin: BudSheets
 * Schedule: weekly
 * Interval: 604800
 * Hook: budsheets_weekly_cron
 * Description: Dispatches automated weekly HTML budget summary reports to configured budget notification email recipients and system users.
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

require_once __DIR__ . '/../models/budsheets-model.php';

/**
 * Task Handler Function for Weekly Summary Email
 *
 * @param array $args Optional task arguments (e.g., ['fiscal_year' => 2026, 'target_email' => 'admin@domain.com'])
 * @return bool True on success, false on failure
 */
function budsheets_task_weekly_summary_email($args = []) {
    $fiscalYear = isset($args['fiscal_year']) ? (int)$args['fiscal_year'] : null;
    $targetEmail = !empty($args['target_email']) ? trim($args['target_email']) : null;

    return budsheets_send_weekly_summary_report($fiscalYear, $targetEmail);
}

// Register Scheduled Task with Framework Task Scheduler & Overrides Page
if (function_exists('add_filter')) {
    $registerTask = function($tasks = []) {
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
            'callback'    => 'budsheets_task_weekly_summary_email',
            'file'        => __FILE__,
            'enabled'     => true
        ];
        return $tasks;
    };

    add_filter('register_scheduled_tasks', $registerTask);
    add_filter('task_scheduler_tasks', $registerTask);
    add_filter('scheduled_tasks', $registerTask);
    add_filter('plugin_scheduled_tasks', $registerTask);
    add_filter('get_scheduled_tasks', $registerTask);
}

// Register Action Hooks for Execution
if (function_exists('add_action')) {
    add_action('init_scheduler', function($scheduler) {
        if (is_object($scheduler) && method_exists($scheduler, 'registerTask')) {
            $scheduler->registerTask('budsheets_weekly_summary_report', 'budsheets_task_weekly_summary_email', 604800, 'budsheets');
        }
    });

    add_action('budsheets_weekly_cron', 'budsheets_task_weekly_summary_email');
    add_action('plugin_cron_weekly', 'budsheets_task_weekly_summary_email');
    add_action('cron_weekly', 'budsheets_task_weekly_summary_email');
    add_action('schedule_weekly_tasks', 'budsheets_task_weekly_summary_email');
    add_action('task_scheduler_run_budsheets_weekly_summary_report', 'budsheets_task_weekly_summary_email');
    add_action('run_scheduled_task_budsheets_weekly_summary_report', 'budsheets_task_weekly_summary_email');
}
