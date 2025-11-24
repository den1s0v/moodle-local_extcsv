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
 * Edit CSV source
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_extcsv\source_manager;
use local_extcsv\form\source_form;

// Check permissions
source_manager::require_manage_capability();

// Get source ID
$id = optional_param('id', 0, PARAM_INT);

// Get or create source - load directly from DB to avoid persistent memory issues
$source = null;
if ($id) {
    global $DB;
    $source = $DB->get_record('local_extcsv_sources', ['id' => $id], '*', MUST_EXIST);
}

// Page setup
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/extcsv/edit.php', ['id' => $id]));
$PAGE->set_title($id ? get_string('editsource', 'local_extcsv') : get_string('addsource', 'local_extcsv'));
$PAGE->set_heading($id ? get_string('editsource', 'local_extcsv') : get_string('addsource', 'local_extcsv'));
$PAGE->set_pagelayout('admin');

// Breadcrumb
$PAGE->navbar->add(get_string('sources', 'local_extcsv'), new moodle_url('/local/extcsv/index.php'));
$PAGE->navbar->add($id ? get_string('editsource', 'local_extcsv') : get_string('addsource', 'local_extcsv'));

// Form
$form = new source_form(null, ['source' => $source]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/extcsv/index.php'));
}

if ($data = $form->get_data()) {
    try {
        // Get ID from form data or URL parameter
        $sourceid = !empty($data->id) ? (int)$data->id : $id;
        
        if ($sourceid) {
            source_manager::update_source($sourceid, $data);
            $message = get_string('sourceupdated', 'local_extcsv');
        } else {
            source_manager::create_source($data);
            $message = get_string('sourceadded', 'local_extcsv');
        }
        redirect(new moodle_url('/local/extcsv/index.php'), $message, null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (\Exception $e) {
        redirect($PAGE->url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Output
echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();

