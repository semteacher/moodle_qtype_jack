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
 * Question type class for the jack question type.
 *
 * @package    qtype_jack
 * @subpackage jack
 * @copyright  2005 Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot.'/question/type/jack/lib.php');

/**
 * The jack question type.
 *
 * @copyright  2005 Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_jack extends question_type {
    /**
     * Is manual graded
     *
     * @return boolean
     */
    public function is_manual_graded() {
        return true;
    }

    /**
     * Response file areas
     *
     * @return array
     */
    public function response_file_areas() {
        return array('attachments', 'answer');
    }

    /**
     * Get question options
     *
     * @param object $question
     * @return void
     */
    public function get_question_options($question) {
        global $DB;
        $question->options = $DB->get_record('qtype_jack_options',
                array('questionid' => $question->id), '*', MUST_EXIST);
        parent::get_question_options($question);

        // Get jack options.
        $jackoptions = $DB->get_record('question_jack', array('questionid' => $question->id));
        $question->testdriver = $jackoptions->testdriver;
        $question->ruleset = $jackoptions->ruleset;
    }

    /**
     * Save question options
     *
     * @param object $formdata
     * @return void
     */
    public function save_question_options($formdata) {
        global $DB, $CFG, $PAGE;
        $context = $formdata->context;

        $options = $DB->get_record('qtype_jack_options', array('questionid' => $formdata->id));
        if (!$options) {
            $options = new stdClass();
            $options->questionid = $formdata->id;
            $options->id = $DB->insert_record('qtype_jack_options', $options);
        }

        $options->attachments = $formdata->attachments;
        if ($options->attachments) {
            $options->attachmentsrequired = 1;
            $options->responseformat = 'noinline';
        } else {
            $options->responseformat = 'plain';
            $options->attachmentsrequired = 0;
        }
        $options->responserequired = 1;
        $options->responsefieldlines = $formdata->responsefieldlines;
        $options->filetypeslist = $formdata->filetypeslist;
        $options->graderinfo = $this->import_or_save_files($formdata->graderinfo,
                $context, 'qtype_jack', 'graderinfo', $formdata->id);
        $options->graderinfoformat = $formdata->graderinfo['format'];
        $options->responsetemplate = $formdata->responsetemplate;
        $options->responsetemplateformat = 0;

        if (!isset($formdata->lang)) {
            $formdata->lang = in_array($CFG->lang, SUPPORTED_LANGUAGES) ? $CFG->lang : 'en';
        }

        $options->lang = $formdata->lang;
        $DB->update_record('qtype_jack_options', $options);

        // On saving, first we need to make sure there is only one file.

        // Add files.
        if (!empty($formdata->responsefiletemplate)) {

            $fs = get_file_storage();
            $storedfiles = $fs->get_area_files(
                $context->id,
                'qtype_jack',
                'responsefiletemplate',
                $formdata->id);

            foreach ($storedfiles as $storedfile) {
                $storedfile->delete();
            }

            file_save_draft_area_files(
                $formdata->responsefiletemplate,
                $context->id,
                'qtype_jack',
                'responsefiletemplate',
                $formdata->id);
        }

        // Add jack options.
        $jackoptions = $DB->get_record('question_jack', array('questionid' => $formdata->id));
        if (empty($jackoptions)) {
            $jackoptions = new stdClass();
            $jackoptions->questionid = $formdata->id;
            $jackoptions->timecreated = time();
            $jackoptions->id = $DB->insert_record('question_jack', $jackoptions);
        }
        $jackoptions->testdriver = $formdata->testdriver;
        $jackoptions->ruleset = $formdata->ruleset;

        $DB->update_record('question_jack', $jackoptions);
    }

    /**
     * Initialis question instance
     *
     * @param question_definition $question
     * @param object $questiondata
     * @return void
     */
    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $question->responseformat = $questiondata->options->responseformat;
        $question->responserequired = $questiondata->options->responserequired;
        $question->responsefieldlines = $questiondata->options->responsefieldlines;
        $question->attachments = $questiondata->options->attachments;
        $question->attachmentsrequired = $questiondata->options->attachmentsrequired;
        $question->graderinfo = $questiondata->options->graderinfo;
        $question->graderinfoformat = $questiondata->options->graderinfoformat;
        $question->responsetemplate = $questiondata->options->responsetemplate;
        $question->responsetemplateformat = $questiondata->options->responsetemplateformat;
        $question->lang = $questiondata->options->lang;
        $filetypesutil = new \core_form\filetypes_util();
    }

    /**
     * Delete question
     *
     * @param mixed $questionid
     * @param int $contextid
     * @return void
     */
    public function delete_question($questionid, $contextid) {
        global $DB;

        $DB->delete_records('qtype_jack_options', array('questionid' => $questionid));
        $DB->delete_records('question_jack', array('questionid' => $questionid));
        parent::delete_question($questionid, $contextid);
    }

     /**
      * Response formates
      *
      * @return array the different response formats that the question type supports.
      * internal name => human-readable name.
      * @throws coding_exception
      */
    public function response_formats() {
        return array(
            'editor' => get_string('formateditor', 'qtype_jack'),
            'editorfilepicker' => get_string('formateditorfilepicker', 'qtype_jack'),
            'plain' => get_string('formatplain', 'qtype_jack'),
            'monospaced' => get_string('formatmonospaced', 'qtype_jack'),
            'noinline' => get_string('formatnoinline', 'qtype_jack'),
        );
    }

    /**
     * Response required options
     *
     * @return array the choices that should be offerd when asking if a response is required
     * @throws coding_exception
     */
    public function response_required_options() {
        return array(
            1 => get_string('responseisrequired', 'qtype_jack'),
            0 => get_string('responsenotrequired', 'qtype_jack'),
        );
    }

    /**
     * Response sizes
     *
     * @return array the choices that should be offered for the input box size.
     * @throws coding_exception
     */
    public function response_sizes() {
        $choices = array();
        for ($lines = 5; $lines <= 40; $lines += 5) {
            $choices[$lines] = get_string('nlines', 'qtype_jack', $lines);
        }
        return $choices;
    }

    /**
     * Attachment options
     *
     * @return array the choices that should be offered for the number of attachments.
     * @throws coding_exception
     */
    public function attachment_options() {
        return array(
            0 => get_string('no'),
            1 => '1',
            2 => '2',
            3 => '3',
            -1 => get_string('unlimited')
        );
    }

    /**
     * Attachments required options
     *
     * @return array the choices that should be offered for the number of required attachments.
     * @throws coding_exception
     */
    public function attachments_required_options() {
        return array(
            0 => get_string('attachmentsoptional', 'qtype_jack'),
            1 => '1',
            2 => '2',
            3 => '3'
        );
    }

    /**
     * Lists filetype options
     *
     * @return array
     */
    public function filetypeslist_options() {
        return array(
            0 => 'Java (.jar, .java)'
        );
    }

    /**
     * Moves files
     *
     * @param int $questionid
     * @param int $oldcontextid
     * @param int $newcontextid
     * @return void
     */
    public function move_files($questionid, $oldcontextid, $newcontextid) {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $fs = get_file_storage();
        $fs->move_area_files_to_new_context($oldcontextid,
                $newcontextid, 'qtype_jack', 'graderinfo', $questionid);
        $fs->move_area_files_to_new_context($oldcontextid,
                $newcontextid, 'qtype_jack', 'responsefiletemplate', $questionid);
    }

    /**
     * Delete files
     *
     * @param int $questionid
     * @param int $contextid
     * @return void
     */
    protected function delete_files($questionid, $contextid) {
        parent::delete_files($questionid, $contextid);
        $fs = get_file_storage();
        $fs->delete_area_files($contextid, 'qtype_jack', 'graderinfo', $questionid);
        $fs->delete_area_files($contextid, 'qtype_jack', 'responsefiletemplate', $questionid);
    }

     /**
      * Imports question from the Moodle XML format
      *
      * Imports question using information from extra_question_fields function
      * If some of you fields contains id's you'll need to reimplement this
      *
      * @param mixed $data
      * @param mixed $question
      * @param qformat_xml $format
      * @param mixed $extra
      * @return bool|object
      */
    public function import_from_xml($data, $question, qformat_xml $format, $extra=null) {
        $questiontype = $data['@']['type'];
        if ($questiontype != $this->name()) {
            return false;
        }

        $extraquestionfields = $this->extra_question_fields();
        if (!is_array($extraquestionfields)) {
            return false;
        }

        // Omit table name.
        array_shift($extraquestionfields);
        $qo = $format->import_headers($data);
        $qo->qtype = $questiontype;

        foreach ($extraquestionfields as $field) {
            $qo->$field = $format->getpath($data, array('#', $field, 0, '#'), '');
        }

        // Handle graderinfo editor field.
        $qo->graderinfo = array('text' => $qo->graderinfo);
        $qo->graderinfo['format'] = $qo->graderinfoformat;

        // Add jack options.
        $extrajackquestionfields = $this->extra_jack_question_fields();
        if (!is_array($extrajackquestionfields)) {
            return false;
        }
        // Omit table name.
        array_shift($extrajackquestionfields);
        foreach ($extrajackquestionfields as $field) {
            $qo->$field = $format->getpath($data, array('#', $field, 0, '#'), '');
        }

        $filexml = $format->getpath($data, array('#', 'file'), array());
        $qo->responsefiletemplate = $format->import_files_as_draft($filexml);

        return $qo;
    }

     /**
      * Export question to the Moodle XML format
      *
      * Export question using information from extra_question_fields function
      * If some of you fields contains id's you'll need to reimplement this
      *
      * @param mixed $question
      * @param qformat_xml $format
      * @param mixed $extra
      * @return bool|string
      */
    public function export_to_xml($question, qformat_xml $format, $extra=null) {

        $fs = get_file_storage();
        $contextid = $question->contextid;

        $extraquestionfields = $this->extra_question_fields();
        if (!is_array($extraquestionfields)) {
            return false;
        }

        // Omit table name.
        array_shift($extraquestionfields);
        $expout = '';
        foreach ($extraquestionfields as $field) {
            $exportedvalue = $format->xml_escape($question->options->$field);
            $expout .= "    <{$field}>{$exportedvalue}</{$field}>\n";
        }

        // Add jack options.
        $extrajackquestionfields = $this->extra_jack_question_fields();
        if (!is_array($extrajackquestionfields)) {
            return false;
        }
        // Omit table name.
        array_shift($extrajackquestionfields);
        foreach ($extrajackquestionfields as $field) {
            $exportedvalue = $format->xml_escape($question->$field);
            $expout .= "    <{$field}>{$exportedvalue}</{$field}>\n";
        }

        $extraanswersfields = $this->extra_answer_fields();
        if (is_array($extraanswersfields)) {
            array_shift($extraanswersfields);
        }
        foreach ($question->options->answers as $answer) {
            $extra = '';
            if (is_array($extraanswersfields)) {
                foreach ($extraanswersfields as $field) {
                    $exportedvalue = $format->xml_escape($answer->$field);
                    $extra .= "      <{$field}>{$exportedvalue}</{$field}>\n";
                }
            }

            $expout .= $format->write_answer($answer, $extra);
        }

        $files = $fs->get_area_files($contextid, 'qtype_jack', 'responsefiletemplate', $question->id);
        $expout .= "    ".$this->write_files($files, 2)."\n";;

        return $expout;
    }

    /**
     * Extra question fields
     *
     * If your question type has a table that extends the question table, and
     * you want the base class to automatically save, backup and restore the extra fields,
     * override this method to return an array wherer the first element is the table name,
     * and the subsequent entries are the column names (apart from id and questionid).
     *
     * @return mixed array as above, or null to tell the base class to do nothing.
     */
    public function extra_question_fields() {
        return array('qtype_jack_options',
            'responseformat',
            'responserequired',
            'responsefieldlines',
            'attachments',
            'attachmentsrequired',
            'graderinfo',
            'graderinfoformat',
            'responsetemplate',
            'responsetemplateformat',
            'filetypeslist',
            'lang',
        );
    }

    /**
     * Extra jack question fields
     *
     * If your question type has a table that extends the question table, and
     * you want the base class to automatically save, backup and restore the extra fields,
     * override this method to return an array wherer the first element is the table name,
     * and the subsequent entries are the column names (apart from id and questionid).
     *
     * @return mixed array as above, or null to tell the base class to do nothing.
     */
    public function extra_jack_question_fields() {
        return array('question_jack',
            'testdriver',
            'ruleset',
        );
    }

    /**
     * Convert files into text output in the given format.
     * This method is copied from qformat_default as a quick fix, as the method there is
     * protected.
     * @param array $files
     * @param int $indent Number of spaces to indent
     * @return string $string
     */
    public function write_files($files, $indent) {
        if (empty($files)) {
            return '';
        }
        $string = '';
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $string .= str_repeat('  ', $indent);
            $string .= '<file name="' . $file->get_filename() . '" encoding="base64">';
            $string .= base64_encode($file->get_content());
            $string .= "</file>\n";
        }
        return $string;
    }

}
