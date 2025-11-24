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
 * Source model alias for backward compatibility
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace local_extcsv;

defined('MOODLE_INTERNAL') || die();

use local_extcsv\model\source_model;

/**
 * Source class alias for backward compatibility
 *
 * This class extends source_model for backward compatibility with code
 * that uses the old 'source' class name. All constants and methods
 * are inherited from source_model.
 *
 * @package    local_extcsv
 * @copyright  2024
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

class source extends source_model {
    // All constants and methods are inherited from source_model
    // This class exists only for backward compatibility
}
