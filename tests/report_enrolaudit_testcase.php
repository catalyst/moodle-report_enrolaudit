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

defined('MOODLE_INTERNAL') || die();

class report_enrolaudit_testcase extends advanced_testcase {
    public function get_test_values() {
        global $DB;
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        $users[] = $this->getDataGenerator()->create_user();
        $users[] = $this->getDataGenerator()->create_user();
        $users[] = $this->getDataGenerator()->create_user();

        $category = $this->getDataGenerator()->create_category();

        $course = $this->getDataGenerator()->create_course(array(
            'shortname' => 'course1',
            'category' => $category->id,
        ));

        $enrolinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $manual = enrol_get_plugin('manual');

        return [
            $users,
            $course,
            $enrolinstance,
            $manual,
            $studentrole
        ];
    }
}
