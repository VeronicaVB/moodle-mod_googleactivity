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
 * Internal library of functions for module googleactivity
 *
 * All the googleactivity specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_googleactivity
 * @copyright  2019 Michael de Raadt <michaelderaadt@gmail.com>
 *             2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

define('GDRIVEFILEPERMISSION_COMMENTER', 'comment'); // Student can Read and Comment.
define('GDRIVEFILEPERMISSION_EDITOR', 'edit'); // Students can Read and Write.
define('GDRIVEFILEPERMISSION_READER', 'view'); // Students can read.
define('GDRIVEFILETYPE_DOCUMENT', 'application/vnd.google-apps.document');
define('GDRIVEFILETYPE_PRESENTATION', 'application/vnd.google-apps.presentation');
define('GDRIVEFILETYPE_SPREADSHEET', 'application/vnd.google-apps.spreadsheet');
define('GDRIVEFILETYPE_FOLDER', 'application/vnd.google-apps.folder');

/**
 * Google Drive file types.
 *
 * @return array google drive file types.
 * http://stackoverflow.com/questions/11412497/what-are-the-google-apps-mime-types-in-google-docs-and-google-drive#11415443
 */
function google_filetypes()
{
    $types = array(
        'document' => array(
            'name'     => get_string('google_doc', 'mod_googleactivity'),
            'mimetype' => 'application/vnd.google-apps.document',
            'icon'     => 'docs.svg',
            'linktemplate' => 'https://docs.google.com/document/d/%s/edit?usp=sharing',
            'linkbegin' => 'https://docs.google.com/document/d/',
            'linkend' => '/edit?usp=sharing'
        ),
        'spreadsheets' => array(
            'name'     => get_string('google_sheet', 'mod_googleactivity'),
            'mimetype' => 'application/vnd.google-apps.spreadsheet',
            'icon'     => 'sheets.svg',
            'linktemplate' => 'https://docs.google.com/spreadsheets/d/%s/edit?usp=sharing',
            'linlbegin' => 'https://docs.google.com/spreadsheets/d/',
            'linkend' => '/edit?usp=sharing'
        ),
        'presentation' => array(
            'name'     => get_string('google_slides', 'mod_googleactivity'),
            'mimetype' => 'application/vnd.google-apps.presentation',
            'icon'     => 'slides.svg',
            'linktemplate' => 'https://docs.google.com/presentation/d/%s/edit?usp=sharing',
            'linkbegin' => 'https://docs.google.com/presentation/d/',
            'linkend' => '/edit?usp=sharing'
        ),
        'folder' => array(
            'name' => get_string('google_folder', 'mod_googleactivity'),
            'mimetype' => 'application/vnd.google-apps.folder',
            'icon' => 'folder.svg',
            'linktemplate' => 'https://drive.google.com/drive/folders/%s/?usp=sharing',
            'linkdisplay' => 'https://drive.google.com/embeddedfolderview?id=%s#list',
        ),
    );

    return $types;
}

/**
 * This methods does weak url validation, we are looking for major problems only,
 * no strict RFE validation.
 * TODO: Make this stricter
 *
 * @param $url
 * @return bool true is seems valid, false if definitely not valid URL
 */
function googleactivity_appears_valid_url($url)
{
    if (preg_match('/^(\/|https?:|ftp:)/i', $url)) {
        // note: this is not exact validation, we look for severely malformed URLs only
        return (bool)preg_match('/^[a-z]+:\/\/([^:@\s]+:[^@\s]+@)?[a-z0-9_\.\-]+(:[0-9]+)?(\/[^#]*)?(#.*)?$/i', $url);
    } else {
        return (bool)preg_match('/^[a-z]+:\/\/...*$/i', $url);
    }
}

/**
 * Helper function to get the file id from a given URL.
 * @param type $url
 * @param type $doctype
 * @return type
 */
function get_file_id_from_url($url)
{

    if (preg_match('#\/(d|folders)\/([a-zA-Z0-9-_]+)#', $url, $match) == 1) {
        $fileid = $match[2];
    }
    return $fileid;
}

function get_file_type_from_string($str)
{

    if (strpos($str, 'document')) {
        return 'document';
    }
    if (strpos($str, 'spreadsheets') || strpos($str, 'spreadsheet')) {
        return 'spreadsheets';
    }
    if (strpos($str, 'presentation')) {
        return 'presentation';
    }
    if (strpos($str, 'folder')) {
        return 'folder';
    }
}

function format_permission($permissiontype)
{
    $commenter = false;
    if ($permissiontype == GDRIVEFILEPERMISSION_COMMENTER) {
        $studentpermissions = 'reader';
        $commenter = true;
    } else if ($permissiontype == GDRIVEFILEPERMISSION_READER) {
        $studentpermissions = 'reader';
    } else {
        $studentpermissions = 'writer';
    }

    return array($studentpermissions, $commenter);
}


//**************************************GROUPS AND GROUPINGS HELPER FUNCTIONS*************************** */
/**
 * Helper function to display groups and/or groupings
 * in form when updating.
 */
function get_groups_formatted_for_form($data)
{
    $dataformatted = [];
    foreach ($data->c as $c) {
        $dataformatted[] = $c->id . '_' . $c->type;
    }
    return $dataformatted;
}

/**
 * Checks if the group option selected is everyone
 * @param  $data
 * @return boolean
 */
function everyone($data)
{
    list($id, $type) = explode('_', current($data));
    return $type == 'everyone';
}

/**
 * Generate an array with stdClass object that has the format
 * needed to generate the JSON
 * @param  $type
 * @param  $data
 * @return array
 */
function prepare_json($data, $courseid = 0)
{
    $conditions = array();

    $combination = !empty((preg_grep("/_grouping/", $data)) && !(empty(preg_grep("/_group/", $data))));
    $dist = '';

    foreach ($data as $d) {
        list($id, $type) = explode('_', $d);
        $condition = new stdClass();
        $dist .= $type . ",";
        $condition->id = $id;
        $condition->type = $type;
        array_push($conditions, $condition);
    }

    $dist = rtrim($dist, ",");
    $combination = (array_unique(explode(',', $dist)));

    if (count($combination) > 1) {
        $dist = 'group_grouping';
    } else if ($combination[0] == 'grouping') {
        $dist = 'grouping';
    } else {
        $dist = 'group';
    }

    return array($conditions, $dist);
}

/**
 *
 * @param type $courseusers
 * @param type $conditionsjson
 * @return array of students with the format needed to create docs.
 */
function get_users_in_group($conditionsjson)
{
    global $COURSE;

    $groupmembers = get_group_members_ids($conditionsjson, $COURSE->id);
    $courseusers = get_role_users(5, context_course::instance($COURSE->id));
    $users = null;

    foreach ($courseusers as  $user) {
        if (in_array($user->id, $groupmembers)) {
            $users[] = $user;
        }
    }

    return $users;
}

/**
 * Get users that belongs to the groupings selected in the form.
 * users can be teachers or students
 * @param type $courseusers
 * @param type $conditionsjson
 * @return string
 */
function get_users_in_grouping($conditionsjson)
{
    global $COURSE;
    
    $courseusers = get_role_users(5, context_course::instance($COURSE->id));
    $groupingmembers = get_grouping_members_ids($conditionsjson);
   
    foreach ($courseusers as $user) {
        if (in_array($user->id, $groupingmembers)) {
            $users[] = $user;
        }
    }

  
    return $users;
}

/**
 * Return the ids of the students from all the groups  the file has to be created for
 * This function is used when the group is set in the general area in the form.
 * @param json $conditionsjson
 * @return array
 */
function get_group_members_ids($conditionsjson)
{

    $j = json_decode($conditionsjson);
    //print_object($j); exit;
    $groupmembers = [];
    $groups = get_groups_details_from_json($j);

    foreach ($groups as $group) {
        $groupmembers = array_merge($groupmembers, groups_get_members($group->id, $fields = 'u.id'));
    }

    return array_column($groupmembers, 'id');
}

function get_grouping_members_ids($conditionsjson)
{

    $j = json_decode($conditionsjson);
    $groupingids = get_grouping_ids_from_json($j);
    $groupingmembers = [];

    foreach ($groupingids as $id) {
        $groupingmembers = array_merge($groupingmembers, groups_get_grouping_members($id, $fields = 'u.id'));
    }

    return array_column($groupingmembers, 'id');
}
/**
 * Filter the group grouping data to just groups without duplicates
 * @global type $DB
 * @param type $data
 * @return array
 */
function get_groups_details_from_json($data)
{

    $groups = [];

    foreach ($data->c as $c) {
        if ($c->type == 'group') {
            $g = new stdClass();
            $g->id = $c->id;
            $g->type = $c->type;
            $g->name = groups_get_group_name($c->id);
            $groups[] = $g;
        }
    }
    // Remove empty groups.
    foreach ($groups as $group => $g) {
        if (!groups_get_members($g->id, 'u.id')) {
            unset($groups[$group]);
        }
    }
    return $groups;
}

function get_groupings_details_from_json($data)
{
    $groupings = [];
    foreach ($data->c as $c) {
        if ($c->type == 'grouping') {
            $g = new stdClass();
            $g->id = $c->id;
            $g->type =  $c->type;
            $g->name = groups_get_grouping_name($c->id);
            $groupings[] = $g;
        }
    }
    return $groupings;
}

function get_grouping_ids_from_json($data)
{
    $groupingids = get_id_detail_from_json($data, "grouping");

    foreach ($groupingids as $id) {
        if (!groups_get_grouping_members($id, 'u.id')) {
            unset($groupingids[$id]);
        }
    }
    return $groupingids;
}



/**
 * Get the group or grouping ids from the group_grouping_json attr.
 * @param stdClass $groupgroupingjson
 * @param string $type
 * @return \stdClass
 */
function get_id_detail_from_json($groupgroupingjson, $type)
{
    $ids = [];

    foreach ($groupgroupingjson->c as $c) {
        if ($c->type == $type) {
            $ids[] = $c->id;
        }
    }
    return $ids;
}
/**
 * dist: distribution type.
 *  
 * Group_grouping_condition: values to form the json
 */
function get_students_based_on_group_grouping_distribution($dist, $group_grouping_condition)
{   
    $jsongroup = new stdClass();
    $jsongroup->c = $group_grouping_condition;    
   
    if ($dist == 'dist_share_same_grouping' || $dist == 'grouping_copy' || $dist == 'std_copy_grouping') {      
        $students = get_users_in_grouping(json_encode($jsongroup));
    } else {
        $students = get_users_in_group(json_encode($jsongroup));
    }

    return array(json_encode($jsongroup), $students);
}

/**
 * Create a string with the group ids a student belongs to
 * @param type $userid
 * @param type $courseid
 * @return type
 */
function get_students_group_ids($userid, $courseid) {

    $ids = groups_get_user_groups($courseid, $userid)[0];
    $group_ids = '';

    foreach ($ids as $i) {
        $group_ids .= $i . '-';
    }

    return rtrim($group_ids, "-");
}


//********************************************END GROUP AND GROUPING HELPERS ***************************************/
function oauth_ready()
{
}

function get_enrolled_students($courseid)
{

    $context = \context_course::instance($courseid);

    $coursestudents = get_role_users(5, $context, false, 'u.*', 'u.lastname', 'u.id ASC');

    foreach ($coursestudents as $student) {
        $students[] = array(
            'id' => $student->id, 'emailAddress' => $student->email,
            'displayName' => $student->firstname . ' ' . $student->lastname
        );
    }

    return $students;
}

/**
 * Get editing and non-editing teachers in the course except for the teacher
 * that is creating the activity
 * Role id = 3 --> Editing Teacher
 * Role id = 4 --> Non editing teacher
 * @param type $courseid
 */
function get_enrolled_teachers($courseid)
{
    global $USER;

    $context = \context_course::instance($courseid);
    $teachers = [];
    $roles = ['3', '4'];
    $courseteachers = get_role_users(
        '',
        $context,
        false,
        'ra.id ,  u.email, u.lastname, u.firstname',
        'ra.id ASC'
    );

    foreach ($courseteachers as $teacher) {

        if ($teacher->id != $USER->id && in_array($teacher->roleid, $roles)) {
            $teachers[] = $teacher;
        }
    }

    return $teachers;
}

/**
 * Distribution types
 * Possible combinations:
 *
 *  Each student in the course gets a copy.
 *  Each student in a group in the course gets a copy.
 *  Each student in a grouping group in the course gets a copy.
 *  All students in the course share same copy.
 *  All students in a group in the course share same copy.
 *  All students in a grouping group in the course share same copy.
 *  Each group gets a copy.
 *  Each grouping gets a copy.
 *  Each group and grouping gets a copy.
 * @param type $data_submmited
 * @param type $dist
 * @return array(string, boolean)
 */
function distribution_type($data_submmited, $dist)
{
    if (
        !empty($data_submmited->groups) && $dist != ''
        && $data_submmited->distribution == 'std_copy'
    ) {
        return array('std_copy_' . $dist, true);
    }

    if (
        !empty($data_submmited->groups) && $dist != ''
        && $data_submmited->distribution == 'dist_share_same'
    ) {
        return array('dist_share_same_' . $dist, false);
    }

    if ($dist == '' && $data_submmited->distribution == 'std_copy') {
        return array($data_submmited->distribution, true);
    }

    if ($dist == '' && $data_submmited->distribution == 'dist_share_same') {
        return array($data_submmited->distribution, false);
    }

    if ($dist == 'group' && $data_submmited->distribution == "group_copy") {
        return array($data_submmited->distribution, false);
    }

    if ($dist == 'grouping' && $data_submmited->distribution == "group_copy") {
        return array($dist . '_copy', false);
    }

    if ($dist == 'group_grouping' && $data_submmited->distribution == 'group_copy') {
        return array($dist . '_copy', false);
    }

    return array($dist, true);
}



/**
 * Helper function to save the instance record in DB
 * @global  $googleactivity
 * @global  $sharedlink
 * @param  $folderid
 * @param  $owncopy
 * @return 
 */
function save_instance(
    $googleactivity,
    $file,
    $sharedlink = '',
    $folderid,
    $owncopy='false',
    $dist,
    $intro = ''
) {
    global $USER, $DB;

    if(!$owncopy || $googleactivity->distribution == 'group_copy'){
        $googleactivity->google_doc_url = $sharedlink;
    }
    
    $googleactivity->docid = $file->id;
    $googleactivity->parentfolderid = $folderid;
    $googleactivity->userid = $USER->id;
    $googleactivity->timeshared = (strtotime($file->createdDate));
    $googleactivity->timemodified = $googleactivity->timecreated;
    $googleactivity->name = empty($googleactivity->name_doc) ? $file->title : $googleactivity->name_doc;
    $googleactivity->use_document = $googleactivity->use_document;
    $googleactivity->sharing = 0;
    $googleactivity->distribution = $dist;

    return $DB->insert_record('googleactivity', $googleactivity);
}

// WHen distribution is group_grouping we need to know if its a grouping to get the id.
function is_grouping($groupings, $foldername) {
    foreach ($groupings as $grouping) {
        if ($grouping->foldername == $foldername) {
            return true;
        }
    }

    return false;
}
