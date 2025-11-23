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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extcsv;

defined('MOODLE_INTERNAL') || die();

use core\persistent;

/**
 * Source persistent model
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source extends persistent {

    /** @var string Table name */
    const TABLE = 'local_extcsv_sources';

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
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'name' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'description' => [
                'type' => PARAM_TEXT,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
            'status' => [
                'type' => PARAM_TEXT,
                'default' => self::STATUS_DISABLED,
                'choices' => [
                    self::STATUS_ENABLED,
                    self::STATUS_DISABLED,
                    self::STATUS_FROZEN,
                ],
            ],
            'url' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'content_type' => [
                'type' => PARAM_TEXT,
                'default' => self::CONTENT_TYPE_CSV,
                'choices' => [
                    self::CONTENT_TYPE_CSV,
                    self::CONTENT_TYPE_TSV,
                ],
            ],
            'schedule' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'columns_config' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'timecreated' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'timemodified' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'lastupdate' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'lastupdatestatus' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
                'choices' => [
                    self::UPDATE_STATUS_SUCCESS,
                    self::UPDATE_STATUS_ERROR,
                    self::UPDATE_STATUS_PENDING,
                ],
            ],
            'lastupdateerror' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
        ];
    }

    /**
     * Get columns configuration as array
     *
     * @return array|null
     */
    protected function get_columns_config() {
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
     */
    protected function set_columns_config($config) {
        if ($config === null) {
            $this->set('columns_config', null);
        } else {
            $this->set('columns_config', json_encode($config, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Hook to execute before create.
     */
    protected function before_create() {
        $this->set('timecreated', time());
        $this->set('timemodified', time());
    }

    /**
     * Hook to execute before update.
     */
    protected function before_update() {
        $this->set('timemodified', time());
    }

    /**
     * Check if source is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        return $this->get('status') === self::STATUS_ENABLED;
    }

    /**
     * Check if source is frozen
     *
     * @return bool
     */
    public function is_frozen() {
        return $this->get('status') === self::STATUS_FROZEN;
    }

    /**
     * Update last update status
     *
     * @param string $status
     * @param string|null $error
     */
    public function set_update_status($status, $error = null) {
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

