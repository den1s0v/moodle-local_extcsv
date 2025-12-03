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
 * List of CSV sources
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_extcsv\source_manager;
use local_extcsv\data_manager;
use local_extcsv\csv_importer;
use local_extcsv\model\source_model;

// Check permissions
source_manager::require_manage_capability();

// Page setup
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/extcsv/index.php'));
$PAGE->set_title(get_string('sources', 'local_extcsv'));
$PAGE->set_heading(get_string('sources', 'local_extcsv'));
$PAGE->set_pagelayout('admin');

// Handle actions
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

if ($action === 'delete' && $id) {
    if ($confirm && confirm_sesskey()) {
        if (source_manager::delete_source($id)) {
            redirect($PAGE->url, get_string('sourcedeleted', 'local_extcsv'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($PAGE->url, get_string('sourcenotfound', 'local_extcsv'), null, \core\output\notification::NOTIFY_ERROR);
        }
    } else {
        // Load source using source_manager
        $source = source_manager::get_source($id);
        if ($source) {
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(
                get_string('confirmdelete', 'local_extcsv', $source->get('name')),
                new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $id, 'confirm' => 1]),
                $PAGE->url
            );
            echo $OUTPUT->footer();
            exit;
        }
    }
}

if ($action === 'update' && $id && confirm_sesskey()) {
    $result = source_manager::update_source_manual($id);
    if ($result['success']) {
        redirect($PAGE->url, $result['message'], null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($PAGE->url, $result['message'], null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Get all sources using source_manager
$sources = source_manager::get_all_sources();

// Output
echo $OUTPUT->header();

// Add button
echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/extcsv/edit.php'),
        get_string('addsource', 'local_extcsv'),
        ['class' => 'btn btn-primary']
    ),
    'mb-3'
);

// Table
$table = new html_table();
$table->head = [
    get_string('name', 'local_extcsv'),
    get_string('shortname', 'local_extcsv'),
    get_string('status', 'local_extcsv'),
    get_string('content_type', 'local_extcsv'),
    get_string('lastupdate', 'local_extcsv'),
    get_string('lastupdatestatus', 'local_extcsv'),
    get_string('actions', 'core'),
];
$table->attributes['class'] = 'generaltable';

foreach ($sources as $source) {
    $sourceid = $source->getId();
    $rowcount = data_manager::count_source_data($sourceid);
    
    // Check if columns are configured
    $columnsconfig = $source->getColumnsConfig();
    $hascolumnsconfig = !empty($columnsconfig) && !empty($columnsconfig['columns']);

    $status = $source->get('status');
    $statusclass = '';
    switch ($status) {
        case source_model::STATUS_ENABLED:
            $statusclass = 'badge badge-success';
            break;
        case source_model::STATUS_DISABLED:
            $statusclass = 'badge badge-secondary';
            break;
        case source_model::STATUS_FROZEN:
            $statusclass = 'badge badge-warning';
            break;
    }

    $lastupdate = $source->get('lastupdate');
    $lastupdatestr = $lastupdate ? userdate($lastupdate) : '-';

    $lastupdatestatus = $source->get('lastupdatestatus');
    $statusbadge = '';
    if ($lastupdatestatus) {
        $badgeclass = $lastupdatestatus === source_model::UPDATE_STATUS_SUCCESS ? 'badge-success' : 'badge-danger';
        $statusbadge = html_writer::span(
            get_string("status_{$lastupdatestatus}", 'local_extcsv'),
            "badge {$badgeclass}"
        );
    }
    
    // Add warning badge if columns not configured
    if (!$hascolumnsconfig) {
        $statusbadge .= ' ' . html_writer::span(
            get_string('configurecolumnsfirst', 'local_extcsv'),
            'badge badge-warning'
        );
    }

    $actions = [];
    $actions[] = html_writer::link(
        new moodle_url('/local/extcsv/edit.php', ['id' => $sourceid]),
        get_string('edit'),
        ['class' => 'btn btn-sm btn-secondary']
    );
    $actions[] = html_writer::link(
        new moodle_url('/local/extcsv/preview.php', ['id' => $sourceid]),
        get_string('preview', 'local_extcsv'),
        ['class' => 'btn btn-sm btn-info']
    );
    $actions[] = html_writer::link(
        new moodle_url('/local/extcsv/view.php', ['id' => $sourceid]),
        get_string('viewdata', 'local_extcsv'),
        ['class' => 'btn btn-sm btn-primary']
    );
    $actions[] = html_writer::link(
        new moodle_url($PAGE->url, ['action' => 'update', 'id' => $sourceid, 'sesskey' => sesskey()]),
        get_string('updatenow', 'local_extcsv'),
        ['class' => 'btn btn-sm btn-success']
    );
    $actions[] = html_writer::link(
        new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $sourceid, 'sesskey' => sesskey()]),
        get_string('delete'),
        ['class' => 'btn btn-sm btn-danger']
    );

    $shortname = $source->get('shortname') ?? '';
    $shortnamedisplay = $shortname ? html_writer::tag('code', $shortname) : html_writer::span('-', 'text-muted');
    
    $table->data[] = [
        html_writer::link(
            new moodle_url('/local/extcsv/edit.php', ['id' => $sourceid]),
            $source->get('name')
        ) . ' (' . $rowcount . ' ' . get_string('rows', 'local_extcsv') . ')',
        $shortnamedisplay,
        html_writer::span(get_string("status_{$status}", 'local_extcsv'), $statusclass),
        get_string("content_type_{$source->get('content_type')}", 'local_extcsv'),
        $lastupdatestr,
        $statusbadge,
        implode(' ', $actions),
    ];
}

if (empty($table->data)) {
    echo html_writer::div(get_string('nosources', 'local_extcsv'), 'alert alert-info');
} else {
    echo html_writer::table($table);
}

echo $OUTPUT->footer();

