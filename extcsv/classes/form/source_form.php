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
 * Source form
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_extcsv\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use moodleform;

/**
 * Form for creating/editing sources
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_form extends moodleform {

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;
        $source = $this->_customdata['source'] ?? null;

        // Hidden field for ID (if editing)
        if ($source && $source->get('id')) {
            $mform->addElement('hidden', 'id', $source->get('id'));
            $mform->setType('id', PARAM_INT);
        }

        // Name
        $mform->addElement('text', 'name', get_string('name', 'local_extcsv'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Description
        $mform->addElement('textarea', 'description', get_string('description', 'local_extcsv'), ['rows' => 3]);
        $mform->setType('description', PARAM_TEXT);

        // Status
        $mform->addElement('select', 'status', get_string('status', 'local_extcsv'), [
            \local_extcsv\source::STATUS_ENABLED => get_string('status_enabled', 'local_extcsv'),
            \local_extcsv\source::STATUS_DISABLED => get_string('status_disabled', 'local_extcsv'),
            \local_extcsv\source::STATUS_FROZEN => get_string('status_frozen', 'local_extcsv'),
        ]);
        $mform->setDefault('status', \local_extcsv\source::STATUS_DISABLED);

        // URL
        $mform->addElement('textarea', 'url', get_string('url', 'local_extcsv'), ['rows' => 2]);
        $mform->setType('url', PARAM_TEXT);
        $mform->addRule('url', null, 'required', null, 'client');
        $mform->addHelpButton('url', 'url', 'local_extcsv');

        // Content type
        $mform->addElement('select', 'content_type', get_string('content_type', 'local_extcsv'), [
            \local_extcsv\source::CONTENT_TYPE_CSV => get_string('content_type_csv', 'local_extcsv'),
            \local_extcsv\source::CONTENT_TYPE_TSV => get_string('content_type_tsv', 'local_extcsv'),
        ]);
        $mform->setDefault('content_type', \local_extcsv\source::CONTENT_TYPE_CSV);

        // Schedule mode
        $mform->addElement('select', 'schedule_mode', get_string('schedule_mode', 'local_extcsv'), [
            'simple' => get_string('schedule_mode_simple', 'local_extcsv'),
            'advanced' => get_string('schedule_mode_advanced', 'local_extcsv'),
        ]);
        $mform->setDefault('schedule_mode', 'simple');

        // Simple schedule (interval)
        $intervalgroup = [];
        $intervalgroup[] = $mform->createElement('text', 'interval_value', '', ['size' => 5]);
        $intervalgroup[] = $mform->createElement('select', 'interval_unit', '', [
            'minutes' => get_string('interval_minutes', 'local_extcsv'),
            'hours' => get_string('interval_hours', 'local_extcsv'),
            'days' => get_string('interval_days', 'local_extcsv'),
        ]);
        $mform->addGroup($intervalgroup, 'schedule_interval', get_string('schedule_interval', 'local_extcsv'), ' ', false);
        $mform->setType('interval_value', PARAM_INT);
        $mform->disabledIf('schedule_interval', 'schedule_mode', 'eq', 'advanced');

        // Advanced schedule (cron)
        $mform->addElement('text', 'schedule_cron', get_string('schedule_cron', 'local_extcsv'), ['size' => 50]);
        $mform->setType('schedule_cron', PARAM_TEXT);
        $mform->addHelpButton('schedule_cron', 'schedule_cron', 'local_extcsv');
        $mform->disabledIf('schedule_cron', 'schedule_mode', 'eq', 'simple');

        // Buttons
        $this->add_action_buttons();

        // Set defaults if editing
        if ($source) {
            $mform->setDefault('name', $source->get('name'));
            $mform->setDefault('description', $source->get('description'));
            $mform->setDefault('status', $source->get('status'));
            $mform->setDefault('url', $source->get('url'));
            $mform->setDefault('content_type', $source->get('content_type'));

            $schedule = $source->get('schedule');
            if ($schedule) {
                // Try to detect if it's a cron expression
                if (preg_match('/^\s*(\d+)\s+(minute|hour|day)s?\s*$/i', $schedule, $matches)) {
                    $mform->setDefault('schedule_mode', 'simple');
                    $mform->setDefault('interval_value', $matches[1]);
                    $mform->setDefault('interval_unit', $matches[2] . 's');
                } else {
                    $mform->setDefault('schedule_mode', 'advanced');
                    $mform->setDefault('schedule_cron', $schedule);
                }
            }
        }
    }

    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate schedule
        if ($data['schedule_mode'] === 'simple') {
            // Interval is optional, but if provided must be positive
            if (!empty($data['interval_value']) && $data['interval_value'] <= 0) {
                $errors['schedule_interval'] = get_string('invalidinterval', 'local_extcsv');
            }
        } else {
            // Cron expression is required in advanced mode
            if (empty(trim($data['schedule_cron']))) {
                $errors['schedule_cron'] = get_string('required');
            }
        }

        // Validate URL
        if (!empty($data['url'])) {
            if (!filter_var(trim($data['url']), FILTER_VALIDATE_URL) && 
                strpos($data['url'], 'docs.google.com/spreadsheets') === false) {
                // Allow Google Sheets URLs even if not valid URL format
            } else if (strpos($data['url'], 'docs.google.com/spreadsheets') === false) {
                // Check if it's a valid URL
                if (!filter_var(trim($data['url']), FILTER_VALIDATE_URL)) {
                    $errors['url'] = get_string('invalidurl', 'local_extcsv');
                }
            }
        }

        return $errors;
    }

    /**
     * Get data as object
     *
     * @return \stdClass
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            // Build schedule string
            if ($data->schedule_mode === 'simple') {
                // If interval is provided, build schedule string, otherwise set to null
                if (!empty($data->interval_value) && $data->interval_value > 0) {
                    $data->schedule = $data->interval_value . ' ' . $data->interval_unit;
                } else {
                    $data->schedule = null;
                }
            } else {
                // Advanced mode: use cron expression, trim whitespace
                $data->schedule = !empty($data->schedule_cron) ? trim($data->schedule_cron) : null;
            }
            
            // Remove form helper fields that shouldn't be saved
            unset($data->schedule_mode);
            unset($data->interval_value);
            unset($data->interval_unit);
            unset($data->schedule_cron);
            
            // Ensure system fields are not present
            unset($data->id);
            unset($data->timecreated);
            unset($data->timemodified);
            unset($data->lastupdate);
            unset($data->lastupdatestatus);
            unset($data->lastupdateerror);
        }
        return $data;
    }
}

