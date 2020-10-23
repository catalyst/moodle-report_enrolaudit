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
$id       = optional_param('id', 0, PARAM_INT); // Course ID.
$userid   = optional_param('userid', 0, PARAM_INT); // User ID.
$perpage  = optional_param('perpage', 30, PARAM_INT); // How many results per page.
$download = optional_param('download', '', PARAM_ALPHA); // Report download option.

$params = [];
$course = null;
if (!empty($id)) {
    // Course level.
    $params['id'] = $id;
    $course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
    $context = context_course::instance($course->id);
} else {
    $context = context_system::instance();
}

// Filter by userid.
if (!empty($userid)) {
    $params['userid'] = $userid;
}

require_login();
require_capability('report/enrolaudit:view', $context);

$heading = get_string('enrolaudit', 'report_enrolaudit');
$url = new moodle_url('/report/enrolaudit/index.php', $params);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title($heading);
$PAGE->set_heading($heading);

$output = $PAGE->get_renderer('report_enrolaudit');

$enrolaudit = new report_enrolaudit\enrolaudit($course, $context, $userid, $url);

$table = new report_enrolaudit\output\report_table('enrolaudit');
$table->is_downloading($download, $enrolaudit->get_filename(), $heading);

// Don't output markup if we are downloading.
if (!$table->is_downloading()) {
    echo $output->header();
    echo $output->heading($heading);

    if (!$id) {
        // Site level filters.
        $mform = new \report_enrolaudit\form\filters(null, array('sitelevel' => !(bool)$id));
        $data = $mform->get_data();

        if ($data) {
            if ($data->firstname) {
                $enrolaudit->set_firstname($data->firstname);
            }
            if ($data->lastname) {
                $enrolaudit->set_lastname($data->lastname);
            }
            if ($data->coursename) {
                $enrolaudit->set_coursename($data->coursename);
            }
        }

        $mform->display();
    } else {
        // Course level filters.
        $output->print_user_selector($enrolaudit);
    }
}

// Set up the table with the data and display it.
$table->set_sql(
    $enrolaudit->get_fields_sql(),
    $enrolaudit->get_from_sql(),
    $enrolaudit->get_where_sql(),
    $enrolaudit->get_params()
);

$table->define_columns($enrolaudit->get_columns());
$table->define_headers($enrolaudit->get_headers());

$table->sortable(true, 'timemodified', SORT_DESC);
$table->define_baseurl($url);
$table->build_table();
$table->close_recordset();
$table->out($perpage, true);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
