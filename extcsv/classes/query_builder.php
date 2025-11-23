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
 * Query builder for local_extcsv
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extcsv;

defined('MOODLE_INTERNAL') || die();

use moodle_exception;

/**
 * Query builder class for preprocessing SQL queries with field name substitution
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class query_builder {

    /** @var source Source instance */
    protected $source;

    /** @var array Field name mapping: short_name => field_name */
    protected $fieldmapping = null;

    /**
     * Constructor
     *
     * @param source $source
     */
    public function __construct($source) {
        $this->source = $source;
        $this->build_field_mapping();
    }

    /**
     * Build field name mapping from source configuration
     */
    protected function build_field_mapping() {
        $columnsconfig = data_manager::parse_columns_config($this->source);
        if (empty($columnsconfig) || !isset($columnsconfig['columns'])) {
            $this->fieldmapping = [];
            return;
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

        $this->fieldmapping = $mapping;
    }

    /**
     * Get field mapping
     *
     * @return array
     */
    public function get_field_mapping() {
        return $this->fieldmapping;
    }

    /**
     * Replace logical field names with actual database field names in SQL query
     *
     * @param string $sql SQL query with {{field_name}} placeholders
     * @return string SQL query with replaced field names
     */
    public function replace_field_names($sql) {
        if (empty($this->fieldmapping)) {
            throw new moodle_exception('nofieldmapping', 'local_extcsv');
        }

        $replaced = $sql;
        foreach ($this->fieldmapping as $shortname => $fieldname) {
            // Replace {{short_name}} with actual field name
            $pattern = '/\{\{' . preg_quote($shortname, '/') . '\}\}/';
            $replaced = preg_replace($pattern, $fieldname, $replaced);
        }

        // Check if there are any unmatched placeholders
        if (preg_match('/\{\{([^}]+)\}\}/', $replaced, $matches)) {
            throw new moodle_exception('unknownfield', 'local_extcsv', '', $matches[1]);
        }

        return $replaced;
    }

    /**
     * Build SELECT query for source data
     *
     * @param array $fields Array of short field names to select
     * @param array $conditions Array of conditions ['field' => 'value'] or ['field' => ['operator' => '>', 'value' => 5]]
     * @param string|null $orderby Field name for ORDER BY
     * @param string $orderdir 'ASC' or 'DESC'
     * @param int|null $limit Limit number of rows
     * @return array ['sql' => string, 'params' => array]
     */
    public function build_select_query($fields = [], $conditions = [], $orderby = null, $orderdir = 'ASC', $limit = null) {
        global $DB;

        // Map field names
        if (empty($fields)) {
            // Select all mapped fields
            $selectfields = array_values($this->fieldmapping);
        } else {
            $selectfields = [];
            foreach ($fields as $field) {
                if (!isset($this->fieldmapping[$field])) {
                    throw new moodle_exception('unknownfield', 'local_extcsv', '', $field);
                }
                $selectfields[] = $this->fieldmapping[$field];
            }
        }

        // Build SELECT clause
        $select = 'SELECT id, sourceid, rownum, ' . implode(', ', $selectfields) . ' FROM {local_extcsv_data}';

        // Build WHERE clause
        $where = ['sourceid = :sourceid'];
        $params = ['sourceid' => $this->source->get('id')];

        foreach ($conditions as $field => $condition) {
            if (!isset($this->fieldmapping[$field])) {
                throw new moodle_exception('unknownfield', 'local_extcsv', '', $field);
            }
            $dbfield = $this->fieldmapping[$field];

            if (is_array($condition)) {
                $operator = $condition['operator'] ?? '=';
                $value = $condition['value'];
                $paramname = 'param' . count($params);
                $where[] = "{$dbfield} {$operator} :{$paramname}";
                $params[$paramname] = $value;
            } else {
                $paramname = 'param' . count($params);
                $where[] = "{$dbfield} = :{$paramname}";
                $params[$paramname] = $condition;
            }
        }

        $sql = $select . ' WHERE ' . implode(' AND ', $where);

        // Add ORDER BY
        if ($orderby !== null) {
            if (!isset($this->fieldmapping[$orderby])) {
                throw new moodle_exception('unknownfield', 'local_extcsv', '', $orderby);
            }
            $dbfield = $this->fieldmapping[$orderby];
            $sql .= ' ORDER BY ' . $dbfield . ' ' . $orderdir;
        }

        // Add LIMIT
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int)$limit;
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Execute a custom SQL query with field name replacement
     *
     * @param string $sql SQL query with {{field_name}} placeholders
     * @param array $params Query parameters
     * @return array|false Array of records or false on error
     */
    public function execute_query($sql, $params = []) {
        global $DB;

        // Replace field names
        $replacedsql = $this->replace_field_names($sql);

        // Add sourceid condition if not present
        if (stripos($replacedsql, 'sourceid') === false && stripos($replacedsql, 'WHERE') !== false) {
            // Add sourceid to WHERE clause
            $replacedsql = preg_replace('/WHERE/i', "WHERE sourceid = :sourceid AND ", $replacedsql);
            $params['sourceid'] = $this->source->get('id');
        } else if (stripos($replacedsql, 'sourceid') === false) {
            // Add WHERE clause
            $replacedsql .= ' WHERE sourceid = :sourceid';
            $params['sourceid'] = $this->source->get('id');
        }

        return $DB->get_records_sql($replacedsql, $params);
    }
}

