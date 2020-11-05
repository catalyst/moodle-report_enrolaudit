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
 * Ad hoc task definition for populating initial log values.
 *
 * @package    report_enrolaudit
 * @copyright  2020 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_enrolaudit\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/modlib.php');

/**
 * Ad hoc task definition for populating initial log values.
 *
 * @package    report_enrolaudit
 * @copyright  2020 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class populate_enrolaudit_log_table extends \core\task\adhoc_task {

    /**
     * Gets all the current settings for course modules and adds to the log table.
     *
     * It's done as a task as we can't use these methods during an upgrade.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function execute() {
        global $DB;

        // Populate the log table with current status.
        // This is needed as a point of comparison when there's a new change.
        // The initial record gets stored with a change status that won't appear in the report.

        $numrecords = $DB->count_records('report_enrolaudit');

        if (!$numrecords) {
            $this->insert_enrol_create_records();
            $this->insert_enrol_update_records();
            $this->insert_enrol_deleted_records();
        }
    }

    private function insert_enrol_create_records() {
        global $DB;

        $createdenrolmentsql = "SELECT objectid AS userenrolmentid, courseid, relateduserid AS enrolleduserid,
                                       userid AS modifierid, timecreated
                                  FROM {logstore_standard_log}
                                 WHERE eventname = :eventname1

                                UNION ALL

                                SELECT ue.id AS userenrolmentid, c.id AS courseid, ue.userid AS enrolleduserid,
                                       0 AS modifierid, ue.timecreated
                                  FROM {user_enrolments} ue
                                  JOIN {enrol} e ON ue.enrolid = e.id
                                  JOIN {course} c ON e.courseid = c.id
                                 WHERE ue.id NOT IN (
                                     SELECT objectid
                                       FROM {logstore_standard_log}
                                      WHERE eventname = :eventname2
                                 )";

        $rs = $DB->get_recordset_sql($createdenrolmentsql, [
            'eventname1' => '\core\event\user_enrolment_created',
            'eventname2' => '\core\event\user_enrolment_created',
        ]);

        foreach ($rs as $record) {
            $log = (object)[
                'userenrolmentid' => $record->userenrolmentid,
                'courseid' => $record->courseid,
                'userid' => $record->enrolleduserid,
                'modifierid' => $record->modifierid,
                'change' => \report_enrolaudit\enrolaudit::ENROLMENT_CREATED,
                'status' => ENROL_USER_ACTIVE,
                'timemodified' => $record->timecreated,
            ];

            $DB->insert_record('report_enrolaudit', $log);
        }

        $rs->close();
    }

    private function insert_enrol_update_records() {
        global $DB;

        $rs = $DB->get_recordset('report_enrolaudit');

        foreach ($rs as $record) {

            // Get the current enrolment record if it exists.
            $userenrolmentsql = "SELECT *
                                   FROM {user_enrolments}
                                  WHERE id = :userenrolmentid
                                    AND timemodified <> timecreated";
            $userenrolment = $DB->get_record_sql($userenrolmentsql, ['userenrolmentid' => $record->userenrolmentid]);

            // Get enrolment updates from the logs.
            $logstoresql = "SELECT id, objectid AS userenrolmentid, courseid, relateduserid AS enrolleduserid,
                                   userid AS modifierid, timecreated
                              FROM {logstore_standard_log}
                             WHERE eventname = :eventname
                               AND objectid = :userenrolmentid";
            $logstoreparams = [
                'eventname' => '\core\event\user_enrolment_updated',
                'userenrolmentid' => $record->userenrolmentid
            ];

            if ($userenrolment) {
                // Ignore the most recent log as that's stored in the user enrolment table already.
                $logstoresql .= " AND timecreated < :enrolmentupdatedtime";
                $logstoreparams['enrolmentupdatedtime'] = $userenrolment->timemodified;

                if ($userenrolment->status != ENROL_USER_ACTIVE) {
                    $change = \report_enrolaudit\enrolaudit::ENROLMENT_STATUS_SUSPENDED;
                } else {
                    $change = \report_enrolaudit\enrolaudit::ENROLMENT_UPDATED;
                }

                // Try to get the modifier id from the logstore if it exists.
                $logstoremodifieridsql = "SELECT userid
                                            FROM {logstore_standard_log}
                                           WHERE objectid = :userenrolmentid
                                             AND eventname = :eventname
                                             AND timecreated >= :enrolmentupdatedtime";

                $modifierid = $DB->get_field_sql($logstoremodifieridsql, $logstoreparams);

                $log = (object)[
                    'userenrolmentid' => $userenrolment->id,
                    'courseid' => $record->courseid,
                    'userid' => $record->userid,
                    'modifierid' => $modifierid ? $modifierid : 0,
                    'change' => $change,
                    'status' => $userenrolment->status,
                    'timemodified' => $userenrolment->timemodified,
                ];

                $DB->insert_record('report_enrolaudit', $log);
            }

            // Attempt to get historical data if it still exists in DB.
            $logstorerecords = $DB->get_records_sql($logstoresql, $logstoreparams);

            if (!$logstorerecords) {
                // No updates to user enrolment.
                continue;
            }

            foreach ($logstorerecords as $logstorerecord) {
                // Add records from the logstore table.
                // Note we can't know when the status was changed when the enrolment was updated.
                $log = (object)[
                    'userenrolmentid' => $record->userenrolmentid,
                    'courseid' => $record->courseid,
                    'userid' => $record->userid,
                    'modifierid' => $logstorerecord->modifierid,
                    'change' => \report_enrolaudit\enrolaudit::ENROLMENT_UPDATED,
                    'status' => $record->status,
                    'timemodified' => $logstorerecord->timecreated,
                ];

                $DB->insert_record('report_enrolaudit', $log);
            }

        }

        $rs->close();
    }

    private function insert_enrol_deleted_records() {
        global $DB;

        $deletedenrolmentsql = "SELECT objectid AS userenrolmentid, courseid, relateduserid AS enrolleduserid,
                                       userid AS modifierid, timecreated
                                  FROM {logstore_standard_log}
                                 WHERE eventname = :eventname";

        $rs = $DB->get_recordset_sql($deletedenrolmentsql, ['eventname' => '\core\event\user_enrolment_deleted']);

        foreach ($rs as $record) {
            $log = (object)[
                'userenrolmentid' => $record->userenrolmentid,
                'courseid' => $record->courseid,
                'userid' => $record->enrolleduserid,
                'modifierid' => $record->modifierid,
                'change' => \report_enrolaudit\enrolaudit::ENROLMENT_DELETED,
                'status' => ENROL_USER_ACTIVE,
                'timemodified' => $record->timecreated,
            ];

            $DB->insert_record('report_enrolaudit', $log);
        }

        $rs->close();
    }
}
