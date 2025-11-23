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
 * View source data
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_extcsv\source_manager;
use local_extcsv\data_manager;
use local_extcsv\query_builder;

// Check permissions
source_manager::require_manage_capability();

// Get source ID
$id = required_param('id', PARAM_INT);
// Load source directly to avoid potential memory issues
global $DB;
$sourcerecord = $DB->get_record('local_extcsv_sources', ['id' => $id], '*', MUST_EXIST);
$source = new \local_extcsv\source();
$source->from_record($sourcerecord);

// Page setup
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/extcsv/view.php', ['id' => $id]));
$PAGE->set_title(get_string('viewdata', 'local_extcsv'));
$PAGE->set_heading(get_string('viewdata', 'local_extcsv') . ': ' . $source->get('name'));
$PAGE->set_pagelayout('admin');

// Breadcrumb
$PAGE->navbar->add(get_string('sources', 'local_extcsv'), new moodle_url('/local/extcsv/index.php'));
$PAGE->navbar->add(get_string('viewdata', 'local_extcsv'));

// Pagination
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

// Get data with pagination
$total = data_manager::count_source_data($id);
$limitfrom = $page * $perpage;
$data = data_manager::get_source_data($id, $limitfrom, $perpage);

// Get column configuration
$columnsconfig = \local_extcsv\data_manager::parse_columns_config($source);

// Output
echo $OUTPUT->header();

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/extcsv/index.php'),
        get_string('back'),
        ['class' => 'btn btn-secondary']
    ) . ' ' .
    html_writer::link(
        new moodle_url('/local/extcsv/edit.php', ['id' => $id]),
        get_string('edit'),
        ['class' => 'btn btn-primary']
    ),
    'mb-3'
);

if ($total == 0) {
    echo html_writer::div(get_string('nodata', 'local_extcsv'), 'alert alert-info');
} else {
    echo html_writer::div(get_string('totalrows', 'local_extcsv', $total), 'mb-2');

    // Build table
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';

    // Build headers from column config
    $headers = ['ID', get_string('row', 'local_extcsv')];
    $fieldmapping = [];

    if ($columnsconfig && !empty($columnsconfig['columns'])) {
        foreach ($columnsconfig['columns'] as $colconfig) {
            $shortname = $colconfig['short_name'] ?? null;
            $type = $colconfig['type'] ?? 'text';
            $slot = $colconfig['slot'] ?? null;

            if ($shortname && $slot !== null) {
                $fieldname = data_manager::get_field_name($type, $slot);
                if ($fieldname) {
                    $headers[] = htmlspecialchars($shortname);
                    $fieldmapping[] = $fieldname;
                }
            }
        }
    }

    if (empty($fieldmapping)) {
        // No mapping, show limited info
        $table->head = ['ID', get_string('row', 'local_extcsv'), get_string('info', 'core')];
        foreach ($data as $record) {
            // Show only basic info to avoid memory issues
            $info = "Source ID: {$record->sourceid}, Row: {$record->rownum}";
            $table->data[] = [
                $record->id,
                $record->rownum,
                html_writer::div($info, 'small'),
            ];
        }
    } else {
        $table->head = $headers;
        foreach ($data as $record) {
            $row = [$record->id, $record->rownum];
            foreach ($fieldmapping as $fieldname) {
                $value = $record->$fieldname ?? '';
                // Limit text length to avoid memory issues
                if (is_string($value) && strlen($value) > 200) {
                    $value = substr($value, 0, 200) . '...';
                }
                if (is_numeric($value) && strlen((string)$value) > 0) {
                    $row[] = $value;
                } else if (empty($value)) {
                    $row[] = '-';
                } else {
                    $row[] = htmlspecialchars($value);
                }
            }
            $table->data[] = $row;
        }
    }

    echo html_writer::table($table);

    // Pagination
    if ($total > $perpage) {
        echo $OUTPUT->paging_bar($total, $page, $perpage, $PAGE->url);
    }
}

echo $OUTPUT->footer();

