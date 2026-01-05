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

// Process filters from GET/POST
$filters = [];
$hasactivefilters = false;
if ($columnsconfig && !empty($columnsconfig['columns'])) {
    foreach ($columnsconfig['columns'] as $colconfig) {
        $shortname = $colconfig['short_name'] ?? null;
        $type = $colconfig['type'] ?? 'text';
        $slot = $colconfig['slot'] ?? null;

        if ($shortname && $slot !== null) {
            $fieldname = data_manager::get_field_name($type, $slot);
            if ($fieldname) {
                $operatorparam = optional_param("filter_{$fieldname}_operator", '', PARAM_ALPHA);
                $valueparam = optional_param("filter_{$fieldname}_value", '', PARAM_RAW);
                
                if (!empty($valueparam) && trim($valueparam) !== '') {
                    $filters[$fieldname] = [
                        'operator' => $operatorparam ?: '=',
                        'value' => $valueparam
                    ];
                    $hasactivefilters = true;
                }
            }
        }
    }
}

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
$limitfrom = $page * $perpage;
$fieldslist = implode(',', $fields);

// Use filtered methods if filters are active
if ($hasactivefilters) {
    $total = data_manager::count_source_data_filtered($id, $filters, $fieldtypemapping);
    $data = data_manager::get_source_data_filtered($id, $filters, $fieldtypemapping, $limitfrom, $perpage, $fieldslist);
} else {
    $total = data_manager::count_source_data($id);
    $data = data_manager::get_source_data($id, $limitfrom, $perpage, $fieldslist);
}

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

// Handle reset filters action
if ($action === 'resetfilters') {
    redirect(new moodle_url('/local/extcsv/view.php', ['id' => $id]));
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

// Build URL with filters for pagination (initialize even if no filters)
$filterurlparams = ['id' => $id];
if ($hasactivefilters) {
    foreach ($filters as $fieldname => $filter) {
        $filterurlparams["filter_{$fieldname}_operator"] = $filter['operator'];
        $filterurlparams["filter_{$fieldname}_value"] = $filter['value'];
    }
}

// Build filter form if columns are configured
if ($hascolumnsconfig) {
    $filterurl = new moodle_url('/local/extcsv/view.php', $filterurlparams);
    
    // Determine if spoiler should be expanded
    $spoilerid = 'filter-spoiler-' . uniqid();
    
    echo html_writer::start_div('mb-3');
    echo html_writer::start_tag('div', ['class' => 'card']);
    
    // Spoiler header
    $spoilerheaderid = $spoilerid . '-header';
    echo html_writer::start_tag('div', [
        'class' => 'card-header',
        'id' => $spoilerheaderid,
        'style' => 'cursor: pointer;',
        'data-toggle' => 'collapse',
        'data-target' => '#' . $spoilerid,
        'aria-expanded' => $hasactivefilters ? 'true' : 'false',
        'aria-controls' => $spoilerid
    ]);
    echo html_writer::tag('strong', (get_string('filters', 'local_extcsv') ?: 'Фильтры') . ' ');
    $iconid = $spoilerid . '-icon';
    echo html_writer::tag('span', $hasactivefilters ? '▼' : '▶', ['id' => $iconid]);
    echo html_writer::end_tag('div');
    
    // Spoiler content
    echo html_writer::start_tag('div', [
        'id' => $spoilerid,
        'class' => 'collapse' . ($hasactivefilters ? ' show' : '')
    ]);
    echo html_writer::start_tag('div', ['class' => 'card-body']);
    
    // Filter form
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => $PAGE->url->out(false)
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
    
    // Build filter table
    echo html_writer::start_tag('table', ['class' => 'generaltable']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
                echo html_writer::tag('th', get_string('field', 'local_extcsv') ?: 'Поле', ['style' => 'width: 30%;']);
                echo html_writer::tag('th', get_string('operator', 'local_extcsv') ?: 'Оператор', ['style' => 'width: 20%;']);
                echo html_writer::tag('th', get_string('value', 'local_extcsv') ?: 'Значение', ['style' => 'width: 50%;']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    // Add filter rows for each field
    foreach ($columnsconfig['columns'] as $colconfig) {
        $shortname = $colconfig['short_name'] ?? null;
        $type = $colconfig['type'] ?? 'text';
        $slot = $colconfig['slot'] ?? null;

        if ($shortname && $slot !== null) {
            $fieldname = data_manager::get_field_name($type, $slot);
            if ($fieldname) {
                $operators = data_manager::get_operators_for_type($type);
                $currentoperator = $filters[$fieldname]['operator'] ?? '=';
                $currentvalue = $filters[$fieldname]['value'] ?? '';
                
                echo html_writer::start_tag('tr');
                
                // Field name
                echo html_writer::start_tag('td');
                echo html_writer::tag('label', htmlspecialchars($shortname), [
                    'for' => "filter_{$fieldname}_value"
                ]);
                echo html_writer::end_tag('td');
                
                // Operator select
                echo html_writer::start_tag('td');
                $selectid = "filter_{$fieldname}_operator";
                $select = html_writer::start_tag('select', [
                    'name' => $selectid,
                    'id' => $selectid,
                    'class' => 'form-control'
                ]);
                foreach ($operators as $opvalue => $oplabel) {
                    $selected = ($currentoperator === $opvalue) ? 'selected' : '';
                    $select .= html_writer::tag('option', $oplabel, [
                        'value' => $opvalue,
                        'selected' => $selected
                    ]);
                }
                $select .= html_writer::end_tag('select');
                echo $select;
                echo html_writer::end_tag('td');
                
                // Value input
                echo html_writer::start_tag('td');
                $inputid = "filter_{$fieldname}_value";
                $inputtype = ($type === 'date') ? 'text' : 'text';
                $placeholder = ($type === 'date') ? 'ДД.ММ.ГГГГ' : '';
                echo html_writer::empty_tag('input', [
                    'type' => $inputtype,
                    'name' => $inputid,
                    'id' => $inputid,
                    'value' => htmlspecialchars($currentvalue),
                    'class' => 'form-control',
                    'placeholder' => $placeholder
                ]);
                echo html_writer::end_tag('td');
                
                echo html_writer::end_tag('tr');
            }
        }
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    
    // Form buttons
    echo html_writer::start_div('mt-2');
    echo html_writer::tag('button', get_string('applyfilters', 'local_extcsv') ?: 'Применить фильтры', [
        'type' => 'submit',
        'class' => 'btn btn-primary'
    ]);
    echo ' ';
    echo html_writer::link(
        new moodle_url('/local/extcsv/view.php', ['id' => $id, 'action' => 'resetfilters']),
        get_string('resetfilters', 'local_extcsv') ?: 'Сбросить фильтры',
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_div();
    
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('div'); // card-body
    echo html_writer::end_tag('div'); // collapse
    echo html_writer::end_tag('div'); // card
    echo html_writer::end_div(); // mb-3
    
    // Add JavaScript to update icon on toggle
    $PAGE->requires->js_init_code("
        (function() {
            var spoiler = document.getElementById('{$spoilerid}');
            var icon = document.getElementById('{$iconid}');
            if (spoiler && icon) {
                spoiler.addEventListener('show.bs.collapse', function() {
                    icon.textContent = '▼';
                });
                spoiler.addEventListener('hide.bs.collapse', function() {
                    icon.textContent = '▶';
                });
            }
        })();
    ");
    
    // Update page URL to include filters for pagination
    $PAGE->set_url(new moodle_url('/local/extcsv/view.php', $filterurlparams));
}

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
        // Preserve filters in pagination URL
        $paginationurl = $PAGE->url;
        if ($hasactivefilters) {
            $paginationurl = new moodle_url('/local/extcsv/view.php', $filterurlparams);
        }
        echo $OUTPUT->paging_bar($total, $page, $perpage, $paginationurl);
    }
}

echo $OUTPUT->footer();

