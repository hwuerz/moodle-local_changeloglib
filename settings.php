<?php
// This file is part of ChangelogLib plugin for Moodle - http://moodle.org/
//
// ChangelogLib is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// ChangelogLib is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with ChangelogLib.  If not, see <http://www.gnu.org/licenses/>.

/**
 * ChangelogLib.
 *
 * A library to support other plugins which have to detect changes in files.
 * This plugin does not do anything of its own. It is only useful as a
 * dependency for other plugins.
 *
 * @package   local_changeloglib
 * @author    Hendrik Wuerz <hendrikmartin.wuerz@stud.tu-darmstadt.de>
 * @copyright (c) 2017 Hendrik Wuerz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__) . '/definitions.php');
require_once(dirname(__FILE__) . '/classes/pdftotext.php');
require_once(dirname(__FILE__) . '/classes/diff_detector.php');

if ($hassiteconfig) {

    // Create the new settings page.
    $settings_name = get_string('pluginname', LOCAL_CHANGELOGLIB_NAME);
    $settings = new admin_settingpage( LOCAL_CHANGELOGLIB_NAME, $settings_name);

    // Create.
    $ADMIN->add( 'localplugins', $settings );

    $settings->add(new admin_setting_configtext(
        LOCAL_CHANGELOGLIB_NAME . '/pdftotext_path',
        new lang_string('settings_pdftotext_path', LOCAL_CHANGELOGLIB_NAME),
        new lang_string('settings_pdftotext_path_help', LOCAL_CHANGELOGLIB_NAME),
        '/usr/bin/pdftotext',  '/^.*$/'));

    $settings->add(new admin_setting_configtext(
        LOCAL_CHANGELOGLIB_NAME . '/diff_path',
        new lang_string('settings_diff_path', LOCAL_CHANGELOGLIB_NAME),
        new lang_string('settings_diff_path_help', LOCAL_CHANGELOGLIB_NAME),
        '/usr/bin/diff',  '/^.*$/'));

    // Check whether pdftotext was found at the given path.
    if (local_changeloglib_pdftotext::is_installed()) {
        $settings->add(new admin_setting_heading(
            LOCAL_CHANGELOGLIB_NAME . '/pdftotext_available',
            new lang_string('success', LOCAL_CHANGELOGLIB_NAME),
            new lang_string('settings_pdftotext_available', LOCAL_CHANGELOGLIB_NAME)));
    } else {
        $settings->add(new admin_setting_heading(
            LOCAL_CHANGELOGLIB_NAME . '/pdftotext_not_available',
            new lang_string('warning', LOCAL_CHANGELOGLIB_NAME),
            new lang_string('settings_pdftotext_not_available', LOCAL_CHANGELOGLIB_NAME)));
    }

    // Check whether diff was found at the given path.
    if (local_changeloglib_diff_detector::is_command_line_diff_installed()) {
        $settings->add(new admin_setting_heading(
            LOCAL_CHANGELOGLIB_NAME . '/diff_available',
            new lang_string('success', LOCAL_CHANGELOGLIB_NAME),
            new lang_string('settings_diff_available', LOCAL_CHANGELOGLIB_NAME)));
    } else {
        $settings->add(new admin_setting_heading(
            LOCAL_CHANGELOGLIB_NAME . '/diff_not_available',
            new lang_string('warning', LOCAL_CHANGELOGLIB_NAME),
            new lang_string('settings_diff_not_available', LOCAL_CHANGELOGLIB_NAME)));
    }
}
