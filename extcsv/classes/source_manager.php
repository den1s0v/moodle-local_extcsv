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
     * @return source[]
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
            $source = new source();
            $source->from_record($record);
            $sources[] = $source;
        }
        return $sources;
    }

    /**
     * Get source by ID
     *
     * @param int $id
     * @return source|null
     */
    public static function get_source($id) {
        try {
            return new source($id);
        } catch (\dml_missing_record_exception $e) {
            return null;
        }
    }

    /**
     * Get enabled sources
     *
     * @return source[]
     */
    public static function get_enabled_sources() {
        return self::get_all_sources(source::STATUS_ENABLED);
    }

    /**
     * Create new source
     *
     * @param \stdClass $data
     * @return source
     */
    public static function create_source($data) {
        // Filter only allowed fields from form data
        $allowedfields = ['name', 'description', 'status', 'url', 'content_type', 'schedule', 'columns_config'];
        $sourcedata = new \stdClass();
        foreach ($allowedfields as $field) {
            if (isset($data->$field)) {
                $sourcedata->$field = $data->$field;
            }
        }
        // Create source without passing data directly to constructor to avoid validation issues
        $source = new source();
        foreach ($allowedfields as $field) {
            if (isset($sourcedata->$field)) {
                $source->set($field, $sourcedata->$field);
            }
        }
        $source->save();
        return $source;
    }

    /**
     * Update source
     *
     * @param int $id
     * @param \stdClass $data
     * @return source
     * @throws moodle_exception
     */
    public static function update_source($id, $data) {
        $source = self::get_source($id);
        if (!$source) {
            throw new moodle_exception('sourcenotfound', 'local_extcsv');
        }
        // Ensure we're updating an existing record (check ID)
        $sourceid = $source->get('id');
        if (empty($sourceid) || $sourceid != $id) {
            throw new moodle_exception('sourcenotfound', 'local_extcsv');
        }
        // Filter only allowed fields from form data (exclude system fields and id)
        $allowedfields = ['name', 'description', 'status', 'url', 'content_type', 'schedule', 'columns_config'];
        // Explicitly exclude id from being set
        if (isset($data->id)) {
            unset($data->id);
        }
        foreach ($allowedfields as $field) {
            if (isset($data->$field)) {
                try {
                    $source->set($field, $data->$field);
                } catch (\coding_exception $e) {
                    // Property doesn't exist or invalid value, skip it
                    continue;
                }
            }
        }
        // Double-check that ID is still set before saving
        $finalid = $source->get('id');
        if (empty($finalid) || $finalid != $id) {
            throw new moodle_exception('sourcenotfound', 'local_extcsv');
        }
        // save() will update existing record if ID is set and object was loaded from DB
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

        // Delete source
        $source->delete();
        return true;
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
}

