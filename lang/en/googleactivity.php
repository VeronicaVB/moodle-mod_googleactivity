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
 * English strings for googleactivity
 * @package    mod_googleactivity
 * @copyright  2019 Michael de Raadt <michaelderaadt@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['all_share'] = 'All share single copy';
$string['authorised'] = 'Google drive access authorised.';
$string['close'] = 'Close now';
$string['comment'] = 'Comment';
$string['googleactivity:addinstance'] = 'Add a new google activity instance';
$string['googleactivity:viewall'] = 'View all list of file';
$string['googleactivity:update'] = 'Update resource';
$string['googleactivity:manage'] = 'Manage a google activity';
$string['googleactivity:view'] = 'View google activity';
$string['googleactivity:grade'] = 'Grade google activity';
$string['create_new'] = 'Create blank';
$string['create_new_folder'] = 'Create new folder';
$string['use_existing_folder'] = 'Use existing folder';
$string['distribution'] = 'Distribution';
$string['dist_student_copy'] = 'Each student gets a copy';
$string['dist_group_copy'] = 'Each group gets  a copy';
$string['dist_all_share_same'] = ' Students share same copy';
$string['dist_grouping_copy'] = 'Each grouping gets a copy';
$string['groups'] = 'Groups';
$string['groupings'] = '---- GROUPINGS ----';
$string['dist_group_grouping_copy'] = 'Each group and grouping gets a copy';
$string['folder_name'] = 'Folder Name';
$string['dir_name_help'] = 'The folder you create will be inside CGS/{$a}';
$string['dir_name_help_help'] = '{$a}';
$string['document_name'] = 'Document name';
$string['document_type'] = 'Document type';
$string['each_gets_own'] = 'Each gets their own copy';
$string['edit'] = 'Edit';
$string['filetypes'] = 'File type';
$string['google_doc'] = 'Google Doc';
$string['google_doc_url'] = 'Existing Doc URL';
$string['google_dir_url'] = 'Existing Folder URL';
$string['google_sheet'] = 'Google Sheet';
$string['google_slides'] = 'Google Slides';
$string['google_folder'] = 'Google Folder';
$string['group_select'] = 'Groups  Selection';
$string['description'] = 'Description';
$string['group_select_help'] = '<strong>Everyone</strong>: All Students enrolled in the course. <br> <strong>All Groups</strong>: Students belonging to any of the available groups. <br>'
    . '<strong>All Groupings</strong>: Groups belonging to any of the groupings in the course.';

$string['modulename'] = 'Google Activity';
$string['modulename_help'] = 'The Google Doc activity module enables a teacher to share G Suite documents (docs, sheets, slides) with students, provide grades and feedback.<br><br>'
    . 'Students can submit their work provided the permission of the file is either edit or comment. <br>'
    . 'When reviewing G suite documents, teachers can leave feedback comments. <br><br>'
    . 'Google activity can be graded using a numerical scale. Final grades are recorded in the gradebook.';
$string['modulenameplural'] = 'Google Activity';
$string['oauth2link'] = 'This plugin requires a Google OAuth service to be set up. See the <a href="{$a}" title="'.
                        'Link to OAuth 2 services configuration">OAuth 2 services</a> configuration page.';
$string['oauth2services'] = 'OAuth 2 service';
$string['oauth2servicesdesc'] = 'Choose the Google OAuth2 service the Google Activity will use.';
$string['permissions'] = 'Permissions';
$string['everyone'] = 'Everyone';
$string['all_groups'] = 'All Groups';
$string['all_groupings'] = 'All Groupings';
$string['pluginname'] = 'Google Activity';
$string['docnameinvalid'] = 'The name provided is invalid';
$string['urlempty'] = 'If sharing an existing doc, you must enter a documents URL.';
$string['dirurlempty'] = 'If sharing an existing folder, you must enter a folders URL';
$string['urlinvalid'] = 'The doc URL you provided doesn\'t seem to be valid. Be sure to copy the whole URL, including https://';
$string['dirurlinvalid'] = 'The folder URL you provided doesn\'t seem to be valid. Be sure to copy the whole URL, including https://';
$string['foldernotpermitted'] = "Use existing <strong>folders</strong> is not supported";
$string['sharelimitation' ] = "Some users will not be able to edit this file. Please consider another distribution.";
$string['notsharedwithuser'] = 'You do not have editing permissions on this file.';
$string['groupsinvalidselection'] = 'Please select group value(s)';
$string['groupingsinvalid'] = 'You have chosen All Groupings and a particular grouping. Either select All Groupings or the grouping you want to share a file with.';
$string['groupingsinvalidselection'] = 'Please Select Grouping value(s)';
$string['std_invalid_selection'] = 'The selected combination is invalid.';
$string['use_document'] = 'Use document';
$string['use_folder'] = 'Use Folder';
$string['use_existing'] = 'Use existing';
$string['view'] = 'View';
$string['windowillclose'] = 'This window will close in 5 seconds.';
$string['pluginadministration'] = '';
$string['sharedurl'] = 'Link';
$string['googleactivity_api_key'] = 'Google Drive API Key';
$string['googleactivity_api_key_desc'] = 'Generate the API key in your Google console ';
$string['googleactivity_referrer'] = 'HTTP referrers';
$string['googleactivity_referrer_key_desc'] = 'Specify which Web Sites can see the API Key. The URL has to match the URL set in the Website Restrictions section in the developers console';
$string['groupheader'] = 'Groups';
$string['clicktoopen'] = 'Click {$a} to open resource';
$string['grading'] = 'grade';
$string['viewgrading'] = 'View all submissions';
$string['title'] = 'First name';
$string['all'] = 'All';
$string['notsubmitted'] = 'NOTSUBMITTED';
$string['submitted'] = 'SUBMITTED';
$string['loading'] = 'Loading...';
$string['nousersselected'] = 'No users selected';
$string['viewgrading'] = 'View all submissions';
$string['assign:viewgrades'] = 'View grades';
$string['changeuser'] = 'Change user';
$string['gradechangessaveddetail'] = 'The changes to the grade and feedback were saved';
$string['savingchanges'] = 'Saving Changes';
$string['saveandcontinue'] = 'Save and continue';
$string['unsavedchanges'] = 'Unsaved changes';
$string['unsavedchangesquestion'] = 'There are unsaved changes to grades or feedback. Do you want to save the changes and continue?';
$string['currentgradeingradebook'] = 'Current grade in gradebook';
$string['gradelockedoroverrideningradebook'] = 'This grade is locked or overridden in the gradebook.';
$string['feedbackplugin'] = 'Feedback plugin';
$string['feedbacksettings'] = 'Feedback settings';
$string['feedbackpluginforgradebook'] = 'Feedback plugin that will push comments to the gradebook';
$string['nousersselected'] = 'No users selected';
$string['groupheader'] = 'Groups';
$string['memberheader'] = 'Members';
$string['linkheader'] = 'Link';
$string['statusheader'] = 'Status';
$string['studentaccessheader'] = 'Student access';
$string['studenttableaccessheader'] = 'Access';
$string['fullnameheader'] = 'Full name';
$string['groupsgroupingsheader'] = 'Groups - Groupings';
$string['gradeheader'] = 'Grade';
$string['logintosubmit'] = 'Log in to submit';
$string['logintoviewfolder'] = 'Log in to view folder';
$string['signout'] = 'Sign out from other Google accounts';
$string['emptyfoldermessage'] = 'The folder is empty. ';
$string['emailmessageGoogleNotification'] = 'Link to the course:  {$a->url}';
$string['showdescription'] = 'Show/hide activity description';