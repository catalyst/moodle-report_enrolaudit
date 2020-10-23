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
 * Table definition for the enrol audit report.
 *
 * @package    report_enrolaudit
 * @copyright  2020 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_enrolaudit\output;

defined('MOODLE_INTERNAL') || die;

use report_enrolaudit\enrolaudit;
use table_sql;

require_once("$CFG->libdir/tablelib.php");

/**
 * Class that manages how data is displayed in the enrol audit report.
 *
 * @package    report_enrolaudit
 * @copyright  2020 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_table extends table_sql {
    /**
     * Format the timemodified cell.
     *
     * @param   \stdClass $row
     * @return  string
     */
    public function col_timemodified($row) {
        return userdate($row->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
    }

    /**
     * Format the change cell to show what the update was.
     *
     * @param   \stdClass $row
     * @return  string
     */
    public function col_change($row) {
        switch ($row->change) {
            case enrolaudit::ENROLMENT_DELETED:
                return get_string('enrolmentdeleted', 'report_enrolaudit');
            case enrolaudit::ENROLMENT_CREATED:
                return get_string('enrolmentcreated', 'report_enrolaudit');
            case enrolaudit::ENROLMENT_STATUS_SUSPENDED:
                return get_string('enrolmentsuspended', 'report_enrolaudit');
            case enrolaudit::ENROLMENT_STATUS_ACTIVE:
                return get_string('enrolmentactive', 'report_enrolaudit');
            default:
                return '';
        }
    }

    /**
     * Format the modifierid cell. This is the time the update was made.
     *
     * @param   \stdClass $row
     * @return  string
     */
    public function col_modifierid($row) {
        global $DB;
        return fullname($DB->get_record('user', ['id' => $row->modifierid]));
    }

    /**
     * Format the coursename cell. Generates a link to filter by course.
     *
     * @param   \stdClass $row
     * @return  string
     */
    public function col_coursename($row) {
        return \html_writer::link(
            new \moodle_url('/report/enrolaudit/index.php', ['id' => $row->courseid]),
            $row->coursename
        );
    }
}
