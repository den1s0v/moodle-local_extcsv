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
 * Column mapping form
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace local_extcsv\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use moodleform;

/**
 * Form for mapping CSV columns to database fields
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
class column_mapping_form extends moodleform {

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;
        
        $headers = $this->_customdata['headers'] ?? [];
        $existingconfig = $this->_customdata['existing_config'] ?? null;
        
        // Hidden field for source ID
        $mform->addElement('hidden', 'sourceid');
        $mform->setType('sourceid', PARAM_INT);
        
        if (empty($headers)) {
            $mform->addElement('static', 'noheaders', '', get_string('nocolumns', 'local_extcsv'));
            return;
        }
        
        // Add field limits info
        $maxslots = [
            \local_extcsv\data_manager::TYPE_TEXT => \local_extcsv\data_manager::MAX_TEXT,
            \local_extcsv\data_manager::TYPE_INT => \local_extcsv\data_manager::MAX_INT,
            \local_extcsv\data_manager::TYPE_FLOAT => \local_extcsv\data_manager::MAX_FLOAT,
            \local_extcsv\data_manager::TYPE_BOOL => \local_extcsv\data_manager::MAX_BOOL,
            \local_extcsv\data_manager::TYPE_DATE => \local_extcsv\data_manager::MAX_DATE,
            \local_extcsv\data_manager::TYPE_JSON => \local_extcsv\data_manager::MAX_JSON,
        ];
        
        $limitshtml = '<div class="alert alert-info"><strong>' . get_string('fieldlimits', 'local_extcsv') . ':</strong><ul>';
        foreach ($maxslots as $type => $max) {
            $limitshtml .= '<li>' . get_string("type_{$type}", 'local_extcsv') . ': ' . $max . '</li>';
        }
        $limitshtml .= '</ul></div>';
        $mform->addElement('html', $limitshtml);
        
        // Build existing mapping for easier editing
        $existingmap = [];
        if ($existingconfig && !empty($existingconfig['columns'])) {
            foreach ($existingconfig['columns'] as $colconfig) {
                $pattern = $colconfig['pattern'] ?? '';
                $existingmap[$pattern] = $colconfig;
            }
        }
        
        // Add fieldset for columns
        $mform->addElement('header', 'columnsheader', get_string('selectcolumns', 'local_extcsv'));
        
        $typeoptions = [
            \local_extcsv\data_manager::TYPE_TEXT => get_string('type_text', 'local_extcsv'),
            \local_extcsv\data_manager::TYPE_INT => get_string('type_int', 'local_extcsv'),
            \local_extcsv\data_manager::TYPE_FLOAT => get_string('type_float', 'local_extcsv'),
            \local_extcsv\data_manager::TYPE_BOOL => get_string('type_bool', 'local_extcsv'),
            \local_extcsv\data_manager::TYPE_DATE => get_string('type_date', 'local_extcsv'),
            \local_extcsv\data_manager::TYPE_JSON => get_string('type_json', 'local_extcsv'),
        ];
        
        // Wrap table in a div for better control
        $mform->addElement('html', '<div class="column-mapping-table-wrapper">');
        
        // Create table header with column number
        $mform->addElement('html', '<table class="generaltable"><thead><tr>');
        $mform->addElement('html', '<th>' . get_string('column_number', 'local_extcsv') . '</th>');
        $mform->addElement('html', '<th>' . get_string('column_name', 'local_extcsv') . '</th>');
        $mform->addElement('html', '<th>' . get_string('select', 'core') . '</th>');
        $mform->addElement('html', '<th>' . get_string('columntype', 'local_extcsv') . '</th>');
        $mform->addElement('html', '<th>' . get_string('shortname', 'local_extcsv') . '</th>');
        $mform->addElement('html', '</tr></thead><tbody>');
        
        // Create form elements for each column
        foreach ($headers as $index => $headername) {
            // Hidden field for column name
            $mform->addElement('hidden', "column_name_{$index}", $headername);
            $mform->setType("column_name_{$index}", PARAM_TEXT);
            
            $existingtype = $existingmap[$headername]['type'] ?? \local_extcsv\data_manager::TYPE_TEXT;
            $existingshortname = $existingmap[$headername]['short_name'] ?? '';
            $ischecked = isset($existingmap[$headername]) ? 1 : 0;
            
            $checkboxid = "selected_{$index}";
            $typeid = "column_type_{$index}";
            $nameid = "short_name_{$index}";
            
            // Start row
            $mform->addElement('html', '<tr>');
            
            // Column number cell
            $mform->addElement('html', '<td style="text-align: center;">' . ($index + 1) . '</td>');
            
            // Column name cell
            $mform->addElement('html', '<td>' . htmlspecialchars($headername) . '</td>');
            
            // Checkbox cell
            $mform->addElement('html', '<td style="text-align: center;">');
            $mform->addElement('checkbox', $checkboxid, '');
            $mform->setDefault($checkboxid, $ischecked);
            $mform->addElement('html', '</td>');
            
            // Column type cell
            $mform->addElement('html', '<td>');
            $mform->addElement('select', $typeid, '', $typeoptions);
            $mform->setDefault($typeid, $existingtype);
            $mform->disabledIf($typeid, $checkboxid, 'notchecked');
            $mform->addElement('html', '</td>');
            
            // Short name cell
            $mform->addElement('html', '<td>');
            $mform->addElement('text', $nameid, '', ['size' => 20]);
            $mform->setType($nameid, PARAM_TEXT);
            $mform->setDefault($nameid, $existingshortname ?: $headername);
            $mform->disabledIf($nameid, $checkboxid, 'notchecked');
            $mform->addElement('html', '</td>');
            
            // End row
            $mform->addElement('html', '</tr>');
        }
        
        $mform->addElement('html', '</tbody></table></div>');
        
        // Add CSS and JavaScript to properly position form elements in table cells
        $mform->addElement('html', '<style>
        .column-mapping-table-wrapper { position: relative; }
        .column-mapping-table-wrapper .fitem {
            margin: 0;
            border: none;
            background: transparent;
            padding: 0;
        }
        .column-mapping-table-wrapper .fitemtitle {
            display: none;
        }
        .column-mapping-table-wrapper .felement {
            margin: 0;
            padding: 0;
        }
        .column-mapping-table-wrapper table.generaltable td {
            vertical-align: middle;
        }
        </style>');
        
        $mform->addElement('html', '<script>
        require(["jquery"], function($) {
            function fixTableLayout() {
                var $table = $(".column-mapping-table-wrapper table.generaltable").first();
                if (!$table.length) return;
                
                // Find and move each form element to its corresponding table cell
                $table.find("tbody tr").each(function() {
                    var $row = $(this);
                    var rowIndex = $row.index();
                    
                    // Find checkbox - look for the input element (column 3: index 2)
                    var checkboxName = "selected_" + rowIndex;
                    var $checkboxInput = $("input[name=\"" + checkboxName + "\"]");
                    if ($checkboxInput.length) {
                        var $checkboxItem = $checkboxInput.closest(".fitem");
                        if ($checkboxItem.length && !$row.find("td").eq(2).find($checkboxInput).length) {
                            var $checkboxCell = $row.find("td").eq(2);
                            $checkboxCell.html("");
                            $checkboxCell.append($checkboxItem);
                        }
                    }
                    
                    // Find select dropdown (column 4: index 3)
                    var selectName = "column_type_" + rowIndex;
                    var $selectInput = $("select[name=\"" + selectName + "\"]");
                    if ($selectInput.length) {
                        var $selectItem = $selectInput.closest(".fitem");
                        if ($selectItem.length && !$row.find("td").eq(3).find($selectInput).length) {
                            var $typeCell = $row.find("td").eq(3);
                            $typeCell.html("");
                            $typeCell.append($selectItem);
                        }
                    }
                    
                    // Find text input (column 5: index 4)
                    var textName = "short_name_" + rowIndex;
                    var $textInput = $("input[name=\"" + textName + "\"]");
                    if ($textInput.length) {
                        var $textItem = $textInput.closest(".fitem");
                        if ($textItem.length && !$row.find("td").last().find($textInput).length) {
                            var $nameCell = $row.find("td").last();
                            $nameCell.html("");
                            $nameCell.append($textItem);
                        }
                    }
                });
            }
            
            $(document).ready(fixTableLayout);
            setTimeout(fixTableLayout, 50);
            setTimeout(fixTableLayout, 200);
            setTimeout(fixTableLayout, 500);
        });
        </script>');
        
        // Wrap table in a div for better control
        $mform->addElement('html', '<div class="column-mapping-table-wrapper">');
        
        // Buttons
        $this->add_action_buttons(true, get_string('save', 'core'));
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
        
        $headers = $this->_customdata['headers'] ?? [];
        $selectedcolumns = [];
        
        // Collect selected columns
        foreach ($headers as $index => $headername) {
            $selectedkey = "selected_{$index}";
            if (!empty($data[$selectedkey])) {
                $type = $data["column_type_{$index}"] ?? \local_extcsv\data_manager::TYPE_TEXT;
                $shortname = trim($data["short_name_{$index}"] ?? '');
                
                if (empty($shortname)) {
                    $errors["short_name_{$index}"] = get_string('required');
                } else {
                    $selectedcolumns[] = [
                        'column_name' => $headername,
                        'type' => $type,
                        'short_name' => $shortname,
                        'pattern' => $headername,
                    ];
                }
            }
        }
        
        // Validate field limits
        if (!empty($selectedcolumns)) {
            try {
                \local_extcsv\data_manager::assign_slots_automatically($selectedcolumns);
            } catch (\moodle_exception $e) {
                $errors['columnsheader'] = $e->getMessage();
            }
        } else {
            $errors['columnsheader'] = get_string('noselectedcolumns', 'local_extcsv');
        }
        
        return $errors;
    }
    
    /**
     * Get processed data
     *
     * @return array|null
     */
    public function get_processed_data() {
        $data = $this->get_data();
        if (!$data) {
            return null;
        }
        
        $headers = $this->_customdata['headers'] ?? [];
        $selectedcolumns = [];
        
        foreach ($headers as $index => $headername) {
            $selectedkey = "selected_{$index}";
            if (!empty($data->$selectedkey)) {
                $typekey = "column_type_{$index}";
                $shortnamekey = "short_name_{$index}";
                
                $selectedcolumns[] = [
                    'column_name' => $headername,
                    'type' => $data->$typekey ?? \local_extcsv\data_manager::TYPE_TEXT,
                    'short_name' => trim($data->$shortnamekey ?? $headername),
                    'pattern' => $headername,
                ];
            }
        }
        
        if (empty($selectedcolumns)) {
            return null;
        }
        
        // Assign slots automatically
        return \local_extcsv\data_manager::assign_slots_automatically($selectedcolumns);
    }
}
