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
 * True-false question renderer class.
 *
 * @package    qtype
 * @subpackage truefalse
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Generates the output for true-false questions.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ubtruefalse_renderer extends qtype_renderer {

    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {

        global $PAGE, $CFG, $valor;

        $question = $qa->get_question();
        $response = $qa->get_last_qt_var('answer', '');

        $inputname = $qa->get_qt_field_name('answer');
        $trueattributes = array(
            'type' => 'radio',
            'name' => $inputname,
            'value' => 1,
            'id' => $inputname . 'true',
        );
        $falseattributes = array(
            'type' => 'radio',
            'name' => $inputname,
            'value' => 0,
            'id' => $inputname . 'false',
        );
        $emptyattributes = array(
            'type' => 'radio',
            'name' => $inputname,
            'value' => 2,
            'id' => $inputname . '_hiden',
        );
        $buttonclearattributes = array(
            'type' => 'button',
            'name' => $inputname,
            'id' => $inputname,
            'onclick' => "blankresponse('$inputname')",
        );

        if ($options->readonly) {
            $trueattributes['disabled'] = 'disabled';
            $falseattributes['disabled'] = 'disabled';
            $buttonclearattributes['disabled'] = 'disabled';
        }

        // Work out which radio button to select (if any)
        $truechecked = false;
        $falsechecked = false;
        $responsearray = array();

        if ($qa->get_step($qa->get_num_steps() - 1)->get_state() != 'todo' && $qa->get_step($qa->get_num_steps() - 1)->get_state() != 'gaveup') {
            if ($response == 1) {
                $trueattributes['checked'] = 'checked';
                $truechecked = true;
                $responsearray = array('answer' => 1);
            } else if ($response == 0 && $response !== '') {//else if ($response !== '')
                $falseattributes['checked'] = 'checked';
                $falsechecked = true;
                $responsearray = array('answer' => 1);
            } else {
                $truechecked = false;
                $falsechecked = false;
            }
        } else {
            $truechecked = false;
            $falsechecked = false;
        }

        // Work out visual feedback for answer correctness.
        $trueclass = '';
        $falseclass = '';
        $truefeedbackimg = '';
        $falsefeedbackimg = '';
        if ($options->correctness) {
            if ($truechecked) {
                $trueclass = ' ' . $this->feedback_class((int) $question->rightanswer);
                $truefeedbackimg = $this->feedback_image((int) $question->rightanswer);
            } else if ($falsechecked) {
                $falseclass = ' ' . $this->feedback_class((int) (!$question->rightanswer));
                $falsefeedbackimg = $this->feedback_image((int) (!$question->rightanswer));
            }
        }

        $radiotrue = html_writer::empty_tag('input', $trueattributes) .
                html_writer::tag('label', get_string('true', 'qtype_ubtruefalse'), array('for' => $trueattributes['id']));
        $radiofalse = html_writer::empty_tag('input', $falseattributes) .
                html_writer::tag('label', get_string('false', 'qtype_ubtruefalse'), array('for' => $falseattributes['id']));


        $result = '';
        $result .= html_writer::tag('div', $question->format_questiontext($qa), array('class' => 'qtext'));

        $result .= html_writer::start_tag('div', array('class' => 'ablock'));
        $result .= html_writer::tag('div', get_string('selectone', 'qtype_ubtruefalse'), array('class' => 'prompt'));

        $result .= html_writer::start_tag('div', array('class' => 'answer'));
        $result .= html_writer::tag('div', $radiotrue . ' ' . $truefeedbackimg, array('class' => 'r0' . $trueclass));

        $result .= html_writer::tag('div', $radiofalse . ' ' . $falsefeedbackimg, array('class' => 'r1' . $falseclass));


        /*
         * UB
         * Modificación, ponemos en blanco radiobuttons
         * Javier Flaqué - 14/03/2013
         */

        $PAGE->requires->js(new moodle_url($CFG->wwwroot . '/question/type/ubtruefalse/emptyRadio.js'));
        $result .= html_writer::end_tag('br');
        $result .= html_writer::start_tag('button', $buttonclearattributes);
        $result .= get_string('noresponse', 'quiz');
        $result .= html_writer::end_tag('button');

        /* Fin modificación */


        $result .= html_writer::end_tag('div'); // answer

        $result .= html_writer::end_tag('div'); // ablock

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div', $question->get_validation_error($responsearray), array('class' => 'validationerror'));
        }

        return $result;
    }

    public function specific_feedback(question_attempt $qa) {
        $question = $qa->get_question();
        $response = $qa->get_last_qt_var('answer', '');

        if ($response) {
            return $question->format_text($question->truefeedback, $question->truefeedbackformat, $qa, 'question', 'answerfeedback', $question->trueanswerid);
        } else if ($response !== '') {
            return $question->format_text($question->falsefeedback, $question->falsefeedbackformat, $qa, 'question', 'answerfeedback', $question->falseanswerid);
        }
    }

    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();

        if ($question->rightanswer) {
            return get_string('correctanswertrue', 'qtype_ubtruefalse');
        } else {
            return get_string('correctanswerfalse', 'qtype_ubtruefalse');
        }
    }

}
