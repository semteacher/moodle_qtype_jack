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
 * jack question renderer class.
 *
 * @package    qtype_jack
 * @subpackage jack
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/questionbase.php');

/**
 * Generates the output for jack questions.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_jack_renderer extends qtype_renderer {
    /**
     * Undocumented function
     *
     * @param question_attempt $qa
     * @param question_display_options $options
     * @return string
     */
    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {

        /** @var qtype_jack_question $question */
        $question = $qa->get_question();
        $responseoutput = $question->get_format_renderer($this->page);

        // Answer field.
        $step = $qa->get_last_step_with_qt_var('answer');

        if (!$step->has_qt_var('answer') && empty($options->readonly)) {
            // Question has never been answered, fill it with response template.
            $step = new question_attempt_step(array('answer' => $question->responsetemplate));
        }

        if (empty($options->readonly)) {
            $answer = $responseoutput->response_area_input('answer', $qa,
                    $step, $question->responsefieldlines, $options->context);

        } else {
            $answer = $responseoutput->response_area_read_only('answer', $qa,
                    $step, $question->responsefieldlines, $options->context);
        }

        $files = '';
        if ($question->attachments) {
            if (empty($options->readonly)) {
                $files = $this->files_input($qa, $question->attachments, $options);

            } else {
                $files = $this->files_read_only($qa, $options);
            }
        }

        // The context for the files which are transmitted is the one of the module.
        // But we need for the template the context of the question itself.

        $fs = get_file_storage();
        if ($storedfiles = $fs->get_area_files(
            $question->contextid,
            'qtype_jack',
            'responsefiletemplate',
            $question->id)) {

            $storedfile = array_pop($storedfiles);

            $url = moodle_url::make_pluginfile_url(
                $storedfile->get_contextid(),
                $storedfile->get_component(),
                $storedfile->get_filearea(),
                $storedfile->get_itemid(),
                $storedfile->get_filepath(),
                $storedfile->get_filename(),
                false
            );
            $renderurl = html_writer::tag('div', get_string('workwithsourcecodetemplatefile', 'qtype_jack'));
            $renderurl .= html_writer::link($url->out(), $storedfile->get_filename());
        }

        $result = '';
        $result .= html_writer::tag('div', $question->format_questiontext($qa),
                array('class' => 'qtext'));
        $result .= $renderurl ?? '';
        $result .= html_writer::start_tag('div', array('class' => 'ablock'));
        $result .= html_writer::tag('div', $answer, array('class' => 'answer'));
        $result .= html_writer::tag('div', $files, array('class' => 'attachments'));
        $result .= html_writer::end_tag('div');

        return $result;
    }

    /**
     * Displays any attached files when the question is in read-only mode.
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should
     *      not be displayed. Used to get the context.
     */
    public function files_read_only(question_attempt $qa, question_display_options $options) {
        $files = $qa->get_last_qt_files('attachments', $options->context->id);
        $output = array();

        foreach ($files as $file) {
            $output[] = html_writer::tag('p', html_writer::link($qa->get_response_file_url($file),
                    $this->output->pix_icon(file_file_icon($file), get_mimetype_description($file),
                    'moodle', array('class' => 'icon')) . ' ' . s($file->get_filename())));
        }
        return implode($output);
    }

    /**
     * Displays the input control for when the student should upload a single file.
     * @param question_attempt $qa the question attempt to display.
     * @param int $numallowed the maximum number of attachments allowed. -1 = unlimited.
     * @param question_display_options $options controls what should and should
     *      not be displayed. Used to get the context.
     */
    public function files_input(question_attempt $qa, $numallowed,
            question_display_options $options) {
        global $CFG;
        require_once($CFG->dirroot . '/lib/form/filemanager.php');

        $pickeroptions = new stdClass();
        $pickeroptions->mainfile = null;
        $pickeroptions->maxfiles = $numallowed;
        $pickeroptions->itemid = $qa->prepare_response_files_draft_itemid(
                'attachments', $options->context->id);
        $pickeroptions->context = $options->context;
        $pickeroptions->return_types = FILE_INTERNAL | FILE_CONTROLLED_LINK;

        $pickeroptions->itemid = $qa->prepare_response_files_draft_itemid(
                'attachments', $options->context->id);

        $pickeroptions->accepted_types = $qa->get_question()->get_filetypeslist();

        $fm = new form_filemanager($pickeroptions);
        $filesrenderer = $this->page->get_renderer('core', 'files');

        $text = '';
        if (!empty($qa->get_question()->get_filetypeslist())) {
            $text = html_writer::tag('p', get_string('acceptedfiletypes', 'qtype_jack'));
            $filetypesutil = new \core_form\filetypes_util();
            $filetypes = $qa->get_question()->get_filetypeslist();
            $filetypedescriptions = $filetypesutil->describe_file_types($filetypes);
            $text .= $this->render_from_template('core_form/filetypes-descriptions', $filetypedescriptions);
        }
        return $filesrenderer->render($fm). html_writer::empty_tag(
                'input', array('type' => 'hidden', 'name' => $qa->get_qt_field_name('attachments'),
                'value' => $pickeroptions->itemid)) . $text;
    }

    /**
     * Manual comment
     *
     * @param question_attempt $qa
     * @param question_display_options $options
     * @return string
     */
    public function manual_comment(question_attempt $qa, question_display_options $options) {
        if ($options->manualcomment != question_display_options::EDITABLE) {
            return '';
        }

        $question = $qa->get_question();
        return html_writer::nonempty_tag('div', $question->format_text(
                $question->graderinfo, $question->graderinfo, $qa, 'qtype_jack',
                'graderinfo', $question->id), array('class' => 'graderinfo'));
    }
}


/**
 * A base class to abstract out the differences between different type of response format.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class qtype_jack_format_renderer_base extends plugin_renderer_base {
    /**
     * Render the students respone when the question is in read-only mode.
     * @param string $name the variable name this input edits.
     * @param question_attempt $qa the question attempt being display.
     * @param question_attempt_step $step the current step.
     * @param int $lines approximate size of input box to display.
     * @param object $context the context teh output belongs to.
     * @return string html to display the response.
     */
    abstract public function response_area_read_only($name, question_attempt $qa,
            question_attempt_step $step, $lines, $context);

    /**
     * Render the students respone when the question is in read-only mode.
     * @param string $name the variable name this input edits.
     * @param question_attempt $qa the question attempt being display.
     * @param question_attempt_step $step the current step.
     * @param int $lines approximate size of input box to display.
     * @param object $context the context teh output belongs to.
     * @return string html to display the response for editing.
     */
    abstract public function response_area_input($name, question_attempt $qa,
            question_attempt_step $step, $lines, $context);

    /**
     * Class name
     *
     * @return string specific class name to add to the input element.
     */
    abstract protected function class_name();
}

/**
 * An jack format renderer for jacks where the student should not enter any inline response.
 *
 * @copyright  2013 Binghamton University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_jack_format_noinline_renderer extends plugin_renderer_base {

    /**
     * Class name
     *
     * @return string
     */
    protected function class_name() {
        return 'qtype_jack_noinline';
    }

    /**
     * Response area read only
     *
     * @param mixed $name
     * @param mixed $qa
     * @param mixed $step
     * @param mixed $lines
     * @param mixed $context
     * @return string
     */
    public function response_area_read_only($name, $qa, $step, $lines, $context) {
        return '';
    }

    /**
     * Response area input
     *
     * @param mixed $name
     * @param mixed $qa
     * @param mixed $step
     * @param mixed $lines
     * @param mixed $context
     * @return string
     */
    public function response_area_input($name, $qa, $step, $lines, $context) {
        return '';
    }

}

/**
 * An jack format renderer for jacks where the student should use the HTML editor without the file picker.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_jack_format_editor_renderer extends plugin_renderer_base {

    /**
     * Return the class name of this class.
     *
     * @return string class name
     */
    protected function class_name(): string {
        return 'qtype_jack_editor';
    }

    /**
     * Response area read only
     *
     * @param mixed $name
     * @param mixed $qa
     * @param mixed $step
     * @param mixed $lines
     * @param mixed $context
     * @return object
     */
    public function response_area_read_only($name, $qa, $step, $lines, $context) {
        return html_writer::tag('div', $this->prepare_response($name, $qa, $step, $context),
                ['class' => $this->class_name() . ' qtype_jack_response readonly',
                        'style' => 'min-height: ' . ($lines * 1.5) . 'em;']);
        // Height $lines * 1.5 because that is a typical line-height on web pages.
        // That seems to give results that look OK.
    }

    /**
     * Response area input
     *
     * @param mixed $name
     * @param mixed $qa
     * @param mixed $step
     * @param mixed $lines
     * @param mixed $context
     * @return string
     */
    public function response_area_input($name, $qa, $step, $lines, $context) {
        global $CFG;
        require_once($CFG->dirroot . '/repository/lib.php');

        $inputname = $qa->get_qt_field_name($name);
        $responseformat = $step->get_qt_var($name . 'format');
        $id = $inputname . '_id';

        $editor = editors_get_preferred_editor($responseformat);
        $strformats = format_text_menu();
        $formats = $editor->get_supported_formats();
        foreach ($formats as $fid) {
            $formats[$fid] = $strformats[$fid];
        }

        list($draftitemid, $response) = $this->prepare_response_for_editing(
                $name, $step, $context);

        $editor->set_text($response);
        $editor->use_editor($id, $this->get_editor_options($context),
                $this->get_filepicker_options($context, $draftitemid));

        $output = '';
        $output .= html_writer::start_tag('div', array('class' =>
                $this->class_name() . ' qtype_jack_response'));

        $output .= html_writer::tag('div', html_writer::tag('textarea', s($response),
                array('id' => $id, 'name' => $inputname, 'rows' => $lines, 'cols' => 60)));

        $output .= html_writer::start_tag('div');
        if (count($formats) == 1) {
            reset($formats);
            $output .= html_writer::empty_tag('input', array('type' => 'hidden',
                    'name' => $inputname . 'format', 'value' => key($formats)));

        } else {
            $output .= html_writer::label(get_string('format'), 'menu' . $inputname . 'format', false);
            $output .= ' ';
            $output .= html_writer::select($formats, $inputname . 'format', $responseformat, '');
        }
        $output .= html_writer::end_tag('div');

        $output .= $this->filepicker_html($inputname, $draftitemid);

        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     * Prepare the response for read-only display.
     *
     * @param string $name the variable name this input edits.
     * @param question_attempt $qa the question attempt being display.
     * @param question_attempt_step $step the current step.
     * @param object $context the context the attempt belongs to.
     * @return string the response prepared for display.
     */
    protected function prepare_response($name, question_attempt $qa,
            question_attempt_step $step, $context) {
        if (!$step->has_qt_var($name)) {
            return '';
        }

        $formatoptions = new stdClass();
        $formatoptions->para = false;
        return format_text($step->get_qt_var($name), $step->get_qt_var($name . 'format'),
                $formatoptions);
    }

    /**
     * Prepare the response for editing.
     *
     * @param string $name the variable name this input edits.
     * @param question_attempt_step $step the current step.
     * @param object $context the context the attempt belongs to.
     * @return string the response prepared for display.
     */
    protected function prepare_response_for_editing($name,
            question_attempt_step $step, $context) {
        return array(0, $step->get_qt_var($name));
    }

    /**
     * Get editor options
     *
     * @param object $context the context the attempt belongs to.
     * @return array options for the editor.
     */
    protected function get_editor_options($context) {
        // Disable the text-editor autosave because quiz has it's own auto save function.
        return array('context' => $context, 'autosave' => false);
    }

    /**
     * Get filepicker options
     *
     * @param object $context the context the attempt belongs to.
     * @param int $draftitemid draft item id.
     * @return array filepicker options for the editor.
     */
    protected function get_filepicker_options($context, $draftitemid) {
        return array('return_types'  => FILE_INTERNAL | FILE_EXTERNAL);
    }

    /**
     * Filepicker HTML
     *
     * @param string $inputname input field name.
     * @param int $draftitemid draft file area itemid.
     * @return string HTML for the filepicker, if used.
     */
    protected function filepicker_html($inputname, $draftitemid) {
        return '';
    }
}


/**
 * An jack format renderer for jacks where the student should use the HTML editor with the file picker.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_jack_format_editorfilepicker_renderer extends qtype_jack_format_editor_renderer {

    /**
     * Class name
     *
     * @return string
     */
    protected function class_name(): string {
        return 'qtype_jack_editorfilepicker';
    }

    /**
     * Undocumented function
     *
     * @param string $name
     * @param question_attempt $qa
     * @param question_attempt_step $step
     * @param object $context
     * @return string
     */
    protected function prepare_response($name, question_attempt $qa,
            question_attempt_step $step, $context) {
        if (!$step->has_qt_var($name)) {
            return '';
        }

        $formatoptions = new stdClass();
        $formatoptions->para = false;
        $text = $qa->rewrite_response_pluginfile_urls($step->get_qt_var($name),
                $context->id, 'answer', $step);
        return format_text($text, $step->get_qt_var($name . 'format'), $formatoptions);
    }

    /**
     * Prepare response for editing
     *
     * @param string $name
     * @param question_attempt_step $step
     * @param object $context
     * @return question_attempt_step
     */
    protected function prepare_response_for_editing($name,
            question_attempt_step $step, $context) {
        return $step->prepare_response_files_draft_itemid_with_text(
                $name, $context->id, $step->get_qt_var($name));
    }

    /**
     * Get editor options for question response text area.
     *
     * @param object $context the context the attempt belongs to.
     * @return array options for the editor.
     */
    protected function get_editor_options($context) {
        return question_utils::get_editor_options($context);
    }

    /**
     * Get the options required to configure the filepicker for one of the editor toolbar buttons.
     *
     * @deprecated since 3.5
     * @param mixed $acceptedtypes array of types of '*'.
     * @param int $draftitemid the draft area item id.
     * @param object $context the context.
     * @return array the required options.
     */
    protected function specific_filepicker_options($acceptedtypes, $draftitemid, $context) {
        debugging('qtype_jack_format_editorfilepicker_renderer::specific_filepicker_options() is deprecated, ' .
            'use question_utils::specific_filepicker_options() instead.', DEBUG_DEVELOPER);

        $filepickeroptions = new stdClass();
        $filepickeroptions->accepted_types = $acceptedtypes;
        $filepickeroptions->return_types = FILE_INTERNAL | FILE_EXTERNAL;
        $filepickeroptions->context = $context;
        $filepickeroptions->env = 'filepicker';

        $options = initialise_filepicker($filepickeroptions);
        $options->context = $context;
        $options->client_id = uniqid();
        $options->env = 'editor';
        $options->itemid = $draftitemid;

        return $options;
    }

    /**
     * Get filepicker options
     *
     * @param object $context the context the attempt belongs to.
     * @param int $draftitemid draft item id.
     * @return array filepicker options for the editor.
     */
    protected function get_filepicker_options($context, $draftitemid) {
        return question_utils::get_filepicker_options($context, $draftitemid);
    }

    /**
     * Filepicker html
     *
     * @param string $inputname
     * @param int $draftitemid
     * @return string
     */
    protected function filepicker_html($inputname, $draftitemid) {
        $nonjspickerurl = new moodle_url('/repository/draftfiles_manager.php', array(
            'action' => 'browse',
            'env' => 'editor',
            'itemid' => $draftitemid,
            'subdirs' => false,
            'maxfiles' => -1,
            'sesskey' => sesskey(),
        ));

        return html_writer::empty_tag('input', array('type' => 'hidden',
                'name' => $inputname . ':itemid', 'value' => $draftitemid)) .
                html_writer::tag('noscript', html_writer::tag('div',
                    html_writer::tag('object', '', array('type' => 'text/html',
                        'data' => $nonjspickerurl, 'height' => 160, 'width' => 600,
                        'style' => 'border: 1px solid #000;'))));
    }
}


/**
 * An jack format renderer for jacks where the student should use a plain input box, but with a normal, proportional font.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_jack_format_plain_renderer extends plugin_renderer_base {
    /**
     * Get text of textarea
     *
     * @param string $response
     * @param int $lines
     * @param array $attributes
     * @return string the HTML for the textarea.oid
     */
    protected function textarea($response, $lines, $attributes) {
        $attributes['class'] = $this->class_name() . ' qtype_jack_response';
        $attributes['rows'] = $lines;
        $attributes['cols'] = 60;
        return html_writer::tag('textarea', s($response), $attributes);
    }


    /**
     * Class name
     *
     * @return string
     */
    protected function class_name() {
        return 'qtype_jack_plain';
    }

    /**
     * Response area read only
     *
     * @param mixed $name
     * @param mixed $qa
     * @param mixed $step
     * @param mixed $lines
     * @param mixed $context
     * @return string
     */
    public function response_area_read_only($name, $qa, $step, $lines, $context) {
        return $this->textarea($step->get_qt_var($name), $lines, array('readonly' => 'readonly'));
    }

    /**
     * Response area input
     *
     * @param mixed $name
     * @param mixed $qa
     * @param mixed $step
     * @param mixed $lines
     * @param mixed $context
     * @return void
     */
    public function response_area_input($name, $qa, $step, $lines, $context) {
        $inputname = $qa->get_qt_field_name($name);
        return $this->textarea($step->get_qt_var($name), $lines, array('name' => $inputname)) .
                html_writer::empty_tag('input', array('type' => 'hidden',
                    'name' => $inputname . 'format', 'value' => FORMAT_PLAIN));
    }
}


/**
 * An jack format renderer for jacks where the student should use a plain input box with a monospaced font.
 *
 * You might use this, for example, for a question where the students should type computer code.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_jack_format_monospaced_renderer extends qtype_jack_format_plain_renderer {

    /**
     * Return class name
     *
     * @return string
     */
    protected function class_name(): string {
        return 'qtype_jack_monospaced';
    }
}
