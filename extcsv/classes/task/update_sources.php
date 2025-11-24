<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Scheduled task to update CSV sources
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extcsv\task;

defined('MOODLE_INTERNAL') || die();

use local_extcsv\source;
use local_extcsv\source_manager;
use local_extcsv\csv_importer;
use local_extcsv\data_manager;

/**
 * Scheduled task for updating CSV sources
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_sources extends \core\task\scheduled_task {

    /**
     * Get name of the task
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskupdatesources', 'local_extcsv');
    }

    /**
     * Execute the task
     */
    public function execute() {
        global $DB;
        
        // Load sources directly from DB to avoid persistent memory issues
        $sourcerecords = $DB->get_records('local_extcsv_sources', ['status' => source::STATUS_ENABLED], 'name');
        
        foreach ($sourcerecords as $sourcerecord) {
            $source = new source();
            $source->from_record($sourcerecord);
            $this->update_source($source);
            // Unset to free memory
            unset($source);
        }
    }

    /**
     * Update a single source
     *
     * @param source $source
     */
    protected function update_source($source) {
        // Check if source has schedule configured
        $schedule = $source->get('schedule');
        if (empty($schedule)) {
            return; // Skip sources without schedule
        }

        // Check if it's time to update based on schedule
        if (!$this->should_update($source, $schedule)) {
            return;
        }

        // Check if columns are configured
        $columnsconfig = data_manager::parse_columns_config($source);
        if (empty($columnsconfig) || empty($columnsconfig['columns'])) {
            $error = get_string('columnsnotconfigured', 'local_extcsv');
            $source->set_update_status(source::UPDATE_STATUS_ERROR, $error);
            mtrace("Error updating source '{$source->get('name')}': {$error}");
            return;
        }

        try {
            // We may need a lot of memory here.
            core_php_time_limit::raise();
            raise_memory_limit(MEMORY_HUGE);

            // Mark as pending
            $source->set_update_status(source::UPDATE_STATUS_PENDING);

            // Import data
            $rows = csv_importer::import_from_source($source);

            // Save data
            $saved = data_manager::save_csv_data($source, $rows);

            // Mark as success
            $source->set_update_status(source::UPDATE_STATUS_SUCCESS);
            mtrace("Source '{$source->get('name')}' updated successfully. Saved {$saved} rows.");

        } catch (\Exception $e) {
            // Mark as error
            $error = $e->getMessage();
            $source->set_update_status(source::UPDATE_STATUS_ERROR, $error);
            mtrace("Error updating source '{$source->get('name')}': {$error}");
        }
    }

    /**
     * Check if source should be updated based on schedule
     *
     * @param source $source
     * @param string $schedule Cron expression or interval
     * @return bool
     */
    protected function should_update($source, $schedule) {
        $lastupdate = $source->get('lastupdate');
        if (empty($lastupdate)) {
            return true; // Never updated, update now
        }

        // Check if schedule is a cron expression
        if ($this->is_cron_expression($schedule)) {
            return $this->check_cron_schedule($schedule, $lastupdate);
        }

        // Otherwise treat as interval (format: "N minutes/hours/days")
        return $this->check_interval($schedule, $lastupdate);
    }

    /**
     * Check if string is a cron expression
     *
     * @param string $schedule
     * @return bool
     */
    protected function is_cron_expression($schedule) {
        // Simple check: cron expressions have 5 space-separated parts
        $parts = explode(' ', trim($schedule));
        return count($parts) === 5;
    }

    /**
     * Check cron schedule (simplified implementation)
     * Note: Full cron parser would be more complex, this is a basic version
     *
     * @param string $cron Cron expression
     * @param int $lastupdate Timestamp of last update
     * @return bool
     */
    protected function check_cron_schedule($cron, $lastupdate) {
        // For now, check if at least an hour has passed
        // A full cron parser would be more accurate
        $now = time();
        $diff = $now - $lastupdate;
        return $diff >= 3600; // At least 1 hour passed
    }

    /**
     * Check interval schedule
     *
     * @param string $interval Interval string like "30 minutes", "2 hours", "1 days"
     * @param int $lastupdate Timestamp of last update
     * @return bool
     */
    protected function check_interval($interval, $lastupdate) {
        // Parse interval: "N minutes/hours/days"
        if (preg_match('/(\d+)\s+(minute|hour|day)s?/i', $interval, $matches)) {
            $amount = (int)$matches[1];
            $unit = strtolower($matches[2]);

            $seconds = 0;
            switch ($unit) {
                case 'minute':
                    $seconds = $amount * 60;
                    break;
                case 'hour':
                    $seconds = $amount * 3600;
                    break;
                case 'day':
                    $seconds = $amount * 86400;
                    break;
            }

            $now = time();
            $diff = $now - $lastupdate;
            return $diff >= $seconds;
        }

        // Invalid interval format, don't update
        return false;
    }
}

