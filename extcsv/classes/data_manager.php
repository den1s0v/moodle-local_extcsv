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
 * Data manager for local_extcsv
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extcsv;

defined('MOODLE_INTERNAL') || die();

use moodle_exception;
use local_extcsv\tools\pattern_tester;
use local_extcsv\model\source_model;

/**
 * Data manager class
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_manager {

    /** Type: text */
    const TYPE_TEXT = 'text';

    /** Type: int */
    const TYPE_INT = 'int';

    /** Type: float */
    const TYPE_FLOAT = 'float';

    /** Type: bool */
    const TYPE_BOOL = 'bool';

    /** Type: date */
    const TYPE_DATE = 'date';

    /** Type: json */
    const TYPE_JSON = 'json';

    /** Maximum slot counts */
    const MAX_TEXT = 20;
    const MAX_INT = 20;
    const MAX_FLOAT = 5;
    const MAX_BOOL = 5;
    const MAX_DATE = 10;
    const MAX_JSON = 3;

    /**
     * Get field name by type and slot
     *
     * @param string $type
     * @param int $slot
     * @return string|null
     */
    public static function get_field_name($type, $slot) {
        $maxslots = [
            self::TYPE_TEXT => self::MAX_TEXT,
            self::TYPE_INT => self::MAX_INT,
            self::TYPE_FLOAT => self::MAX_FLOAT,
            self::TYPE_BOOL => self::MAX_BOOL,
            self::TYPE_DATE => self::MAX_DATE,
            self::TYPE_JSON => self::MAX_JSON,
        ];

        if (!isset($maxslots[$type]) || $slot < 1 || $slot > $maxslots[$type]) {
            return null;
        }

        return "{$type}{$slot}";
    }


    /**
     * Parse columns configuration from source, DB record, or JSON string
     *
     * @param source_model|\stdClass|string|null $source Source object, DB record, or JSON string
     * @return array|null Parsed columns configuration or null
     */
    public static function parse_columns_config($source) {
        if ($source === null) {
            return null;
        }
        
        $columnsconfigraw = null;
        
        // If it's a string, treat it as JSON
        if (is_string($source)) {
            $columnsconfigraw = $source;
        }
        // If it's a DB record (stdClass), get columns_config property directly
        else if ($source instanceof \stdClass) {
            $columnsconfigraw = $source->columns_config ?? null;
        }
        // If it's a source model object, use getColumnsConfig method
        else if (is_object($source) && method_exists($source, 'getColumnsConfig')) {
            return $source->getColumnsConfig();
        }
        // Fallback: try to use get() method
        else if (is_object($source) && method_exists($source, 'get')) {
            $columnsconfigraw = $source->get('columns_config');
        }
        
        if (empty($columnsconfigraw)) {
            return null;
        }
        
        $columnsconfig = json_decode($columnsconfigraw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return $columnsconfig;
    }

    /**
     * Match CSV column name with pattern
     *
     * @param string $columnname
     * @param string $pattern
     * @return bool
     */
    protected static function match_pattern($columnname, $pattern) {
        try {
            $tester = new pattern_tester($pattern);
            return $tester->test($columnname);
        } catch (\InvalidArgumentException $e) {
            // Invalid pattern, return false
            return false;
        }
    }

    /**
     * Automatically assign slots to selected columns
     *
     * @param array $selectedcolumns Array of ['column_name' => string, 'type' => string, 'short_name' => string]
     * @return array Columns configuration with assigned slots
     * @throws moodle_exception If field limits are exceeded
     */
    public static function assign_slots_automatically($selectedcolumns) {
        $maxslots = [
            self::TYPE_TEXT => self::MAX_TEXT,
            self::TYPE_INT => self::MAX_INT,
            self::TYPE_FLOAT => self::MAX_FLOAT,
            self::TYPE_BOOL => self::MAX_BOOL,
            self::TYPE_DATE => self::MAX_DATE,
            self::TYPE_JSON => self::MAX_JSON,
        ];

        // Count columns by type
        $typecounts = [
            self::TYPE_TEXT => 0,
            self::TYPE_INT => 0,
            self::TYPE_FLOAT => 0,
            self::TYPE_BOOL => 0,
            self::TYPE_DATE => 0,
            self::TYPE_JSON => 0,
        ];

        foreach ($selectedcolumns as $col) {
            $type = $col['type'] ?? self::TYPE_TEXT;
            if (isset($typecounts[$type])) {
                $typecounts[$type]++;
            }
        }

        // Validate limits
        foreach ($typecounts as $type => $count) {
            if ($count > $maxslots[$type]) {
                $a = (object)[
                    'type' => $type,
                    'max' => $maxslots[$type],
                    'selected' => $count
                ];
                throw new moodle_exception('fieldlimitreached', 'local_extcsv', null, $a);
            }
        }

        // Assign slots
        $slotcounters = [
            self::TYPE_TEXT => 0,
            self::TYPE_INT => 0,
            self::TYPE_FLOAT => 0,
            self::TYPE_BOOL => 0,
            self::TYPE_DATE => 0,
            self::TYPE_JSON => 0,
        ];

        $columnsconfig = [];
        foreach ($selectedcolumns as $col) {
            $type = $col['type'] ?? self::TYPE_TEXT;
            $shortname = $col['short_name'] ?? $col['column_name'] ?? '';
            $pattern = $col['pattern'] ?? $col['column_name'] ?? '';

            $slotcounters[$type]++;
            $slot = $slotcounters[$type];

            $columnsconfig[] = [
                'pattern' => $pattern,
                'type' => $type,
                'slot' => $slot,
                'short_name' => $shortname,
            ];
        }

        return ['columns' => $columnsconfig];
    }

    /**
     * Build column mapping from CSV headers and source configuration
     *
     * @param array $csvheaders CSV header row
     * @param array $columnsconfig Columns configuration from source
     * @return array Mapping: ['csv_index' => ['type' => ..., 'slot' => ..., 'short_name' => ...], ...]
     */
    public static function build_column_mapping($csvheaders, $columnsconfig) {
        if (empty($columnsconfig) || !isset($columnsconfig['columns'])) {
            return [];
        }

        $mapping = [];
        $slotcounters = [
            self::TYPE_TEXT => 0,
            self::TYPE_INT => 0,
            self::TYPE_FLOAT => 0,
            self::TYPE_BOOL => 0,
            self::TYPE_DATE => 0,
            self::TYPE_JSON => 0,
        ];

        foreach ($csvheaders as $colindex => $headername) {
            // Try to match with configured columns
            foreach ($columnsconfig['columns'] as $colconfig) {
                $pattern = $colconfig['pattern'] ?? '';
                if (empty($pattern)) {
                    continue;
                }

                if (self::match_pattern($headername, $pattern)) {
                    $type = $colconfig['type'] ?? self::TYPE_TEXT;
                    $shortname = $colconfig['short_name'] ?? null;

                    // Use configured slot or assign next available
                    if (isset($colconfig['slot'])) {
                        $slot = (int)$colconfig['slot'];
                    } else {
                        $slotcounters[$type]++;
                        $slot = $slotcounters[$type];
                    }

                    // Validate slot
                    $fieldname = self::get_field_name($type, $slot);
                    if ($fieldname === null) {
                        continue; // Skip invalid slot
                    }

                    $mapping[$colindex] = [
                        'type' => $type,
                        'slot' => $slot,
                        'field' => $fieldname,
                        'short_name' => $shortname,
                    ];
                    break; // First match wins
                }
            }
        }

        return $mapping;
    }

    /**
     * Convert value to appropriate type
     *
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    protected static function convert_value($value, $type) {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        switch ($type) {
            case self::TYPE_INT:
                return (int)$value;

            case self::TYPE_FLOAT:
                return (float)$value;

            case self::TYPE_BOOL:
                $val = strtolower($value);
                return in_array($val, ['1', 'true', 'yes', 'y', 'да', 'д'], true) ? 1 : 0;

            case self::TYPE_DATE:
                // Try to parse date string to timestamp
                $timestamp = strtotime($value);
                return $timestamp !== false ? $timestamp : null;

            case self::TYPE_JSON:
                // Validate JSON
                $decoded = json_decode($value, true);
                return json_last_error() === JSON_ERROR_NONE ? json_encode($decoded, JSON_UNESCAPED_UNICODE) : $value;

            case self::TYPE_TEXT:
            default:
                return $value;
        }
    }

    /**
     * Save CSV rows to database
     *
     * @param source_model $source
     * @param array $csvrows Array of CSV rows (first row should be headers)
     * @return int Number of rows saved
     * @throws moodle_exception
     */
    public static function save_csv_data($source, $csvrows) {
        global $DB;

        if (empty($csvrows)) {
            return 0;
        }

        // Get source ID
        $sourceid = $source->getId();
        if ($sourceid === null) {
            throw new moodle_exception('invalidoperation', 'local_extcsv');
        }
        
        // Get columns_config
        $columnsconfig = self::parse_columns_config($source);

        // First row is headers
        $headers = array_shift($csvrows);
        if (empty($headers)) {
            throw new moodle_exception('invalidcsvheaders', 'local_extcsv');
        }

        // Build column mapping
        $mapping = self::build_column_mapping($headers, $columnsconfig);
        if (empty($mapping)) {
            throw new moodle_exception('nocolumnsmapped', 'local_extcsv');
        }

        // Delete existing data for this source
        $DB->delete_records('local_extcsv_data', ['sourceid' => $sourceid]);

        // Insert new data
        $saved = 0;
        $timecreated = time();

        foreach ($csvrows as $rownum => $row) {
            // Skip empty rows
            if (empty(array_filter($row, function($val) { return trim($val) !== ''; }))) {
                continue;
            }

            $record = new \stdClass();
            $record->sourceid = $sourceid;
            $record->rownum = $rownum + 1; // 1-based row numbers
            $record->timecreated = $timecreated;

            // Map values according to mapping
            foreach ($mapping as $colindex => $mapinfo) {
                $value = isset($row[$colindex]) ? $row[$colindex] : '';
                $converted = self::convert_value($value, $mapinfo['type']);
                $fieldname = $mapinfo['field'];
                $record->$fieldname = $converted;
            }

            $DB->insert_record('local_extcsv_data', $record);
            $saved++;
        }

        return $saved;
    }

    /**
     * Delete all data for a source
     *
     * @param int $sourceid
     */
    public static function delete_source_data($sourceid) {
        global $DB;
        $DB->delete_records('local_extcsv_data', ['sourceid' => $sourceid]);
    }

    /**
     * Get data rows for a source
     *
     * @param int $sourceid
     * @param int $limitfrom
     * @param int $limitnum
     * @param string $fields Fields to select (default: '*' for all fields)
     * @return array
     */
    public static function get_source_data($sourceid, $limitfrom = 0, $limitnum = 0, $fields = '*') {
        global $DB;
        
        // Escape field names with backticks to avoid SQL syntax errors
        if ($fields !== '*') {
            $fieldlist = explode(',', $fields);
            $fieldlist = array_map('trim', $fieldlist);
            $fieldlist = array_map(function($field) {
                // Escape field name with backticks
                return '`' . str_replace('`', '``', $field) . '`';
            }, $fieldlist);
            $fields = implode(',', $fieldlist);
        }
        
        return $DB->get_records_select(
            'local_extcsv_data',
            'sourceid = :sourceid',
            ['sourceid' => $sourceid],
            'rownum ASC',
            $fields,
            $limitfrom,
            $limitnum
        );
    }

    /**
     * Count data rows for a source
     *
     * @param int $sourceid
     * @return int
     */
    public static function count_source_data($sourceid) {
        global $DB;
        return $DB->count_records('local_extcsv_data', ['sourceid' => $sourceid]);
    }
}

