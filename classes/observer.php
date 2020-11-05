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
class report_enrolaudit_observer {

    /**
     * Triggered via user_enrolment_created event.
     *
     * @param \core\event\user_enrolment_created $event
     * @return bool true on success.
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event) {
        global $DB;

        $record = (object) [
            'userenrolmentid' => $event->objectid,
            'courseid' => $event->courseid,
            'userid' => $event->relateduserid,
            'modifierid' => $event->userid,
            'change' => report_enrolaudit\enrolaudit::ENROLMENT_CREATED,
            'status' => report_enrolaudit\enrolaudit::get_current_status($event->objectid),
            'timemodified' => $event->timecreated
        ];

        $DB->insert_record('report_enrolaudit', $record);

        return true;
    }

    /**
     * Triggered via user_enrolment_updated event.
     *
     * @param \core\event\user_enrolment_updated $event
     * @return bool true on success
     */
    public static function user_enrolment_updated(\core\event\user_enrolment_updated $event) {
        global $DB;

        $currentstatus = \report_enrolaudit\enrolaudit::get_current_status($event->objectid);

        if (report_enrolaudit\enrolaudit::status_has_changed($event->objectid)) {

            if ($currentstatus == ENROL_USER_SUSPENDED) {
                $change = report_enrolaudit\enrolaudit::ENROLMENT_STATUS_SUSPENDED;
            } else {
                $change = report_enrolaudit\enrolaudit::ENROLMENT_STATUS_ACTIVE;
            }

        } else {
            $change = report_enrolaudit\enrolaudit::ENROLMENT_UPDATED;
        }

        $record = (object)[
            'userenrolmentid' => $event->objectid,
            'courseid' => $event->courseid,
            'userid' => $event->relateduserid,
            'modifierid' => $event->userid,
            'change' => $change,
            'status' => $currentstatus,
            'timemodified' => $event->timecreated
        ];

        $DB->insert_record('report_enrolaudit', $record);

        return true;
    }

    /**
     * Triggered via user_enrolment_deleted event.
     *
     * @param \core\event\user_enrolment_deleted $event
     * @return bool true on success.
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        global $DB;

        $record = (object) [
            'userenrolmentid' => $event->objectid,
            'courseid' => $event->courseid,
            'userid' => $event->relateduserid,
            'modifierid' => $event->userid,
            'change' => report_enrolaudit\enrolaudit::ENROLMENT_DELETED,
            'status' => $event->other['userenrolment']['status'],
            'timemodified' => $event->timecreated
        ];

        $DB->insert_record('report_enrolaudit', $record);

        return true;
    }

}
