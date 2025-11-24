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
 * Preview CSV columns
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_extcsv\source_manager;
use local_extcsv\csv_importer;
use local_extcsv\data_manager;
use local_extcsv\form\column_mapping_form;

// Check permissions
source_manager::require_manage_capability();

// Get source ID
$id = required_param('id', PARAM_INT);

// Load source directly from DB to avoid persistent memory issues
global $DB;
$sourcerecord = $DB->get_record('local_extcsv_sources', ['id' => $id], '*', MUST_EXIST);

// Create source object only when needed (for saving)
$source = new \local_extcsv\source();
$source->from_record($sourcerecord);

// Page setup
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/extcsv/preview.php', ['id' => $id]));
$PAGE->set_title(get_string('preview', 'local_extcsv'));
// Use DB record directly to avoid persistent get() calls
$PAGE->set_heading(get_string('preview', 'local_extcsv') . ': ' . $sourcerecord->name);
$PAGE->set_pagelayout('admin');

// Breadcrumb
$PAGE->navbar->add(get_string('sources', 'local_extcsv'), new moodle_url('/local/extcsv/index.php'));
$PAGE->navbar->add(get_string('preview', 'local_extcsv'));

// Try to get preview
$preview = null;
$error = null;

try {
    // Use DB record directly to avoid persistent get() calls
    $url = $sourcerecord->url;
    $contenttype = $sourcerecord->content_type;
    
    $processedurl = csv_importer::process_google_sheets_url($url, $contenttype);
    $content = csv_importer::download_content($processedurl);
    $preview = csv_importer::get_preview($content, $contenttype);
} catch (\Exception $e) {
    $error = $e->getMessage();
}

// Handle column mapping form submission
$existingconfig = data_manager::parse_columns_config($sourcerecord);
$mappingform = null;
$mappingsaved = false;

if (!$error && !empty($preview['headers'])) {
    $formdata = ['headers' => $preview['headers'], 'existing_config' => $existingconfig];
    $mappingform = new column_mapping_form(null, $formdata);
    $mappingform->set_data(['sourceid' => $id]);
    
    if ($mappingform->is_cancelled()) {
        redirect(new moodle_url('/local/extcsv/index.php'));
    }
    
    if ($data = $mappingform->get_processed_data()) {
        // Save columns configuration
        $configjson = json_encode($data);
        $source->set('columns_config', $configjson);
        $source->save();
        $mappingsaved = true;
        
        redirect(
            new moodle_url('/local/extcsv/preview.php', ['id' => $id]),
            get_string('mappingsaved', 'local_extcsv'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Output
echo $OUTPUT->header();

if ($error) {
    echo html_writer::div($error, 'alert alert-danger');
    echo html_writer::link(
        new moodle_url('/local/extcsv/edit.php', ['id' => $id]),
        get_string('back'),
        ['class' => 'btn btn-secondary']
    );
} else {
    // Show sample rows
    if (!empty($preview['rows'])) {
        echo html_writer::tag('h3', get_string('samplerows', 'local_extcsv'));
        $table = new html_table();
        $table->head = array_merge([get_string('row', 'local_extcsv')], $preview['headers']);
        $table->attributes['class'] = 'generaltable';

        foreach ($preview['rows'] as $rownum => $row) {
            $rowdata = [$rownum + 1];
            foreach ($row as $cell) {
                $rowdata[] = htmlspecialchars($cell);
            }
            // Pad row if needed
            while (count($rowdata) < count($table->head)) {
                $rowdata[] = '';
            }
            $table->data[] = $rowdata;
        }
        echo html_writer::table($table);
    }

    // Show column mapping form
    if ($mappingform) {
        echo html_writer::tag('h3', get_string('mapcolumns', 'local_extcsv'));
        $mappingform->display();
    }
    
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/local/extcsv/edit.php', ['id' => $id]),
            get_string('back'),
            ['class' => 'btn btn-secondary']
        ),
        'mt-3'
    );
}

echo $OUTPUT->footer();

