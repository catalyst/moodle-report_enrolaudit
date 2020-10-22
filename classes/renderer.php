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
 * Event observer for report_enrolaudit.
 *
 * @package    report_enrolaudit
 * @copyright  2020 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for report_enrolaudit.
 *
 * @package    report_enrolaudit
 * @copyright  2020 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_enrolaudit_renderer extends plugin_renderer_base {

    /**
     * Output the course selector for the enrol audit report.
     *
     * @param \report_enrolaudit\enrolaudit $report
     */
    public function print_course_selector($report) {
        global $DB;

        $courses = $DB->get_records_menu('course', null, '', 'id, fullname');
        $courses = array_merge([0=> get_string('none')], $courses);

        $select = new single_select(new moodle_url($report->get_baseurl()), 'id', $courses, $report->get_courseid(), null);
        $select->set_label(get_string('course'));

        echo $this->output->render($select);
    }

    /**
     * Output the user selector for the enrol audit report.
     *
     * @param \report_enrolaudit\enrolaudit $report
     */
    public function print_user_selector($report) {
        global $DB;

        $sql = "
            SELECT DISTINCT 
                u.id,
                CONCAT(u.firstname, ' ', u.lastname) as fullname
            FROM {user_enrolments} ue
                JOIN {user} u ON ue.userid = u.id
                JOIN {report_enrolaudit} re ON re.userenrolmentid = ue.id
            WHERE re.change != :initialstatus
        ";
        $params = ['initialstatus' => report_enrolaudit\enrolaudit::ENROLMENT_INITIAL];

        if ($report->get_courseid()) {
            $sql .= " AND re.courseid = :courseid";
            $params['courseid'] = $report->get_courseid();
        }

        $sql .= " ORDER BY fullname";

        $users = $DB->get_records_sql_menu($sql, $params);
        $users = [0=> get_string('none')] + $users;

        $select = new single_select(new moodle_url($report->get_baseurl()), 'userid', $users, $report->get_userid(), null);
        $select->set_label(get_string('user'));
        echo $this->output->render($select);
    }
}