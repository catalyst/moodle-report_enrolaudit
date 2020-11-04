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

    public function test_import_enrolment_created_records() {
        global $DB;

        $this->resetAfterTest();

        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        $users[] = $this->getDataGenerator()->create_user();
        $users[] = $this->getDataGenerator()->create_user();
        $users[] = $this->getDataGenerator()->create_user();

        $category = $this->getDataGenerator()->create_category();

        $course = $this->getDataGenerator()->create_course(array(
            'shortname' => 'course1',
            'category' => $category->id,
        ));

        $maninstance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'manual'), '*', MUST_EXIST);

        $manual = enrol_get_plugin('manual');

        foreach ($users as $user) {
            $manual->enrol_user($maninstance, $user->id, $studentrole->id);
        }

        // Simulate fresh install and run adhoc task.
        $DB->delete_records('report_enrolaudit');
        $adhoctask = new \report_enrolaudit\task\populate_enrolaudit_log_table();
        $adhoctask->execute();

        foreach ($users as $user) {
            $enrolaudit = $DB->get_record('report_enrolaudit', ['userid' => $user->id]);
            $userenrolment = $DB->get_record('user_enrolments', ['userid' => $user->id]);

            $this->assertNotFalse($enrolaudit);
            $this->assertEquals(\report_enrolaudit\enrolaudit::ENROLMENT_CREATED, $enrolaudit->change);
            $this->assertEquals($course->id, $enrolaudit->courseid);
            $this->assertEquals($userenrolment->id, $enrolaudit->userenrolmentid);
        }
    }

    public function test_import_enrolment_updated_records() {
        global $DB;

        $this->resetAfterTest();

        $studentrole = $DB->get_record('role', array('shortname'=>'student'));

        $users[] = $this->getDataGenerator()->create_user();
        $users[] = $this->getDataGenerator()->create_user();
        $users[] = $this->getDataGenerator()->create_user();

        $category = $this->getDataGenerator()->create_category();

        $course = $this->getDataGenerator()->create_course(array(
            'shortname' => 'course1',
            'category' => $category->id,
        ));

        $maninstance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'manual'), '*', MUST_EXIST);
        $manual = enrol_get_plugin('manual');

        foreach ($users as $user) {
            $manual->enrol_user($maninstance, $user->id, $studentrole->id);
            sleep(1); // So the timemodified times are different between updates.
            $manual->update_user_enrol($maninstance, $user->id, ENROL_USER_SUSPENDED);
        }

        // Simulate fresh install and run adhoc task.
        $DB->delete_records('report_enrolaudit');
        $adhoctask = new \report_enrolaudit\task\populate_enrolaudit_log_table();
        $adhoctask->execute();

        foreach ($users as $user) {
            $params = ['userid' => $user->id];

            $enrolauditcount = $DB->count_records('report_enrolaudit', $params);
            $userenrolment = $DB->get_record('user_enrolments', $params);

            $this->assertEquals('2', $enrolauditcount);

            $params['change'] = \report_enrolaudit\enrolaudit::ENROLMENT_STATUS_SUSPENDED;
            $enrolaudit = $DB->get_record('report_enrolaudit', $params);

            $this->assertNotFalse($enrolaudit);
            $this->assertEquals($course->id, $enrolaudit->courseid);
            $this->assertEquals($userenrolment->id, $enrolaudit->userenrolmentid);
        }

    }
}
