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
 * Public API for accessing extcsv sources and data.
 *
 * This class is designed to be used by other plugins (such as local_exam_sheet)
 * and provides a DB-like API (get_records/get_records_select) bound to a
 * particular CSV source, identified by its shortname.
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extcsv\api;

defined('MOODLE_INTERNAL') || die();

use moodle_exception;
use local_extcsv\source_manager;
use local_extcsv\model\source_model;
use local_extcsv\data_manager;

/**
 * Source API class.
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_api {

    /**
     * Get all sources.
     *
     * @param string|null $status Filter by status (enabled/disabled/frozen) or null for all.
     * @return source_model[]
     */
    public static function get_all_sources(?string $status = null): array {
        return source_manager::get_all_sources($status);
    }

    /**
     * Get enabled sources only.
     *
     * @return source_model[]
     */
    public static function get_enabled_sources(): array {
        return source_manager::get_enabled_sources();
    }

    /**
     * Get source by ID.
     *
     * @param int $id
     * @return source_model|null
     */
    public static function get_source_by_id(int $id): ?source_model {
        return source_manager::get_source($id);
    }

    /**
     * Get source by shortname.
     *
     * @param string $shortname
     * @return source_model|null
     */
    public static function get_source_by_shortname(string $shortname): ?source_model {
        return source_manager::get_source_by_shortname($shortname);
    }

    /**
     * Resolve source by shortname or throw an exception.
     *
     * @param string $shortname
     * @return source_model
     * @throws moodle_exception
     */
    protected static function require_source_by_shortname(string $shortname): source_model {
        $source = self::get_source_by_shortname($shortname);
        if (!$source) {
            throw new moodle_exception('sourcenotfound', 'local_extcsv');
        }
        return $source;
    }

    /**
     * Normalise field list for SQL.
     *
     * This keeps behaviour similar to moodle_database: a comma-separated list of
     * identifiers without backticks is acceptable. We leave it as-is to avoid
     * over-escaping and let $DB handle validation.
     *
     * @param string $fields
     * @return string
     */
    protected static function normalise_fields(string $fields): string {
        $fields = trim($fields);
        return $fields === '' ? '*' : $fields;
    }

    /**
     * Build field name mapping from columns_config (short_name => field_name).
     *
     * @param source_model $source
     * @return array Mapping: ['short_name' => 'field_name', ...]
     */
    protected static function build_field_mapping(source_model $source): array {
        $columnsconfig = data_manager::parse_columns_config($source);
        if (empty($columnsconfig) || !isset($columnsconfig['columns'])) {
            return [];
        }

        $mapping = [];
        foreach ($columnsconfig['columns'] as $colconfig) {
            $shortname = $colconfig['short_name'] ?? null;
            $type = $colconfig['type'] ?? 'text';
            $slot = $colconfig['slot'] ?? null;

            if (empty($shortname) || $slot === null) {
                continue;
            }

            $fieldname = data_manager::get_field_name($type, $slot);
            if ($fieldname !== null) {
                $mapping[$shortname] = $fieldname;
            }
        }

        return $mapping;
    }

    /**
     * Rename internal field names to logical names in records.
     *
     * @param array $records Array of stdClass records
     * @param source_model $source Source model
     * @return array Array of records with renamed fields
     */
    protected static function rename_fields_in_records(array $records, source_model $source): array {
        if (empty($records)) {
            return $records;
        }

        $fieldmapping = self::build_field_mapping($source);
        if (empty($fieldmapping)) {
            // No mapping available, return records as-is.
            return $records;
        }

        // Get lists of reserved and internal field names to identify user-defined fields.
        $reserved = data_manager::get_reserved_field_names();
        $internal = data_manager::get_all_internal_field_names();
        $allsystemfields = array_merge($reserved, $internal, array_values($fieldmapping));

        $renamed = [];
        foreach ($records as $record) {
            $newrecord = new \stdClass();

            // Copy all fields first (including system and user-defined).
            foreach ($record as $key => $value) {
                $newrecord->$key = $value;
            }

            // Rename mapped fields: copy value from internal field to logical field.
            foreach ($fieldmapping as $shortname => $fieldname) {
                if (isset($record->$fieldname)) {
                    $newrecord->$shortname = $record->$fieldname;
                    // Remove internal field name to hide it from external API.
                    unset($newrecord->$fieldname);
                }
            }

            $renamed[] = $newrecord;
        }

        return $renamed;
    }

    /**
     * Build SQL WHERE clause and params from an array of conditions.
     *
     * @param array|null $conditions
     * @param array $baseparams
     * @return array Array with keys ['sql', 'params']
     */
    protected static function build_where_from_conditions(?array $conditions, array $baseparams): array {
        $parts = ['sourceid = :sourceid'];
        $params = $baseparams;

        if (!empty($conditions)) {
            foreach ($conditions as $field => $value) {
                // Create a safe parameter name based on field name.
                $cleanfield = preg_replace('/[^a-zA-Z0-9_]/', '_', (string)$field);
                $paramname = 'p_' . $cleanfield . '_' . count($params);

                $parts[] = $field . ' = :' . $paramname;
                $params[$paramname] = $value;
            }
        }

        return [
            'sql' => implode(' AND ', $parts),
            'params' => $params,
        ];
    }

    /**
     * Get records for a given source (by shortname) using simple conditions array.
     *
     * Signature is intentionally similar to moodle_database::get_records().
     *
     * @param string $shortname Source shortname.
     * @param array|null $conditions Associative array of field => value.
     * @param string $sort Sort SQL (e.g. 'rownum ASC').
     * @param string $fields List of fields to return.
     * @param int $limitfrom Offset for paging.
     * @param int $limitnum Number of records to fetch.
     * @return array Array of stdClass records.
     * @throws moodle_exception
     */
    public static function get_records(
        string $shortname,
        ?array $conditions = null,
        string $sort = '',
        string $fields = '*',
        int $limitfrom = 0,
        int $limitnum = 0
    ): array {
        global $DB;

        $source = self::require_source_by_shortname($shortname);
        $sourceid = $source->getId();

        $fields = self::normalise_fields($fields);
        $whereinfo = self::build_where_from_conditions($conditions, ['sourceid' => $sourceid]);

        $records = $DB->get_records_select(
            'local_extcsv_data',
            $whereinfo['sql'],
            $whereinfo['params'],
            $sort,
            $fields,
            $limitfrom,
            $limitnum
        );

        return self::rename_fields_in_records($records, $source);
    }

    /**
     * Get records for a given source (by shortname) using raw SELECT fragment.
     *
     * Signature is intentionally similar to moodle_database::get_records_select().
     * The provided $select is automatically AND-joined with sourceid condition.
     *
     * @param string $shortname Source shortname.
     * @param string $select SQL fragment for WHERE clause (without the 'WHERE' keyword).
     * @param array|null $params Parameters for $select.
     * @param string $sort Sort SQL.
     * @param string $fields List of fields to return.
     * @param int $limitfrom Offset for paging.
     * @param int $limitnum Number of records to fetch.
     * @return array Array of stdClass records.
     * @throws moodle_exception
     */
    public static function get_records_select(
        string $shortname,
        string $select,
        ?array $params = null,
        string $sort = '',
        string $fields = '*',
        int $limitfrom = 0,
        int $limitnum = 0
    ): array {
        global $DB;

        $source = self::require_source_by_shortname($shortname);
        $sourceid = $source->getId();

        $fields = self::normalise_fields($fields);

        $params = $params ?? [];
        $params['sourceid'] = $sourceid;

        $select = trim($select);
        if ($select === '') {
            $where = 'sourceid = :sourceid';
        } else {
            $where = '(' . $select . ') AND sourceid = :sourceid';
        }

        $records = $DB->get_records_select(
            'local_extcsv_data',
            $where,
            $params,
            $sort,
            $fields,
            $limitfrom,
            $limitnum
        );

        return self::rename_fields_in_records($records, $source);
    }
}


