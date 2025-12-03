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
 * Source manager for local_extcsv
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extcsv;

defined('MOODLE_INTERNAL') || die();

use context_system;
use moodle_exception;
use local_extcsv\model\source_model;

/**
 * Source manager class
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_manager {

    /**
     * Get all sources
     *
     * @param string|null $status Filter by status
     * @return source_model[]
     */
    public static function get_all_sources($status = null) {
        global $DB;

        $conditions = [];
        $params = [];
        if ($status !== null) {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        $where = '';
        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        $records = $DB->get_records_sql(
            "SELECT * FROM {local_extcsv_sources} $where ORDER BY name",
            $params
        );

        $sources = [];
        foreach ($records as $record) {
            $source = new source_model();
            $source->from_record($record);
            $sources[] = $source;
        }

        return $sources;
    }

    /**
     * Get source by ID
     *
     * @param int $id
     * @return source_model|null
     */
    public static function get_source($id) {
        try {
            $source = new source_model($id);
            return $source->exists() ? $source : null;
        } catch (\dml_missing_record_exception $e) {
            return null;
        }
    }

    /**
     * Get enabled sources
     *
     * @return source_model[]
     */
    public static function get_enabled_sources() {
        return self::get_all_sources(source_model::STATUS_ENABLED);
    }

    /**
     * Get source by shortname
     *
     * @param string $shortname
     * @return source_model|null
     */
    public static function get_source_by_shortname(string $shortname) {
        global $DB;

        if ($shortname === '') {
            return null;
        }

        $record = $DB->get_record('local_extcsv_sources', ['shortname' => $shortname]);
        if (!$record) {
            return null;
        }

        $source = new source_model();
        $source->from_record($record);
        return $source;
    }

    /**
     * Create new source
     *
     * @param \stdClass $data
     * @return source_model
     */
    public static function create_source($data) {
        // Filter only allowed fields from form data
        $allowedfields = ['name', 'shortname', 'description', 'status', 'url', 'content_type', 'schedule', 'columns_config'];
        $sourcedata = new \stdClass();
        foreach ($allowedfields as $field) {
            if (isset($data->$field)) {
                $sourcedata->$field = $data->$field;
            }
        }
        
        // Create source using model
        $source = new source_model();
        foreach ((array)$sourcedata as $field => $value) {
            $source->set($field, $value);
        }
        $source->save();
        
        return $source;
    }

    /**
     * Update source
     *
     * @param int $id
     * @param \stdClass $data
     * @return source_model
     * @throws moodle_exception
     */
    public static function update_source($id, $data) {
        $source = self::get_source($id);
        if (!$source) {
            throw new moodle_exception('sourcenotfound', 'local_extcsv');
        }
        
        // Filter only allowed fields from form data (exclude system fields and id)
        $allowedfields = ['name', 'shortname', 'description', 'status', 'url', 'content_type', 'schedule', 'columns_config'];
        foreach ($allowedfields as $field) {
            if (isset($data->$field)) {
                $source->set($field, $data->$field);
            }
        }
        
        $source->save();
        return $source;
    }

    /**
     * Delete source
     *
     * @param int $id
     * @return bool
     */
    public static function delete_source($id) {
        global $DB;

        $source = self::get_source($id);
        if (!$source) {
            return false;
        }

        // Delete associated data first
        $DB->delete_records('local_extcsv_data', ['sourceid' => $id]);

        // Delete source using model
        return $source->delete();
    }

    /**
     * Check if user can manage sources
     *
     * @return bool
     */
    public static function can_manage_sources() {
        $context = context_system::instance();
        return has_capability('local/extcsv:manage_sources', $context);
    }

    /**
     * Require capability to manage sources
     *
     * @throws moodle_exception
     */
    public static function require_manage_capability() {
        require_login();
        $context = context_system::instance();
        require_capability('local/extcsv:manage_sources', $context);
    }

    /**
     * Generate unique name for source
     *
     * @param string $basename Base name
     * @param int|null $exclude_id Source ID to exclude from check
     * @return string Unique name
     */
    public static function generate_unique_name(string $basename, ?int $exclude_id = null): string {
        global $DB;

        $name = $basename;
        $counter = 0;

        while (true) {
            $params = ['name' => $name];
            $where = 'name = :name';
            if ($exclude_id !== null) {
                $where .= ' AND id != :exclude_id';
                $params['exclude_id'] = $exclude_id;
            }

            $exists = $DB->record_exists_select('local_extcsv_sources', $where, $params);
            if (!$exists) {
                return $name;
            }

            $counter++;
            if ($counter === 1) {
                $name = $basename . ' (копия)';
            } else {
                $name = $basename . ' (копия ' . $counter . ')';
            }
        }
    }

    /**
     * Generate unique shortname for source
     *
     * @param string $baseshortname Base shortname
     * @param int|null $exclude_id Source ID to exclude from check
     * @return string|null Unique shortname or null if baseshortname is empty
     */
    public static function generate_unique_shortname(string $baseshortname, ?int $exclude_id = null): ?string {
        global $DB;

        if (empty($baseshortname)) {
            return null;
        }

        $shortname = $baseshortname;
        $counter = 0;

        while (true) {
            $params = ['shortname' => $shortname];
            $where = 'shortname = :shortname';
            if ($exclude_id !== null) {
                $where .= ' AND id != :exclude_id';
                $params['exclude_id'] = $exclude_id;
            }

            $exists = $DB->record_exists_select('local_extcsv_sources', $where, $params);
            if (!$exists) {
                return $shortname;
            }

            $counter++;
            if ($counter === 1) {
                $shortname = $baseshortname . '_copy';
            } else {
                $shortname = $baseshortname . '_copy' . $counter;
            }
        }
    }

    /**
     * Duplicate source
     *
     * @param int $id Source ID to duplicate
     * @return source_model Duplicated source
     * @throws moodle_exception
     */
    public static function duplicate_source(int $id): source_model {
        $source = self::get_source($id);
        if (!$source) {
            throw new moodle_exception('sourcenotfound', 'local_extcsv');
        }

        // Prepare data for new source
        $data = new \stdClass();
        $allowedfields = ['name', 'shortname', 'description', 'status', 'url', 'content_type', 'schedule', 'columns_config'];
        
        foreach ($allowedfields as $field) {
            $value = $source->get($field);
            if ($value !== null) {
                $data->$field = $value;
            }
        }

        // Generate unique name and shortname
        $data->name = self::generate_unique_name($source->get('name'));
        $data->shortname = self::generate_unique_shortname($source->get('shortname'));

        // Create new source
        return self::create_source($data);
    }

    /**
     * Manually update source data
     *
     * @param int $id Source ID
     * @return array ['success' => bool, 'message' => string, 'saved' => int]
     * @throws moodle_exception
     */
    public static function update_source_manual($id) {
        global $DB;
        
        // Load source directly from DB to avoid persistent memory issues
        $sourcerecord = $DB->get_record('local_extcsv_sources', ['id' => $id], '*', MUST_EXIST);
        
        try {
            // We may need a lot of memory here.
            \core_php_time_limit::raise();
            raise_memory_limit(MEMORY_HUGE);

            // Create source object using model
            $source = new source_model();
            $source->from_record($sourcerecord);
            
            // Check if columns are configured
            $columnsconfig = $source->getColumnsConfig();
            if (empty($columnsconfig) || empty($columnsconfig['columns'])) {
                throw new moodle_exception('columnsnotconfigured', 'local_extcsv');
            }

            // Mark as pending
            $source->setUpdateStatus(source_model::UPDATE_STATUS_PENDING);

            // Import data
            $rows = csv_importer::import_from_source($source);

            // Save data
            $saved = data_manager::save_csv_data($source, $rows);

            // Mark as success
            $source->setUpdateStatus(source_model::UPDATE_STATUS_SUCCESS);

            return [
                'success' => true,
                'message' => get_string('sourceupdatesuccess', 'local_extcsv', $saved),
                'saved' => $saved
            ];

        } catch (\Exception $e) {
            // Mark as error
            $error = $e->getMessage();
            $source->setUpdateStatus(source_model::UPDATE_STATUS_ERROR, $error);

            return [
                'success' => false,
                'message' => get_string('sourceupdateerror', 'local_extcsv', $error),
                'saved' => 0
            ];
        }
    }
}

