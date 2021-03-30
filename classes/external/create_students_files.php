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
 *  External Web Service Template
 *
 * @package   mod_googleactivity
 * @category
 * @copyright 2021 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_googleactivity\external;

defined('MOODLE_INTERNAL') || die();

use external_function_parameters;
use external_value;
use external_single_structure;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/googleactivity/lib.php');
require_once($CFG->dirroot . '/mod/googleactivity/locallib.php');

/**
 * Trait implementing the external function mod_googleactivity_create_students_file
 * This service performs differently depending on the distribution
 * dist = std_copy: Create a file for each student in the course
 * dist = dist_share_same Creates one file and assign the permissions to all students
 * 
 * For the rest of the distribution there are other services created.
 */
trait create_students_files
{

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     *
     */
    public static function create_students_files_parameters()
    {
        return new external_function_parameters(
            array(
                'students' => new external_value(PARAM_RAW, 'JSON with students details'),
                'instanceid' => new external_value(PARAM_RAW, 'instance ID'),
            )
        );
    }

    /**
     * Create the file for the student
     * @param  string $timetableuser represents a user.
     *         string $timetablerole represents the role of the user.
     *         int $date represents the date in timestamp format.
     *         int $nav represents a nav direction, 0: Backward, 1: Forward.
     * @return a timetable for a user.$by_group, $by_grouping, $group_id, $grouping_id,
     */
    public static function create_students_files($students, $instanceid)
    {
        global $COURSE, $DB;

        $context = \context_user::instance($COURSE->id);
        self::validate_context($context);

        // Parameters validation.
        self::validate_parameters(
            self::create_students_files_parameters(),
            array(
                'students' => $students,
                'instanceid' => $instanceid,
            )
        );

        $filedata = "SELECT * FROM mdl_googleactivity WHERE id = :id ";
        $data = $DB->get_record_sql($filedata, ['id' => $instanceid]);


        // Generate the student files.
        $gdrive = new \googledrive($context->id, false, false, true, false, $data);
        list($data->role, $data->commenter) = format_permission($data->permissions);

        // Get all teachers in the course.
        $teachers = get_enrolled_teachers($data->course);
        $students = json_decode($students);

        if (($data->distribution == 'group_copy'  || $data->distribution == 'dist_share_same_group')) {
            list($records, $status) = $gdrive->dist_share_same_group_helper($data);
        }

        switch ($data->distribution) {
            case 'std_copy':
                list($records, $status) = $gdrive->make_file_copies($students, $data);
                break;
            case 'dist_share_same':
                list($records, $status) = $gdrive->dist_share_same_helper($students, $data);
                break;
            case 'std_copy_group':
                list($records, $status) = $gdrive->make_file_copy_for_groups($data);
                break;
            case 'std_copy_grouping':
                list($records, $status) = $gdrive->make_file_copy_for_grouping($data);
                break;
            case 'dist_share_same_grouping':
                list($records, $status) = $gdrive->dist_share_same_grouping_helper($data);
                break;
            case 'std_copy_group_grouping':
                list($records, $status) = $gdrive->make_file_copy_for_groups($data, true);
                break;
            case 'group_grouping_copy':
                if ($data->document_type == 'folder')
                    list($records, $status) = ($data->document_type != 'folder') ? $gdrive->make_file_group_grouping_helper($data) : $gdrive->make_file_group_grouping_folder_helper($data);
                break;
            case 'dist_share_same_group_grouping':
                list($records, $status) = $gdrive->dist_share_same_group_grouping_herper($data);
                break;
        }



        return array(
            'records' => json_encode($records, JSON_UNESCAPED_UNICODE),
            'status' => json_encode($status, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Describes the structure of the function return value.
     * Returns the URL of the file for the student
     * @return external_single_structure
     *
     */
    public static function create_students_files_returns()
    {
        return new external_single_structure(
            array(
                'records' => new external_value(PARAM_RAW, 'Records created'),
                'status' =>  new external_value(PARAM_RAW, 'Status of the file creation')
            )
        );
    }
}
