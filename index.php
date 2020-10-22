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
 * Enrolment audit report.
 *
 * @package    report_enrolaudit
 * @copyright  2020 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/lib/tablelib.php');

global $DB, $PAGE, $OUTPUT;

// Page parameters.
$id       = optional_param('id', 0, PARAM_INT);// Course ID.
$userid   = optional_param('userid', 0, PARAM_INT);// Course ID.
$user     = optional_param('user', 0, PARAM_INT); // User to display.
$perpage  = optional_param('perpage', 30, PARAM_INT);    // how many per page
$download = optional_param('download', '', PARAM_ALPHA);

$course = null;
if (!empty($id)) {
    $params['id'] = $id;
    $course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
    $context = context_course::instance($course->id);
} else {
    $context = context_system::instance();
}

require_capability('report/enrolaudit:view', $context);

$heading = get_string('enrolaudit', 'report_enrolaudit');
$url = new moodle_url('/report/enrolaudit/index.php', ['id' => $id, 'userid' => $userid]);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title($heading);
$output = $PAGE->get_renderer('report_enrolaudit');

$enrolaudit = new report_enrolaudit\enrolaudit($course, $context, $userid, $url);
$table = new report_enrolaudit\output\report_table('enrolaudit');
$table->is_downloading($download, $enrolaudit->get_filename(), $heading);

// Don't output markup if we are downloading.
if (!$table->is_downloading()) {
    echo $output->header();
    echo $output->heading($heading);
    $output->print_course_selector($enrolaudit);
    $output->print_user_selector($enrolaudit);
}

$params['initialrecord'] = report_enrolaudit\enrolaudit::ENROLMENT_INITIAL;

$fields = "
    re.id,
    firstname,
    lastname,
    c.fullname AS coursename,
    re.change,
    ue.modifierid,
    re.timemodified
";
$from = "{report_enrolaudit} re 
    JOIN {user_enrolments} ue ON ue.id = re.userenrolmentid
    JOIN {user} u ON u.id = ue.userid
    JOIN {course} c ON c.id = re.courseid
";
$where = "re.change != :initialrecord";

if ($id) {
    $where .= " AND c.id = :courseid";
    $params['courseid'] = $id;
}

if ($userid) {
    $where .= " AND u.id = :userid";
    $params['userid'] = $userid;
}

$table->set_sql($fields, $from, $where, $params);

$table->define_columns([
    'firstname',
    'lastname',
    'coursename',
    'change',
    'modifierid',
    'timemodified'
]);

$table->define_headers([
    get_string('firstname'),
    get_string('lastname'),
    get_string('course'),
    get_string('change', 'report_enrolaudit'),
    get_string('changedby', 'report_enrolaudit'),
    get_string('timemodified', 'report_enrolaudit'),
]);

$table->define_baseurl($url);
$table->build_table();
$table->close_recordset();
$table->out($perpage, true);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
