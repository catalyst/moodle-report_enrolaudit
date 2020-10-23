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
 * Enrol audit filters.
 *
 * @package    report_enrolaudit
 * @copyright  2020 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_enrolaudit\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

/**
 * Enrol audit filters form.
 *
 * @package    report_enrolaudit
 * @copyright  2020 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filters extends \moodleform {
    /**
     * Form definition
     *
     * @throws \coding_exception
     */
    public function definition() {
        $mform = $this->_form;

        if ($this->_customdata['sitelevel']) {
            $mform->addElement('header', 'usersearch', get_string('user'));

            // Firstname.
            $mform->addElement('text', 'firstname', get_string('firstname'));
            $mform->setType('firstname', PARAM_ALPHA);

            // Lastname.
            $mform->addElement('text', 'lastname', get_string('lastname'));
            $mform->setType('lastname', PARAM_ALPHA);

            // Course name.
            $mform->addElement('header', 'coursesearch', get_string('course'));
            $mform->addElement('text', 'coursename', get_string('form:coursename', 'report_enrolaudit'));
            $mform->setType('coursename', PARAM_ALPHANUM);

            $this->add_action_buttons(false, get_string('search'));
        }
    }
}
