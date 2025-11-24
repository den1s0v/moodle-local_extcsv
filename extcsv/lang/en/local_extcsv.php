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
 * Language strings for local_extcsv
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'External CSV Import';
$string['privacy:metadata'] = 'The local_extcsv plugin imports data from external CSV/TSV sources.';

// Capabilities
$string['extcsv:manage_sources'] = 'Manage CSV data sources';

// Navigation and pages
$string['sources'] = 'Data sources';
$string['managesources'] = 'Manage CSV sources';

// Source fields
$string['name'] = 'Name';
$string['description'] = 'Description';
$string['url'] = 'Source URL';
$string['content_type'] = 'Content type';
$string['content_type_csv'] = 'CSV';
$string['content_type_tsv'] = 'TSV';
$string['status'] = 'Status';
$string['status_enabled'] = 'Enabled';
$string['status_disabled'] = 'Disabled';
$string['status_frozen'] = 'Frozen';
$string['schedule'] = 'Update schedule';
$string['schedule_interval'] = 'Update interval';
$string['schedule_cron'] = 'Cron expression';
$string['schedule_mode'] = 'Schedule mode';
$string['schedule_mode_simple'] = 'Simple (interval)';
$string['schedule_mode_advanced'] = 'Advanced (cron)';
$string['url_help'] = 'Source data URL. Can be a direct link to CSV/TSV file or a Google Sheets link. For Google Sheets, export URL will be automatically generated.';
$string['schedule_cron_help'] = 'Cron expression for automatic data updates. Format: minute hour day month dayofweek. Example: "0 2 * * *" - every day at 2:00. Leave empty for manual updates only.';

// Time intervals
$string['interval_minutes'] = 'minutes';
$string['interval_hours'] = 'hours';
$string['interval_days'] = 'days';
$string['every'] = 'Every';

// Actions
$string['addsource'] = 'Add source';
$string['editsource'] = 'Edit source';
$string['deletesource'] = 'Delete source';
$string['preview'] = 'Preview';
$string['viewdata'] = 'View data';
$string['update'] = 'Update';
$string['test'] = 'Test';

// Status and errors
$string['lastupdate'] = 'Last update';
$string['lastupdatestatus'] = 'Last update status';
$string['lastupdateerror'] = 'Last update error';
$string['status_success'] = 'Success';
$string['status_error'] = 'Error';
$string['status_pending'] = 'Pending';

// Columns configuration
$string['columns'] = 'Columns';
$string['column_pattern'] = 'External name pattern';
$string['column_shortname'] = 'Short internal name';
$string['column_type'] = 'Data type';
$string['column_type_text'] = 'Text';
$string['column_type_int'] = 'Integer';
$string['column_type_float'] = 'Float';
$string['column_type_bool'] = 'Boolean';
$string['column_type_date'] = 'Date';
$string['column_type_json'] = 'JSON';
$string['selectcolumns'] = 'Select columns';

// Google Sheets
$string['google_sheets_url'] = 'Google Sheets URL';
$string['google_sheets_id'] = 'Sheet ID';
$string['google_sheets_gid'] = 'Sheet GID';
$string['build_google_url'] = 'Build Google Sheets URL';

// Messages
$string['sourceadded'] = 'Source successfully added';
$string['sourceupdated'] = 'Source successfully updated';
$string['sourcedeleted'] = 'Source successfully deleted';
$string['sourceupdatedsuccess'] = 'Source data successfully updated';
$string['sourceupdateerror'] = 'Error updating source data: {$a}';
$string['nopermission'] = 'You do not have permission to manage data sources';
$string['confirmdelete'] = 'Are you sure you want to delete source "{$a}"? All associated data will also be deleted.';

// Task
$string['taskupdatesources'] = 'Update CSV sources';

// Errors
$string['downloaderror'] = 'Error downloading data: {$a}';
$string['downloadhttperror'] = 'HTTP error downloading: {$a}';
$string['downloadempty'] = 'Downloaded file is empty';
$string['invalidcsvheaders'] = 'Invalid CSV headers';
$string['nocolumnsmapped'] = 'No column mappings found';
$string['nofieldmapping'] = 'Field mapping not configured';
$string['unknownfield'] = 'Unknown field: {$a}';
$string['sourcenotfound'] = 'Source not found';
$string['nosources'] = 'No sources found';
$string['invalidinterval'] = 'Invalid interval';
$string['invalidurl'] = 'Invalid URL';
$string['column_number'] = 'Column number';
$string['column_name'] = 'Column name';
$string['nocolumns'] = 'No columns found';
$string['samplerows'] = 'Sample rows';
$string['row'] = 'Row';
$string['nodata'] = 'No data found';
$string['totalrows'] = 'Total rows: {$a}';
$string['rows'] = 'rows';
$string['updatenow'] = 'Update now';
$string['sourceupdatesuccess'] = 'Source updated successfully. Saved {$a} rows.';
$string['sourceupdateerror'] = 'Error updating source: {$a}';
$string['mapcolumns'] = 'Map columns';
$string['selectcolumns'] = 'Select columns to import';
$string['columntype'] = 'Column type';
$string['shortname'] = 'Short name';
$string['slotassigned'] = 'Slot assigned';
$string['fieldlimitreached'] = 'Field limit reached: {$a->type} (max: {$a->max}, selected: {$a->selected})';
$string['mappingsaved'] = 'Column mapping saved successfully';
$string['availableslots'] = 'Available slots: {$a->used}/{$a->max}';
$string['columnsnotconfigured'] = 'Column mapping is not configured. Please configure columns before importing data.';
$string['configurecolumnsfirst'] = 'Configure columns';
$string['nocolumnsmapping'] = 'No columns are mapped. Please go to preview page to configure column mapping.';
$string['columnsmappingrequired'] = 'Column mapping is required before importing data';
$string['fieldlimits'] = 'Field limits';
$string['type_text'] = 'Text';
$string['type_int'] = 'Integer';
$string['type_float'] = 'Float';
$string['type_bool'] = 'Boolean';
$string['type_date'] = 'Date';
$string['type_json'] = 'JSON';
$string['noselectedcolumns'] = 'Please select at least one column';

