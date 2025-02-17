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
 * Serve question type files
 *
 * @since      Moodle 2.0
 * @package    qtype_jack
 * @copyright  Dongsheng Cai <dongsheng@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('SUPPORTED_LANGUAGES', ['en', 'de']);

/**
 * Checks file access for jack questions.
 *
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool
 * @throws coding_exception
 * @package  qtype_jack
 * @category files
 */
function qtype_jack_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');

    try {
        question_pluginfile($course, $context, 'qtype_jack', $filearea, $args, $forcedownload, $options);

    } catch (Exception $e) {
        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'qtype_jack', $filearea, $args[0], '/', $args[1]);

        // Send the file, always forcing download, we don't want options.
        \core\session\manager::write_close();
        send_stored_file($file, 0, 0, true);
    }
}
