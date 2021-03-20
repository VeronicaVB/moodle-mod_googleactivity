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
 * The global googleactivity configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_googleactivity
 * @copyright  2019 Michael de Raadt <michaelderaadt@gmail.com>
 *             2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    // Get Google OAuth providers.
    $choices = [];
    $issuers = \core\oauth2\api::get_all_issuers();
    foreach ($issuers as $issuer) {
        if (strpos($issuer->get('baseurl'), 'accounts.google.com') !== false) {
            $choices[$issuer->get('id')] = s($issuer->get('name'));
        }
    }

    // Output a link to configure OAuth settings.
    $url = new moodle_url('/admin/tool/oauth2/issuers.php');
    $settings->add(new admin_setting_heading('googleactivity_oauthlink', '', get_string('oauth2link', 'googleactivity', $url->out())));

    // Output list of OAuth issuers.
    if (!empty($choices)) {
        $settings->add(new admin_setting_configselect('googleactivity_oauth', get_string('oauth2services', 'googleactivity'),
                get_string('oauth2servicesdesc', 'googleactivity'), null, $choices));
    }

    $settings->add(new admin_setting_configtext('mod_googleactivity/googleactivity_api_key', get_string('googleactivity_api_key', 'googleactivity'),
            get_string('googleactivity_api_key_desc', 'googleactivity'), ''));

    $settings->add(new admin_setting_configtext('mod_googleactivity/referrer', get_string('googleactivity_referrer', 'googleactivity'),
            get_string('googleactivity_referrer_key_desc', 'googleactivity'), ''));

    $menu = array();
    foreach (core_component::get_plugin_list('assignfeedback') as $type => $notused) {
        $visible = !get_config('assignfeedback_' . $type, 'disabled');
        if ($visible) {

            if ($type == 'comments') {
                $menu['assignfeedback_' . $type] = new lang_string('pluginname', 'assignfeedback_' . $type);
            }
        }
    }

    // The default here is feedback_comments (if it exists).
    $name = new lang_string('feedbackplugin', 'mod_googleactivity');
    $description = new lang_string('feedbackpluginforgradebook', 'mod_googleactivity');
    $settings->add(new admin_setting_configselect('googleactivity/feedback_plugin_for_gradebook',
            $name,
            $description,
            'assignfeedback_comments',
            $menu));
}