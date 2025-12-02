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
 * Database upgrade script for local_extcsv
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute local_extcsv upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_extcsv_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024030401) {
        // Rename fields in local_extcsv_data table to avoid MySQL reserved word conflicts
        // Change format from typeN (e.g., int1, text1) to type_N (e.g., int_1, text_1)
        
        $table = new xmldb_table('local_extcsv_data');
        
        // Define fields to rename: [old_name => new_name]
        $fieldstorename = [];
        
        // Text fields: text1-20 -> text_1 to text_20
        for ($i = 1; $i <= 20; $i++) {
            $fieldstorename["text{$i}"] = "text_{$i}";
        }
        
        // Int fields: int1-20 -> int_1 to int_20
        for ($i = 1; $i <= 20; $i++) {
            $fieldstorename["int{$i}"] = "int_{$i}";
        }
        
        // Float fields: float1-5 -> float_1 to float_5
        for ($i = 1; $i <= 5; $i++) {
            $fieldstorename["float{$i}"] = "float_{$i}";
        }
        
        // Bool fields: bool1-5 -> bool_1 to bool_5
        for ($i = 1; $i <= 5; $i++) {
            $fieldstorename["bool{$i}"] = "bool_{$i}";
        }
        
        // Date fields: date1-10 -> date_1 to date_10
        for ($i = 1; $i <= 10; $i++) {
            $fieldstorename["date{$i}"] = "date_{$i}";
        }
        
        // JSON fields: json1-3 -> json_1 to json_3
        for ($i = 1; $i <= 3; $i++) {
            $fieldstorename["json{$i}"] = "json_{$i}";
        }
        
        // Rename each field
        foreach ($fieldstorename as $oldname => $newname) {
            // Check if old field exists and new field doesn't exist before renaming
            if ($dbman->field_exists($table, $oldname) && !$dbman->field_exists($table, $newname)) {
                // Create field object with old name for identification
                $oldfield = new xmldb_field($oldname);
                
                // Rename the field - Moodle will preserve the field structure
                $dbman->rename_field($table, $oldfield, $newname);
            }
        }
        
        // Upgrade savepoint reached.
        upgrade_plugin_savepoint(true, 2024030401, 'local', 'extcsv');
    }

    if ($oldversion < 2025120200) {
        // Add shortname field to local_extcsv_sources for integrations (lookup by short name).
        $table = new xmldb_table('local_extcsv_sources');
        $field = new xmldb_field('shortname', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'name');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add unique index on shortname to avoid duplicates (optional shortname can be NULL).
        $index = new xmldb_index('shortname_uix', XMLDB_INDEX_UNIQUE, ['shortname']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Extcsv savepoint.
        upgrade_plugin_savepoint(true, 2025120200, 'local', 'extcsv');
    }

    return true;
}

