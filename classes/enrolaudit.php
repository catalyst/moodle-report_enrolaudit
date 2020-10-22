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

namespace report_enrolaudit;

use context;

defined('MOODLE_INTERNAL') || die;

class enrolaudit {

    const ENROLMENT_DELETED = -1;
    const ENROLMENT_INITIAL = 0;
    const ENROLMENT_CREATED = 1;
    const ENROLMENT_STATUS_SUSPENDED = 3;
    const ENROLMENT_STATUS_ACTIVE = 4;

    /** @var context context of the report */
    protected $context;

    /** @var int course id to filter results by */
    protected $courseid = 0;

    /** @var int user id to filter results by */
    protected $userid = 0;

    /** @var \moodle_url baseurl of the report */
    protected $baseurl;

    public function __construct($course, $context, $userid, $baseurl) {
        $this->courseid = $course ? $course->id : 0;
        $this->context = $context;
        $this->baseurl = $baseurl;
        $this->userid = $userid;
    }

    public function get_baseurl() {
        return $this->baseurl;
    }

    public function get_context() {
        return $this->context;
    }

    public function get_courseid() {
        return $this->courseid;
    }

    public function get_userid() {
        return $this->userid;
    }

    public function get_filename() {
        return 'enrolaudit_' . userdate(time(), get_string('backupnameformat', 'langconfig'), 99, false);
    }

    static function get_current_status($userenrolmentid) {
        global $DB;

        return $DB->get_field('user_enrolments', 'status', ['id' => $userenrolmentid]);;
    }

    static function get_previous_status($userenrolmentid) {
        global $DB;

        $records = $DB->get_records(
            'report_enrolaudit',
            ['userenrolmentid' => $userenrolmentid],
            'timemodified DESC',
            'status'
        );

        $previousrecord = array_shift($records);

        return $previousrecord->status;
    }

    static function status_has_changed($userenrolmentid) {

        $currentstatus = self::get_current_status($userenrolmentid);
        $previousstatus = self::get_previous_status($userenrolmentid);

        return $currentstatus != $previousstatus;
    }

}