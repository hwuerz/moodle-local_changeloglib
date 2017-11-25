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

// Module metadata.
$string['pluginname'] = 'Changelog Lib';
$string['deletion_backup_clean_task'] = 'Clean old backups';
$string['settings_pdftotext_path'] = 'pdftotext path';
$string['settings_pdftotext_path_help'] = 'The path to the command line program pdftotext. It is used to convert pdf documents before differences can be detected.';
$string['settings_diff_path'] = 'diff path';
$string['settings_diff_path_help'] = 'The path to the command line program diff. It is used to compare text documents.';
$string['warning'] = 'Warning';
$string['success'] = 'Success';
$string['settings_pdftotext_available'] = '<p>The program pdftotext was found and can be used to detect changes</p>';
$string['settings_pdftotext_not_available'] = '<p>The program pdftotext was not found at the given path. Please ask your server admin to run <code>apt-get install poppler-utils</code> and visit this page again. This message disappears if the tool is installed correctly.</p>';
$string['settings_diff_available'] = '<p>The program diff was found and can be used to detect changes</p>';
$string['settings_diff_not_available'] = '<p>The program <code>diff</code> was not found at the given path. Please ask your server admin to install it and visit this page again. This message disappears if the tool is installed correctly.</p>';
