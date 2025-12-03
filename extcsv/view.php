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
// Load source using source_manager
$source = source_manager::get_source($id);
if (!$source) {
    throw new moodle_exception('sourcenotfound', 'local_extcsv');
}

// Page setup
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/extcsv/view.php', ['id' => $id]));
$PAGE->set_title(get_string('viewdata', 'local_extcsv'));
$heading = get_string('viewdata', 'local_extcsv') . ': ' . $source->get('name');
$shortname = $source->get('shortname') ?? '';
if ($shortname) {
    $heading .= ' (' . html_writer::tag('code', $shortname) . ')';
}
$PAGE->set_heading($heading);
$PAGE->set_pagelayout('admin');

// Breadcrumb
$PAGE->navbar->add(get_string('sources', 'local_extcsv'), new moodle_url('/local/extcsv/index.php'));
$PAGE->navbar->add(get_string('viewdata', 'local_extcsv'));

// Pagination
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

// Get column configuration using source model
$columnsconfig = $source->getColumnsConfig();

// Build list of fields to select and headers based on column mapping
$fields = ['id', 'sourceid', 'rownum'];
$headers = ['ID', get_string('row', 'local_extcsv')];
$fieldmapping = [];
$headerfieldmapping = [];
// Map field names to their types for formatting
$fieldtypemapping = [];

if ($columnsconfig && !empty($columnsconfig['columns'])) {
    foreach ($columnsconfig['columns'] as $colconfig) {
        $shortname = $colconfig['short_name'] ?? null;
        $type = $colconfig['type'] ?? 'text';
        $slot = $colconfig['slot'] ?? null;

        if ($shortname && $slot !== null) {
            $fieldname = data_manager::get_field_name($type, $slot);
            if ($fieldname && !in_array($fieldname, $fields)) {
                $fields[] = $fieldname;
                $fieldmapping[] = $fieldname;
                $headers[] = htmlspecialchars($shortname);
                $headerfieldmapping[] = $fieldname;
                // Store type for this field
                $fieldtypemapping[$fieldname] = $type;
            }
        }
    }
}

// Get data with pagination - only select needed fields to save memory
$total = data_manager::count_source_data($id);
$limitfrom = $page * $perpage;
$fieldslist = implode(',', $fields);
$data = data_manager::get_source_data($id, $limitfrom, $perpage, $fieldslist);

// Check if columns are configured
$hascolumnsconfig = !empty($columnsconfig) && !empty($columnsconfig['columns']);

// Output
echo $OUTPUT->header();

// Handle update action
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'update' && confirm_sesskey()) {
    $result = source_manager::update_source_manual($id);
    if ($result['success']) {
        redirect($PAGE->url, $result['message'], null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($PAGE->url, $result['message'], null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Show source info
$sourceinfo = [];
$sourceinfo[] = html_writer::tag('strong', get_string('name', 'local_extcsv') . ': ') . $source->get('name');
$shortname = $source->get('shortname') ?? '';
if ($shortname) {
    $sourceinfo[] = html_writer::tag('strong', get_string('shortname', 'local_extcsv') . ': ') . html_writer::tag('code', $shortname);
}
$sourceinfo[] = html_writer::tag('strong', get_string('status', 'local_extcsv') . ': ') . get_string('status_' . $source->get('status'), 'local_extcsv');
echo html_writer::div(implode(' | ', $sourceinfo), 'mb-3');

// Show warning if columns not configured
if (!$hascolumnsconfig) {
    echo html_writer::div(
        html_writer::div(
            get_string('nocolumnsmapping', 'local_extcsv'),
            'alert alert-warning mb-3'
        ) . html_writer::link(
            new moodle_url('/local/extcsv/preview.php', ['id' => $id]),
            get_string('configurecolumnsfirst', 'local_extcsv'),
            ['class' => 'btn btn-warning']
        ),
        'mb-3'
    );
}

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
    ) . ' ' .
    html_writer::link(
        new moodle_url('/local/extcsv/preview.php', ['id' => $id]),
        get_string('configurecolumnsfirst', 'local_extcsv'),
        ['class' => 'btn btn-info']
    ) . ' ' .
    ($hascolumnsconfig ? html_writer::link(
        new moodle_url($PAGE->url, ['action' => 'update', 'sesskey' => sesskey()]),
        get_string('updatenow', 'local_extcsv'),
        ['class' => 'btn btn-success']
    ) : ''),
    'mb-3'
);

if ($total == 0) {
    echo html_writer::div(get_string('nodata', 'local_extcsv'), 'alert alert-info');
} else {
    echo html_writer::div(get_string('totalrows', 'local_extcsv', $total), 'mb-2');

    // Build table
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';

    // Headers and fieldmapping were already built above
    if (empty($headerfieldmapping)) {
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
            foreach ($headerfieldmapping as $fieldname) {
                $value = $record->$fieldname ?? '';
                $fieldtype = $fieldtypemapping[$fieldname] ?? 'text';
                
                // Format date fields
                if ($fieldtype === 'date' && !empty($value) && is_numeric($value)) {
                    // Format date as DD.MM.YYYY (Russian notation)
                    $value = userdate($value, '%d.%m.%Y');
                } else {
                    // Limit text length to avoid memory issues
                    if (is_string($value) && strlen($value) > 200) {
                        $value = substr($value, 0, 200) . '...';
                    }
                    if (is_numeric($value) && strlen((string)$value) > 0) {
                        // Keep numeric value as is (for int, float, bool)
                        $row[] = $value;
                        continue;
                    } else if (empty($value)) {
                        $row[] = '-';
                        continue;
                    } else {
                        $value = htmlspecialchars($value);
                    }
                }
                $row[] = $value;
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

