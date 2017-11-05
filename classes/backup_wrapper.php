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

require_once(dirname(__FILE__) . '/../definitions.php');

/**
 * A wrapper around a backup. Has access to the database record and the stored_file.
 * @copyright (c) 2017 Hendrik Wuerz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_changeloglib_backup_wrapper {

    /**
     * @var stdClass|null $record The database record of the backup. Null if no record exists (further candidate).
     */
    private $record;

    /**
     * @var stored_file $file The stored file for this backup.
     */
    private $file;

    /**
     * @var boolean $is_used Whether this backup is already used as a predecessor by a file.
     */
    private $is_used;

    /**
     * local_changeloglib_backup_wrapper constructor.
     * @param stdClass|null $record The database record of the backup. Null if none exists.
     * @param stored_file $file The stored file for this backup.
     */
    public function __construct(stdClass $record, stored_file $file) {
        $this->record = $record;
        $this->file = $file;
    }

//    /**
//     * @return stored_file The candidate which is analysed in this object.
//     */
//    public function get_candidate() {
//        return $this->candidate;
//    }

    /**
     * @return stored_file The stored file for this backup.
     */
    public function get_file() {
        return $this->file;
    }

    /**
     * @return boolean Whether this backup is used as a predecessor by a file or not.
     */
    public function is_used() {
        return $this->is_used;
    }

    /**
     * @param boolean $is_used Whether this backup is used as a predecessor by a file or not.
     */
    public function set_is_used($is_used) {
        $this->is_used = $is_used;
    }


}
