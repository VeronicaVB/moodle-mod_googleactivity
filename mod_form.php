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
 * The main googleactivity configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_googleactivity
 * @copyright  2019 Michael de Raadt <michaelderaadt@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();


require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/googledrive.php');
require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/course/lib.php');


/**
 * Module instance settings form
 *
 * @package    mod_googleactivity
 * @copyright  2019 Michael de Raadt <michaelderaadt@gmail.com>
 * @copyright  2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_googleactivity_mod_form extends moodleform_mod {

    /**
     * Defines forms elements.
     */
    public function definition() {
        global $CFG, $PAGE, $USER;

        // Add the javascript required to enhance this mform.
        $PAGE->requires->js_call_amd('mod_googleactivity/processing_control', 'init');

        $update = optional_param('update', 0, PARAM_INT);
        $course_groups = groups_get_all_groups($PAGE->course->id);
        $course_grouping = groups_get_all_groupings($PAGE->course->id);

        // Start the instance config form.
        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Get the Google Drive object.
        $client = new googledrive($this->context->id);
        
        // Hiden parameters.
        $mform->addElement('hidden', 'group_grouping_json', '');
        $mform->setType('group_grouping_json', PARAM_RAW);
        $mform->addElement('hidden', 'docid', '');
        $mform->setType('docid', PARAM_RAW);

        // Check whether the user is logged into their Google account.
        if (!$client->check_google_login()) {

            // Print the login button.
            $button = $client->display_login_button();
            $mform->addElement('static', '', '', $button);

            // Add empty standard elements with only a cancel button.
            $this->standard_hidden_coursemodule_elements();
            $mform->addElement('hidden', 'completionunlocked', 0);
            $mform->setType('completionunlocked', PARAM_INT);
            $this->add_action_buttons(true, false, false);

        } else {
           
            $radioarray = array();
            $radioarray[] = $mform->createElement('radio', 'use_document', '',
                get_string('create_new', 'googleactivity'), 'new');
            $radioarray[] = $mform->createElement('radio', 'use_document', '',
                get_string('use_existing', 'googleactivity'), 'existing');
            $use_document = $mform->addGroup($radioarray, 'document_choice',
                get_string('use_document', 'googleactivity'), array(' '), false);
            $mform->setDefault('use_document', 'new');
            
            $name_doc = $mform->addElement('text', 'name_doc',
                get_string('document_name', 'googleactivity'), array('size' => '64'));
            $mform->setType('name_doc', PARAM_TEXT);
            $mform->hideif('name_doc', 'use_document', 'eq', 'existing');
            $mform->addRule('name_doc', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
            $mform->setDefault('name_doc', '');
            $mform->disabledIf('name_doc', 'use_document', 'eq', 'existing');
           
            if ($update != 0) {
                $use_document->freeze();
                $name_doc->freeze();
            }
            
            $this->standard_intro_elements();
            $types = google_filetypes();
            
            $typesarray = array();
            
            foreach ($types as $key => $type) {
              
                $imgurl = new moodle_url($CFG->wwwroot . '/mod/googleactivity/pix/' . $type['icon']);
                $image = html_writer::empty_tag('img', array('src' => $imgurl, 'style' => 'width:30px;')) . '&nbsp;';
                $doctype = $mform->createElement('radio', 'document_type', '', $image . $type['name'], $key);

                if ($update) {
                    $doctype->freeze();
                }
                $typesarray[] = $doctype;
            }

            $mform->addGroup($typesarray, 'document_type', get_string('document_type', 'googleactivity'), array(' '), false);
            $mform->setDefault('document_type', 'document');

            $mform->hideif('document_type', 'use_document', 'eq', 'existing');

            if ($update != 0) {
                $doctype->freeze();
            }

            $existingfile = $mform->addElement('text', 'google_doc_url', get_string('google_doc_url', 'googleactivity'), array('size' => '64'));
            $mform->setType('google_doc_url', PARAM_RAW_TRIMMED);
            $mform->addRule('google_doc_url', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
            $mform->hideif('google_doc_url', 'use_document', 'eq', 'new');

            if ($update) {
                $existingfile->freeze();
            }

            $permissions = array(
                'edit' => get_string('edit', 'googleactivity'),
                'comment' => get_string('comment', 'googleactivity'),
                'view' => get_string('view', 'googleactivity'),
            );

            $mform->addElement('select', 'permissions', get_string('permissions', 'googleactivity'), $permissions);
            $mform->setDefault('permissions', 'edit');

            // Groups.
            if (!empty($course_groups)) {
                $groups = array('00_everyone' => get_string('everyone', 'googleactivity'));
            }

            $count_empty = 0;
            foreach ($course_groups as $g) {
                // Skip empty groups.
                if (!groups_get_members($g->id, 'u.id')) {
                    $count_empty++;
                    continue;
                }

                $groups[$g->id . '_group'] = $g->name;
            }

            $not_show = false;

            if ($count_empty == count($course_groups)) {
                $not_show = true;
            }

            if ((!empty($course_groups) || !empty($course_grouping)) && !$not_show) {
                $distribution = array(
                    'std_copy' => get_string('dist_student_copy', 'googleactivity'),
                    'group_copy' => get_string('dist_group_copy', 'googleactivity'),
                    'dist_share_same' => get_string('dist_all_share_same', 'googleactivity'));
            } else {
                $distribution = array(
                    'std_copy' => get_string('dist_student_copy', 'googleactivity'),
                    'dist_share_same' => get_string('dist_all_share_same', 'googleactivity'));
            }

            $distselect = $mform->addElement('select', 'distribution', get_string('distribution', 'googleactivity'),
                $distribution);

            if ($update != 0) {
                $distselect->freeze();
            }

            // Grouping.
            if (!empty($course_grouping)) {
                $groups['0_grouping'] = get_string('groupings', 'googleactivity');
            }

            foreach ($course_grouping as $g) {
                // Only list those groupings with groups in it.
                if (empty(groups_get_grouping_members($g->id))) {
                    continue;
                }
                $groups[$g->id . '_grouping'] = $g->name;
            }

            if (!empty($course_groups) && !$not_show) {
                $selectgroups = $mform->addElement('select', 'groups', get_string('groups', 'googleactivity'), $groups);
                $mform->setDefault('groups', '00_everyone');
                $selectgroups->setMultiple(true);
                $mform->addHelpButton('groups', 'group_select', 'googleactivity');

                if ($update != 0) {
                    $selectgroups->freeze();
                }
            }

            // Grade settings.
            $this->standard_grading_coursemodule_elements();

            // Add standard buttons, common to all modules.
            $this->standard_coursemodule_elements();
            $this->add_action_buttons(true, null, false);
        }
    }

    /**
     * Validates forms elements.
     */
    public function validation($data, $files) {

        // Validating doc URL
        $errors = parent::validation($data, $files);

        if ($data['update'] == 0) {
            if ($data['use_document'] != 'new') {
                if (empty($data['google_doc_url'])) {
                    $errors['google_doc_url'] = get_string('urlempty', 'googleactivity');
                } else if (!googleactivity_appears_valid_url($data['google_doc_url'])
                    || get_file_id_from_url($data['google_doc_url']) == null) {
                    $errors['google_doc_url'] = get_string('urlinvalid', 'googleactivity');
                } else if (get_file_type_from_string($data['google_doc_url']) == 'folder') {
                    $errors['google_doc_url'] = get_string('foldernotpermitted', 'googleactivity');
                }
            } else {
                if (empty($data['name_doc'])) {
                    $errors['name_doc'] = get_string('docnameinvalid', 'googleactivity');
                }
            }

            if (isset($data['groups']) && $this->group_validation($data)) {
                $errors['groups'] = get_string('std_invalid_selection', 'googleactivity');
            }

            // if ($this->validatesharelimitation($data['distribution'], $data['groups'])) {
            //     $errors['distribution'] = get_string('sharelimitation', 'googleactivity');
            // }
        }

        return $errors;
    }

    public function group_validation($data) {
        $everyone_group_grouping = in_array('00_everyone', $data['groups']) && count($data['groups']) > 1;
        return $everyone_group_grouping;
    }

    public function set_data($default_values) {

        isset($default_values->name) ? ($default_values->name_doc = $default_values->name ) : $default_values->name_doc = '';

        if (isset($default_values->distribution)) {
            $default_values->groups = $this->get_group_ids($default_values);
            $default_values->distribution = $this->get_distribution($default_values->distribution);
        }

        isset($default_values->use_document) ? ($default_values->use_document = $default_values->use_document ) : $default_values->name_doc =
                '';

        parent::set_data($default_values);
    }

    private function get_distribution($distribution) {

        if ($distribution == 'std_copy' || $distribution == 'std_copy_group'
            || $distribution == 'std_copy_grouping' || $distribution == 'std_copy_group_grouping') {
            return 'std_copy';
        }

        if ($distribution == 'group_copy' || $distribution == 'grouping_copy'
            || $distribution == 'group_grouping_copy') {
            return 'group_copy';
        }

        if ($distribution == 'dist_share_same' || $distribution == 'dist_share_same_group'
            || $distribution == 'dist_share_same_grouping' || $distribution == 'dist_share_same_group_grouping') {
            return 'dist_share_same';
        }
    }

    private function get_group_ids($default_values) {
        $type = '';
        $selected = array();
        $data = json_decode($default_values->group_grouping_json);
        if ($default_values->distribution == 'std_copy'
            || $default_values->distribution == 'dist_share_same') {
            $type = 'everyone';
        } else if ($default_values->distribution == 'std_copy_grouping'
            || $default_values->distribution == 'grouping_copy'
            || $default_values->distribution == 'dist_share_same_grouping') {
            $type = 'grouping';
        } else if ($default_values->distribution == 'std_copy_group_grouping'
            || $default_values->distribution == 'group_grouping_copy'
            || $default_values->distribution == 'dist_share_same_group_grouping') {
            $type = 'group_grouping';
        } else if ($default_values->distribution == 'std_copy_group'
            || $default_values->distribution == 'group_copy'
            || $default_values->distribution == 'dist_share_same_group') {
            $type = 'group';
        }

        if ($type == 'everyone') {
            $selected = ['00_everyone'];
        } else {
            $selected = get_groups_formatted_for_form($data);
        }

        return $selected;
    }

    // Google Drive API allows up to 100 users to edit  the same file
    // If the course has more students and the sharing distribution is Share same
    // Then trigger error.
    private function validatesharelimitation($distrib, $groups) {
        global $PAGE;

        $context = \context_course::instance($PAGE->course->id);
        $coursestudents = get_role_users(5, $context, false, 'u.id');

        if (isset($groups) && $groups[0] != '00_everyone') {
            list($group_grouping, $dist) = prepare_json($groups, $PAGE->course->id);
            $jsongroup = new stdClass();
            $jsongroup->c = $group_grouping;

            if ($dist == 'grouping') {
                $students = get_users_in_grouping($coursestudents, json_encode($jsongroup));
            } else {
                $students = get_users_in_group($coursestudents, json_encode($jsongroup), $PAGE->course->id);
            }
        } else {
            $students = $coursestudents;
        }

        $totalstudents = count($students);

        return ($distrib == 'dist_share_same' && $totalstudents >= 100);
    }

    }
