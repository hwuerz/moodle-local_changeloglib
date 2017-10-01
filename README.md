Changeloglib Plugin for Moodle
==============================

This plugin provides helper methods to handle the backup process of files which are required
for a changelog generation. 
This plugin will analyse the files and detect changes. 
Currently all file types are supported for backup and PDF documents can be used for difference
detection. 

Without any other plugins this will not do anything.

This plugin is required by [Upload Notification](https://github.com/hwuerz/moodle-local_uploadnotification) and [Assign Submission Changes](https://github.com/hwuerz/moodle-assignsubmission_changes)

License
-------

    Copyright (c) 2017 Hendrik Wuerz

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

Requirements
------------
* Tested with Moodle 3.3+
* For the diff detection, the command line tools [poppler-utils](https://wiki.ubuntuusers.de/poppler-utils/) and [diff](https://wiki.ubuntuusers.de/diff/) are required.

Installation
--------

1. Install the package [poppler-utils](https://wiki.ubuntuusers.de/poppler-utils/) for difference detection. This step is optional but recommended. If you skip the installation, the difference detection will not be available.
    ```bash
    sudo apt install -y poppler-utils
    pdftotext -h
    ```
    The last command should print the usage information for pdftotext. Please ensure that the installation was successful. 
2. Ensure that the tool [diff](https://wiki.ubuntuusers.de/diff/) is installed on your system. It should be included in every Ubuntu or Linux distribution. The following command should print some help text. If it is not available please install it following the documentation for your system. This tool is optional but recommended. If it is not installed, the difference detection is not possible.
    ```bash
    diff --help
    ```
3. Clone the repo inside MOODLE_HOME/local/changeloglib
   ```bash
   cd path/to/moodle/home
   git clone git@github.com:hwuerz/moodle-local_changeloglib.git local/changeloglib
   ```
4. Browse to `Site Administration` -> `Notifications` and allow the database upgrades to execute

Tests
------

This plugin provides tests for the main functions. To run them please follow the next steps:

1. Install PHPUnit on your system and configure moodle. See [https://docs.moodle.org/dev/PHPUnit](https://docs.moodle.org/dev/PHPUnit) for more information.
2. Install the plugin.
3. Run the tests
    ```bash
    cd /path/to/moodle/home
    php admin/tool/phpunit/cli/init.php
    vendor/bin/phpunit --group local_changeloglib
    ``` 

Features
--------

### Backup-Lib
To detect updates, the plugin must backup old materials. These backups can later be used to find a predecessor of a new file. As soon as a file becomes deleted, you should call the backup function which will create an own copy of the document. Because of the intelligent file system API of moodle, no hard disc copy will be performed. For every backup only one database record will be created.

Use the class `local_changeloglib_backup_lib` to manage your backups. It has six public functions:
* Backup a file before it will be deleted: `backup(array $data, $context_id_from, $component_from, $filearea_from, $itemid_from = 0, $context_id_to, $scope_id): void`
   * `@param array $data` Additional data which is used to identify a definite predecessor. Use this to exclude all other candidates when you can ensure fitting IDs.
   * `@param int $context_id_from` The context ID of the file which should be backuped
   * `@param string $component_from` The component of the file which should be backuped
   * `@param string $filearea_from` The filearea of the file which should be backuped
   * `@param int $itemid_from` The itemid of the file which should be backuped
   * `@param int $context_id_to` The new context under which the backup should be stored
   * `@param int $scope_id` The scope within the context. (For resources this is the section within the course) The scope of a predecessor must be identically to the new file.
* Get the stored_file instance of a backup: `get_backup_file($backup): null|stored_file`
   * `@param object $backup` The database backup record.
   * `@return stored_file|null` The file instances of the backup or null if no file exists.
* Clean up backups which are nor longer required. The plugin removes old files automatically, but maybe you want to remove some files manually.
   * `clean_up_old_files(): void` Removes backups which are older than one hour. This function is automatically called. You can invoke it manually but this should not be needed.
   * `clean_up_all_files(): void` Deletes all backup files.
   * `clean_up_selected($context, $scope): void` Deletes all backup files with the passed context and scope. `$context` and `$scope` are integers with the IDs.
   * `clean_up_id($backup_id): void` Delete the backup with the passed ID (integer). This ID is the primary key in the database and the itemid of the file instance.
   
### Update detector
The update detector `local_changeloglib_update_detector` checks whether one file is an update of another. Use it to find the most probable predecessor of a new file.
* `__construct(stored_file $new_file, array $new_data, $context, $scope, array $further_candidates)`
   * `@param stored_file $new_file` The new file whose predecessor should be found.
   * `@param array $new_data` The data array of the new file with constraints for a definite predecessor.
   * `@param int $context` The context of the file and its predecessor. (Resources: The course context)
   * `@param int $scope` The scope of the file and its predecessor within the context. (In courses: the section)
   * `@param stored_file[] $further_candidates` All other files which should be checked as predecessor. Use this if candidates exists which are not in the backup table.
* `set_ensure_mime_type($ensure_mime_type): void` Whether this detector should ensure that the MIME type of the found predecessor matches the MIME type of the new file. If this value is true, all candidates with not matching type will be skipped. If this value is false, the MIME Type will be handled as a similarity property. Default value is true.
   * `@param bool $ensure_mime_type` Whether the MIME type of the candidates must match or not.
* `set_min_similarity($min_similarity): void` If the similarity is below this value, a file will be rejected as a predecessor. Adjust this value to define a level of similarity you want to ensure. Default is 0.5
   * `@param float $min_similarity` The minimum similarity a predecessor must have in the range \[0, 1\].
* `is_update(): bool|stored_file` Check whether the new file is an update of an older version.
   * `@return bool|stored_file` False if this is not an update of an earlier file. The previous version of this file if found.
* `get_new_file(): stored_file` Get the new file which was passed in the constructor.

### PDF to text
The class `local_changeloglib_pdftotext` is a wrapper to access the command line tool pdftotext from [poppler-utils](https://wiki.ubuntuusers.de/poppler-utils/).
* `is_installed(): bool` Checks whether the command line tool pdftotext is installed. This tool is required to convert a pdf file to a text document for diff detection.
* `convert_to_txt(stored_file $file): bool|string` Tries to convert the passed file in a text document. Conversion is only possible if pdftotext is available and a PDF document was passed.
   * `@param stored_file $file` The file which should be converted.
   * `@return bool|string` Returns false if file could not be converted or a string with the path of the generated text file.

### Diff detector
The diff detector `local_changeloglib_diff_detector` can identify the pages on which changes were performed. It requires the command line tool [diff](https://wiki.ubuntuusers.de/diff/) and analyses text documents. 
* `__construct($first_file, $second_file)` Creates a new detector for the two files.
   * `@param string $first_file` The filename and path for the first text document.
   * `@param string $second_file` The filename and path for the second text document.
* `get_info(): string` Get a string with all pages containing changes, separated by comma.
   * `@return string` A string which can be printed to the user.
* `has_acceptable_amount_of_changes(): bool` Checks whether there are more changes than allowed for a predecessor. Allowed means: Not more than half of the pages contains changes and not more than half of the lines in the documents are changed.
