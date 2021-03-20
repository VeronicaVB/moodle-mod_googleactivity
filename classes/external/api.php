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
 * @copyright 2021 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

namespace mod_googleactivity\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/externallib.php');

use external_api;

class api extends external_api {
    use create_students_files;
    // use create_group_file;
    // use create_grouping_file;
    // use create_group_grouping_file;
    // use create_group_folder_struct;
    // use delete_files;
    // use update_sharing;
    // use submit_student_file;
    // use google_login_student;
    // use grade_student_file;
    // use save_quick_grading;
    // use get_participant;
    // use list_participants;
    // use get_participant_by_id;
}