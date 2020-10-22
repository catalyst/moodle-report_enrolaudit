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

namespace report_enrolaudit\output;

use report_enrolaudit\enrolaudit;
use table_sql;

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/tablelib.php");

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
            case enrolaudit::ENROLMENT_DELETED: return 'User enrolment deleted';
            case enrolaudit::ENROLMENT_CREATED: return 'User enrolment created';
            case enrolaudit::ENROLMENT_STATUS_SUSPENDED: return 'User enrolment set to suspended';
            case enrolaudit::ENROLMENT_STATUS_ACTIVE: return 'User enrolment set to active';
            default: return '';
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
}