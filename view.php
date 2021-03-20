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
 * Prints a particular instance of googleactivity
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_googleactivity
 * @copyright  2019 Michael de Raadt <michaelderaadt@gmail.com>
 *             2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/googleactivity/locallib.php');
require_once($CFG->dirroot . '/mod/googleactivity/googleactivity_rendering.php');

$new = optional_param('forceview', 0, PARAM_INT);
$id = required_param('id', PARAM_INT);

$action = optional_param('action', array(), PARAM_ALPHA);
$fs = optional_param('fs', '', PARAM_TEXT);
list ($course, $cm) = get_course_and_cm_from_cmid($id, 'googleactivity');

$googleactivity = $DB->get_record('googleactivity', array('id' => $cm->instance), '*', MUST_EXIST);

$coursecontext = context_course::instance($course->id);

$PAGE->set_context($coursecontext); // Every page needs a context.

require_login($course, true, $cm);

$url = new moodle_url('/mod/googleactivity/view.php', array('id' => $cm->id));

$PAGE->set_url($url);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(format_string($googleactivity->name));
$PAGE->set_pagetype('course-view-' . $course->format);  // To get the blocks exactly like the course.
$PAGE->add_body_class('path-user');
$PAGE->set_other_editing_capability('moodle/course:manageactivities');

// Output starts here.
echo $OUTPUT->header();

$created = ($googleactivity->sharing == 1);

$t = new googleactivity_rendering($course->id, false, $coursecontext, $cm, $googleactivity, $created);
$t->render_table();

$PAGE->requires->js_call_amd('mod_googleactivity/create_controls', 'init', array($created, $googleactivity->distribution));

// Finish the page.
echo $OUTPUT->footer();
