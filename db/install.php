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
 * Post install code.
 *
 * @package    report_enrolaudit
 * @copyright  2020 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_report_enrolaudit_install() {
    global $DB;

    // Populate the log table with current status.
    // This is needed as a point of comparison when there's a new change.
    // The initial record gets stored with a change status that won't appear in the report.
    $rs = $DB->get_recordset('user_enrolments', null, '', 'id, enrolid, status, timemodified');

    foreach ($rs as $record) {
        $courseid = $DB->get_field('enrol', 'courseid', ['id' => $record->enrolid]);

        $log = (object)[
          'userenrolmentid' => $record->id,
          'courseid' => $courseid,
          'change' => \report_enrolaudit\enrolaudit::ENROLMENT_INITIAL,
          'status' => $record->status,
          'timemodified' => $record->timemodified,
        ];

        $DB->insert_record('report_enrolaudit', $log);
    }

    $rs->close();
}

