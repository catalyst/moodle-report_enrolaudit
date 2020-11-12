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
 * Renderer for enrol audit report.
 *
 * @package    report_enrolaudit
 * @copyright  2020 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_enrolaudit\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/filters/lib.php');

class filters extends \user_filtering {

    /**
     * Creates known user filter if present
     * @param string $fieldname
     * @param boolean $advanced
     * @return object filter
     */
    public function get_field($fieldname, $advanced) {
        global $DB;

        switch ($fieldname) {
            case 'realname':
                return new \user_filter_text('realname', get_string('fullnameuser'),
                    $advanced, $DB->sql_fullname('u.firstname', 'u.lastname'));
            case 'lastname':
                return new \user_filter_text('lastname', get_string('lastname'), $advanced, 'u.lastname');
            case 'firstname':
                return new \user_filter_text('firstname', get_string('firstname'), $advanced, 'u.firstname');
            case 'coursename':
                return new \user_filter_text('coursename', get_string('course'), $advanced, 'c.fullname');
            default:
                return null;
        }
    }

}
