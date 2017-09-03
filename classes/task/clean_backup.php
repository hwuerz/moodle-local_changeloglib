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

namespace local_changeloglib\task;

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__) . '/../../definitions.php');

/**
 * Class clean_backup.
 *
 * Defines a task to remove old backups which are not required any longer.
 * @copyright (c) 2017 Hendrik Wuerz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class clean_backup extends \core\task\scheduled_task {

    /**
     * Get the name of this task.
     * @return string The name of the task.
     */
    public function get_name() {
        // Shown in admin screens.
        return get_string('deletion_backup_clean_task', LOCAL_CHANGELOGLIB_NAME);
    }

    /**
     * Executes the task. Removes all unused backups.
     */
    public function execute() {

        // Delete old backup files for changelog generation.
        require_once(dirname(__FILE__).'/../backup_lib.php');
        \local_changeloglib_backup_lib::clean_up_old_files();

    }
}