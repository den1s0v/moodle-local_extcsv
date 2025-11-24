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
 * Source model for local_extcsv
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace local_extcsv\model;

defined('MOODLE_INTERNAL') || die();

/**
 * Source model class
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
class source_model extends base_model {

    /** Status: enabled */
    const STATUS_ENABLED = 'enabled';

    /** Status: disabled */
    const STATUS_DISABLED = 'disabled';

    /** Status: frozen */
    const STATUS_FROZEN = 'frozen';

    /** Content type: CSV */
    const CONTENT_TYPE_CSV = 'csv';

    /** Content type: TSV */
    const CONTENT_TYPE_TSV = 'tsv';

    /** Update status: success */
    const UPDATE_STATUS_SUCCESS = 'success';

    /** Update status: error */
    const UPDATE_STATUS_ERROR = 'error';

    /** Update status: pending */
    const UPDATE_STATUS_PENDING = 'pending';

    /**
     * Get table name
     *
     * @return string
     */
    protected function get_table_name() {
        return 'local_extcsv_sources';
    }

    /**
     * Validate data before save
     *
     * @return bool
     */
    protected function validate() {
        // Validate required fields
        if (empty($this->data->name)) {
            return false;
        }
        if (empty($this->data->url)) {
            return false;
        }
        
        // Validate status
        $validstatuses = [self::STATUS_ENABLED, self::STATUS_DISABLED, self::STATUS_FROZEN];
        if (!empty($this->data->status) && !in_array($this->data->status, $validstatuses, true)) {
            return false;
        }
        
        // Validate content type
        $validcontenttypes = [self::CONTENT_TYPE_CSV, self::CONTENT_TYPE_TSV];
        if (!empty($this->data->content_type) && !in_array($this->data->content_type, $validcontenttypes, true)) {
            return false;
        }
        
        return true;
    }

    /**
     * Hook before save - set timestamps
     *
     * @return void
     */
    protected function before_save() {
        $isnew = empty($this->id);
        
        if ($isnew) {
            if (empty($this->data->timecreated)) {
                $this->set('timecreated', time());
            }
        }
        
        $this->set('timemodified', time());
        
        // Set defaults if not set
        if (empty($this->data->status)) {
            $this->set('status', self::STATUS_DISABLED);
        }
        if (empty($this->data->content_type)) {
            $this->set('content_type', self::CONTENT_TYPE_CSV);
        }
    }

    /**
     * Check if source is enabled
     *
     * @return bool
     */
    public function isEnabled() {
        return $this->get('status') === self::STATUS_ENABLED;
    }

    /**
     * Check if source is frozen
     *
     * @return bool
     */
    public function isFrozen() {
        return $this->get('status') === self::STATUS_FROZEN;
    }

    /**
     * Get columns configuration as array
     *
     * @return array|null
     */
    public function getColumnsConfig() {
        $config = $this->get('columns_config');
        if (empty($config)) {
            return null;
        }
        $decoded = json_decode($config, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $decoded;
    }

    /**
     * Set columns configuration from array
     *
     * @param array|null $config
     * @return void
     */
    public function setColumnsConfig($config) {
        if ($config === null) {
            $this->set('columns_config', null);
        } else {
            $this->set('columns_config', json_encode($config, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Update last update status
     *
     * @param string $status
     * @param string|null $error
     * @return void
     */
    public function setUpdateStatus($status, $error = null) {
        $this->set('lastupdate', time());
        $this->set('lastupdatestatus', $status);
        if ($error !== null) {
            $this->set('lastupdateerror', $error);
        } else {
            $this->set('lastupdateerror', null);
        }
        $this->save();
    }
}

