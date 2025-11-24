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
 * Base model abstract class for local_extcsv
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace local_extcsv\model;

defined('MOODLE_INTERNAL') || die();

/**
 * Base abstract model class for database entities
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
abstract class base_model {

    /** @var \stdClass Data record */
    protected $data;

    /** @var int|null Record ID */
    protected $id;

    /**
     * Constructor
     *
     * @param int|\stdClass|null $id_or_record ID or DB record
     */
    public function __construct($id_or_record = null) {
        $this->data = new \stdClass();
        
        if ($id_or_record === null) {
            return;
        }
        
        if (is_int($id_or_record) || is_string($id_or_record)) {
            $this->load((int)$id_or_record);
        } else if (is_object($id_or_record)) {
            $this->from_record($id_or_record);
        }
    }

    /**
     * Get table name
     *
     * @return string
     */
    abstract protected function get_table_name();

    /**
     * Get primary key field name
     *
     * @return string
     */
    protected function get_primary_key() {
        return 'id';
    }

    /**
     * Validate data before save
     *
     * @return bool
     */
    protected function validate() {
        return true;
    }

    /**
     * Load record from database
     *
     * @param int $id Record ID
     * @return bool Success
     * @throws \dml_missing_record_exception
     */
    public function load($id) {
        global $DB;
        
        $record = $DB->get_record($this->get_table_name(), [$this->get_primary_key() => $id], '*', MUST_EXIST);
        $this->from_record($record);
        return true;
    }

    /**
     * Load data from record
     *
     * @param \stdClass $record DB record
     * @return void
     */
    public function from_record($record) {
        $this->data = clone $record;
        $pk = $this->get_primary_key();
        $this->id = isset($this->data->$pk) ? (int)$this->data->$pk : null;
    }

    /**
     * Convert to DB record
     *
     * @return \stdClass
     */
    public function to_record() {
        return clone $this->data;
    }

    /**
     * Get field value
     *
     * @param string $field Field name
     * @return mixed
     */
    public function get($field) {
        return $this->data->$field ?? null;
    }

    /**
     * Set field value
     *
     * @param string $field Field name
     * @param mixed $value Value
     * @return void
     */
    public function set($field, $value) {
        $this->data->$field = $value;
        
        // Update ID if primary key is set
        if ($field === $this->get_primary_key()) {
            $this->id = $value ? (int)$value : null;
        }
    }

    /**
     * Get record ID
     *
     * @return int|null
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Save record to database
     *
     * @return bool Success
     * @throws \moodle_exception
     */
    public function save() {
        global $DB;
        
        // Call before_save hook if method exists
        if (method_exists($this, 'before_save')) {
            $this->before_save();
        }
        
        if (!$this->validate()) {
            throw new \moodle_exception('validationfailed', 'local_extcsv');
        }
        
        $pk = $this->get_primary_key();
        $isnew = empty($this->id);
        
        if ($isnew) {
            // Create new record
            $record = clone $this->data;
            unset($record->$pk); // Remove ID for insert
            $this->id = $DB->insert_record($this->get_table_name(), $record);
            $this->set($pk, $this->id);
        } else {
            // Update existing record
            $record = clone $this->data;
            $record->$pk = $this->id;
            $DB->update_record($this->get_table_name(), $record);
        }
        
        return true;
    }

    /**
     * Delete record from database
     *
     * @return bool Success
     */
    public function delete() {
        global $DB;
        
        if (empty($this->id)) {
            return false;
        }
        
        $pk = $this->get_primary_key();
        $DB->delete_records($this->get_table_name(), [$pk => $this->id]);
        
        $this->id = null;
        $this->data = new \stdClass();
        
        return true;
    }

    /**
     * Check if record exists (is loaded)
     *
     * @return bool
     */
    public function exists() {
        return !empty($this->id);
    }

    /**
     * Create new record from data
     *
     * @param \stdClass $data Data to create record with
     * @return static New model instance
     */
    public static function create($data) {
        $instance = new static();
        foreach ((array)$data as $field => $value) {
            $instance->set($field, $value);
        }
        $instance->save();
        return $instance;
    }
}

