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
 * Library of interface functions and constants for module googleactivity
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 *
 * All the googleactivity specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_googleactivity
 * @copyright  2019 Michael de Raadt <michaelderaadt@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/googledrive.php');


/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function googleactivity_supports($feature)
{

    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return false;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return false;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the googleactivity into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $googleactivity Submitted data from the form in mod_form.php
 * @param mod_googleactivity_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted googleactivity record
 */
function googleactivity_add_instance(stdClass $googleactivity, mod_googleactivity_mod_form $mform = null)
{
    global $USER;

    try {
      // print_object($googleactivity);exit;

        $googleactivity->timecreated = time();

        $context = context_course::instance($googleactivity->course);
        $gdrive = new googledrive($context->id);

        if (!$gdrive->check_google_login()) {
            $googleauthlink = $gdrive->display_login_button();
            $mform->addElement('html', $googleauthlink);
            debugging('Error - not authenticated with Google!');
        }

        $author = array('emailAddress' => $USER->email, 'displayName' => fullname($USER));
        $students = get_enrolled_students($googleactivity->course);
        $group_grouping = [];
        $dist = '';
        
        // Check if the distribution involves groups and/ or groupings.
        if (!empty(($mform->get_submitted_data())->groups) && !everyone(($mform->get_submitted_data())->groups)) {
            list($group_grouping, $dist) = prepare_json(($mform->get_submitted_data())->groups, $googleactivity->course);
        }

        list($dist, $owncopy) = distribution_type($mform->get_submitted_data(), $dist);
        
        if (!empty($group_grouping)) {
            list($googleactivity->group_grouping_json, $students) = get_students_based_on_group_grouping_distribution($dist, $group_grouping);
        }

        if ($students == null) {
            throw new exception('No Students provided. The files were not created');
        }

        if ($googleactivity->use_document == 'existing') {

            $googleactivity->document_type = get_file_type_from_string($googleactivity->google_doc_url);
            list($file, $parentfolderid) = $gdrive->share_existing_file($googleactivity, $author);
            
        } else {

            // Save file in a new folder.            
            list($parentfolderid, $createddate) = $gdrive->create_folder($googleactivity->name_doc, $author);

            // Create master file.
            $file =  $gdrive->create_file(
                $googleactivity->name_doc,
                $googleactivity->document_type,
                $author,
                $students,
                $parentfolderid
            );

            $googleactivity->name = $googleactivity->name_doc; // In case it looses the name when creating.
        }

        $googleactivity->id = save_instance($googleactivity, $file, $file->alternateLink, $parentfolderid, $owncopy, $dist);
        //googleactivity_grade_item_update($googleactivity);

        return $googleactivity->id;

    } catch (Exception $ex) {

        throw $ex;
    }
}

/**
 * Updates an instance of the googleactivity in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $googleactivity An object from the form in mod_form.php
 * @param mod_googleactivity_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function googleactivity_update_instance(stdClass $googleactivity, mod_googleactivity_mod_form $mform = null)
{
    global $DB;

    $googleactivity->timemodified = time();
    $googleactivity->id = $googleactivity->instance;

    if ($googleactivity->intro == null) $googleactivity->intro = $mform->get_current()->intro;
    if ($googleactivity->introformat == null) $googleactivity->introformat = $mform->get_current()->introformat;

    $googleactivity->introeditor = null;

    // You may have to add extra stuff in here.

    $result = $DB->update_record('googleactivity', $googleactivity);

    //googleactivity_grade_item_update($googleactivity);

    return $result;
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every googleactivity event in the site is checked, else
 * only googleactivity events belonging to the course specified are checked.
 * This is only required if the module is generating calendar events.
 *
 * @param int $courseid Course ID
 * @return bool
 */
function googleactivity_refresh_events($courseid = 0)
{
    global $DB;

    if ($courseid == 0) {
        if (!$googleactivitys = $DB->get_records('googleactivity')) {
            return true;
        }
    } else {
        if (!$googleactivitys = $DB->get_records('googleactivity', array('course' => $courseid))) {
            return true;
        }
    }

    foreach ($googleactivitys as $googleactivity) {
        // Create a function such as the one below to deal with updating calendar events.
        // googleactivity_update_events($googleactivity);
    }

    return true;
}

/**
 * Removes an instance of the newmodule from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function googleactivity_delete_instance($id)
{
    global $DB;

    if (!$googleactivity = $DB->get_record('googleactivity', array('id' => $id))) {
        return false;
    }
    

    // Delete any dependent records here.

    $DB->delete_records('googleactivfeedback_comments', array('googleactivityid' => $googleactivity->id));
    $DB->delete_records('google_activity_submissions', array('googleactivityid' => $googleactivity->id));
    $DB->delete_records('google_activity_grades', array('googleactivityid' => $googleactivity->id));
    $DB->delete_records('google_activity_work_task', array('googleactivityid' => $googleactivity->id));
    $DB->delete_records('google_activity_folders', array('googleactivityid' => $googleactivity->id));
    $DB->delete_records('google_activity_files', array('googleactivityid' => $googleactivity->id));
    $DB->delete_records('googleactivity', array('id' => $googleactivity->id));


    //googleactivity_grade_item_delete($googleactivity);

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course The course record
 * @param stdClass $user The user record
 * @param cm_info|stdClass $mod The course module info object or record
 * @param stdClass $googleactivity The googleactivity instance record
 * @return stdClass|null
 */
function googleactivity_user_outline($course, $user, $mod, $googleactivity)
{

    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * It is supposed to echo directly without returning a value.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $googleactivity the module instance record
 */
function googleactivity_user_complete($course, $user, $mod, $googleactivity)
{
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in googleactivity activities and print it out.
 *
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 */
function googleactivity_print_recent_activity($course, $viewfullnames, $timestart)
{
    return false;
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link googleactivity_print_recent_mod_activity()}.
 *
 * Returns void, it adds items into $activities and increases $index.
 *
 * @param array $activities sequentially indexed array of objects with added 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 */
function googleactivity_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid = 0, $groupid = 0)
{
}

/**
 * Prints single activity item prepared by {@link googleactivity_get_recent_mod_activity()}
 *
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by {@link get_module_types_names()}
 * @param bool $viewfullnames display users' full names
 */
function googleactivity_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames)
{
}

/**
 * Function to be run periodically according to the moodle cron
 *
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * Note that this has been deprecated in favour of scheduled task API.
 *
 * @return boolean
 */
function googleactivity_cron()
{
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * For example, this could be array('moodle/site:accessallgroups') if the
 * module uses that capability.
 *
 * @return array
 */
function googleactivity_get_extra_capabilities()
{
    return array();
}


/* File API */

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function googleactivity_get_file_areas($course, $cm, $context)
{
    return array();
}

/**
 * File browsing support for googleactivity file areas
 *
 * @package mod_googleactivity
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function googleactivity_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename)
{
    return null;
}

/**
 * Serves the files from the googleactivity file areas
 *
 * @package mod_googleactivity
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the googleactivity's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function googleactivity_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options = array())
{
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    send_file_not_found();
}

/* Navigation API */

/**
 * Extends the global navigation tree by adding googleactivity nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the googleactivity module instance
 * @param stdClass $course current course record
 * @param stdClass $module current googleactivity instance record
 * @param cm_info $cm course module information
 */
/*
function googleactivity_extend_navigation(navigation_node $navref, stdClass $course, stdClass $module, cm_info $cm) {
    // TODO Delete this function and its docblock, or implement it.
}
*/

/**
 * Extends the settings navigation with the googleactivity settings
 *
 * This function is called when the context for the page is a googleactivity module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav complete settings navigation tree
 * @param navigation_node $googleactivitynode googleactivity administration node
 */
function googleactivity_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $googleactivitynode = null)
{
    // TODO Delete this function and its docblock, or implement it.

    // TODO: what Google drive documents are allowed SELECT box
}
