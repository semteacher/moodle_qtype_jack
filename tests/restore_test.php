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
 * Test restore logic.
 *
 * @package    qtype_jack
 * @copyright  2019 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_jack;

use context_course;
use question_edit_contexts;
use restore_date_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . "/phpunit/classes/restore_date_testcase.php");

/**
 * Test restore logic.
 *
 * @copyright  2019 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \restore_qtype_jack_plugin
 */
class restore_test extends restore_date_testcase {

    /**
     * Test missing qtype_jack_options creation.
     *
     * Old backup files may contain jacks with no qtype_jack_options record.
     * During restore, we add default options for any questions like that.
     * That is what is tested in this file.
     */
    public function test_restore_create_missing_qtype_jack_options() {
        global $DB;

        // Create a course with one jack question in its question bank.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $contexts = new question_edit_contexts(context_course::instance($course->id));
        $category = question_make_default_categories($contexts->all());
        /** @var core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $jack = $questiongenerator->create_question('jack', null, array('category' => $category->id));

        // Remove the options record, which means that the backup will look like a backup made in an old Moodle.
        $DB->delete_records('qtype_jack_options', ['questionid' => $jack->id]);

        // Do backup and restore.
        $newcourseid = $this->backup_and_restore($course);

        // Verify that the restored question has options.
        $contexts = new question_edit_contexts(context_course::instance($newcourseid));
        $newcategory = question_make_default_categories($contexts->all());
        $newjack = $DB->get_record('question', ['category' => $newcategory->id, 'qtype' => 'jack']);
        $this->assertTrue($DB->record_exists('qtype_jack_options', ['questionid' => $newjack->id]));
    }
}
