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
 * Question type class for the true-false question type.
 *
 * @package    qtype
 * @subpackage truefalse
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

/**
 * The true-false question type class.
 *
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ubtruefalse extends question_type {
    public function save_question_options($question) {
        global $DB;
        $result = new stdClass();
        $context = $question->context;

        // Fetch old answer ids so that we can reuse them
        $oldanswers = $DB->get_records('question_answers',
                array('question' => $question->id), 'id ASC');

        // Save the true answer - update an existing answer if possible.
        $answer = array_shift($oldanswers);
        if (!$answer) {
            $answer = new stdClass();
            $answer->question = $question->id;
            $answer->answer = '';
            $answer->feedback = '';
            $answer->id = $DB->insert_record('question_answers', $answer);
        }

        $answer->answer   = get_string('true', 'qtype_ubtruefalse');
        $answer->fraction = $question->correctanswer;
        $answer->feedback = $this->import_or_save_files($question->feedbacktrue,
                $context, 'question', 'answerfeedback', $answer->id);
        $answer->feedbackformat = $question->feedbacktrue['format'];
        $DB->update_record('question_answers', $answer);
        $trueid = $answer->id;

        // Save the false answer - update an existing answer if possible.
        $answer = array_shift($oldanswers);
        if (!$answer) {
            $answer = new stdClass();
            $answer->question = $question->id;
            $answer->answer = '';
            $answer->feedback = '';
            $answer->id = $DB->insert_record('question_answers', $answer);
        }

        $answer->answer   = get_string('false', 'qtype_ubtruefalse');
        $answer->fraction = 1 - (int)$question->correctanswer;
        $answer->feedback = $this->import_or_save_files($question->feedbackfalse,
                $context, 'question', 'answerfeedback', $answer->id);
        $answer->feedbackformat = $question->feedbackfalse['format'];
        $DB->update_record('question_answers', $answer);
        $falseid = $answer->id;

        // Delete any left over old answer records.
        $fs = get_file_storage();
        foreach ($oldanswers as $oldanswer) {
            $fs->delete_area_files($context->id, 'question', 'answerfeedback', $oldanswer->id);
            $DB->delete_records('question_answers', array('id' => $oldanswer->id));
        }

        // Save question options in question_ubtruefalse table
        if ($options = $DB->get_record('question_ubtruefalse', array('question' => $question->id))) {
            // No need to do anything, since the answer IDs won't have changed
            // But we'll do it anyway, just for robustness
            $options->trueanswer  = $trueid;
            $options->falseanswer = $falseid;
            $DB->update_record('question_ubtruefalse', $options);
        } else {
            $options = new stdClass();
            $options->question    = $question->id;
            $options->trueanswer  = $trueid;
            $options->falseanswer = $falseid;
            $DB->insert_record('question_ubtruefalse', $options);
        }

        $this->save_hints($question);

        return true;
    }

    /**
     * Loads the question type specific options for the question.
     */
    public function get_question_options($question) {
        global $DB, $OUTPUT;
        // Get additional information from database
        // and attach it to the question object
        if (!$question->options = $DB->get_record('question_ubtruefalse',
                array('question' => $question->id))) {
            echo $OUTPUT->notification('Error: Missing question options!');
            return false;
        }
        // Load the answers
        if (!$question->options->answers = $DB->get_records('question_answers',
                array('question' =>  $question->id), 'id ASC')) {
            echo $OUTPUT->notification('Error: Missing question answers for truefalse question ' .
                    $question->id . '!');
            return false;
        }

        return true;
    }

    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $answers = $questiondata->options->answers;
        if ($answers[$questiondata->options->trueanswer]->fraction > 0.99) {
            $question->rightanswer = true;
        } else {
            $question->rightanswer = false;
        }
        $question->truefeedback =  $answers[$questiondata->options->trueanswer]->feedback;
        $question->falsefeedback = $answers[$questiondata->options->falseanswer]->feedback;
        $question->truefeedbackformat =
                $answers[$questiondata->options->trueanswer]->feedbackformat;
        $question->falsefeedbackformat =
                $answers[$questiondata->options->falseanswer]->feedbackformat;
        $question->trueanswerid =  $questiondata->options->trueanswer;
        $question->falseanswerid = $questiondata->options->falseanswer;
    }

    public function export_to_xml($question, qformat_xml $format, $extra = null) {
        $output = '';

        $trueanswer = $question->options->answers[$question->options->trueanswer];
        $trueanswer->answer = 'true';
        $output .= $format->write_answer($trueanswer);

        $falseanswer = $question->options->answers[$question->options->falseanswer];
        $falseanswer->answer = 'false';
        $output .= $format->write_answer($falseanswer);

        return $output;
    }

     public function import_from_xml($data, $question, qformat_xml $format, $extra=null) {
        if (!isset($data['@']['type']) || $data['@']['type'] != 'ubtruefalse') {
            return false;
        }

        $question = $format->import_headers($data);

        // 'header' parts particular to ubtrue/ubfalse
        $question->qtype = 'ubtruefalse';

        // In the past, it used to be assumed that the two answers were in the file
        // true first, then false. Howevever that was not always true. Now, we
        // try to match on the answer text, but in old exports, this will be a localised
        // string, so if we don't find true or false, we fall back to the old system.
        $first = true;
        $warning = false;
        foreach ($data['#']['answer'] as $answer) {
            $answertext = $format->getpath($answer,
                    array('#', 'text', 0, '#'), '', true);
            $feedback = $format->import_text_with_files($answer,
                    array('#', 'feedback', 0), '', $format->get_format($question->questiontextformat));

            if ($answertext != 'true' && $answertext != 'false') {
                // Old style file, assume order is true/false.
                $warning = true;
                if ($first) {
                    $answertext = 'true';
                } else {
                    $answertext = 'false';
                }
            }

            if ($answertext == 'true') {
                $question->answer = ($answer['@']['fraction'] == 100);
                $question->correctanswer = $question->answer;
                $question->feedbacktrue = $feedback;
            } else {
                $question->answer = ($answer['@']['fraction'] != 100);
                $question->correctanswer = $question->answer;
                $question->feedbackfalse = $feedback;
            }
            $first = false;
        }

        if ($warning) {
            $a = new stdClass();
            $a->questiontext = $question->questiontext;
            $a->answer = get_string($question->correctanswer ? 'true' : 'false', 'qtype_ubtruefalse');
            echo $OUTPUT->notification(get_string('truefalseimporterror', 'qformat_xml', $a));
        }

        $format->import_hints($question, $data, false, false, $format->get_format($question->questiontextformat));

        return $question;
    }

    public function delete_question($questionid, $contextid) {
        global $DB;
        $DB->delete_records('question_ubtruefalse', array('question' => $questionid));

        parent::delete_question($questionid, $contextid);
    }

    public function move_files($questionid, $oldcontextid, $newcontextid) {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_answers($questionid, $oldcontextid, $newcontextid);
    }

    protected function delete_files($questionid, $contextid) {
        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_answers($questionid, $contextid);
    }

    public function get_random_guess_score($questiondata) {
        return 0.5;
    }

    public function get_possible_responses($questiondata) {
        return array(
            $questiondata->id => array(
                0 => new question_possible_response(get_string('false', 'qtype_ubtruefalse'),
                        $questiondata->options->answers[
                        $questiondata->options->falseanswer]->fraction),
                1 => new question_possible_response(get_string('true', 'qtype_ubtruefalse'),
                        $questiondata->options->answers[
                        $questiondata->options->trueanswer]->fraction),
                null => question_possible_response::no_response()
            )
        );
    }
}
