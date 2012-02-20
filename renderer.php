<?php
// This file is part of Stack - http://stack.bham.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Stack question renderer class.
 *
 * @package   qtype_stack
 * @copyright 2012 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Generates the output for Stack questions.
 *
 * @copyright 2012 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_stack_renderer extends qtype_renderer {
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        $question = $qa->get_question();

        $questiontext = $question->get_qt_var('_questiontext');
        if (empty($question->interactions)) {
            $xhtml = '<div class="secondaryFeedback">'.stack_string('stackQuestion_noQuestionParts').'</div>'.$questiontext;
        } else {
            foreach ($question->interactions as $name => $interaction) {
                // TODO: get the value of the current answer to put into the html.
                $currentanswer = ''; //$qa->get_last_qt_var('answer');
                $questiontext = str_replace("#{$name}#",
                     $interaction->get_xhtml($currentanswer, $options->readonly), $questiontext);

                list ($status, $iefeedback) = $interaction->validate_student_response($currentanswer, $question->options);
                $questiontext = str_replace("<IEfeedback>{$name}</IEfeedback>", $iefeedback, $questiontext);
                $attemptstatus[$name] = $status;
            }

            foreach ($question->prts as $index => $prt) {
                // TODO see stack_old/lib/ui/DisplayItem.php.
                $requirednames = $prt->get_required_variables(array_keys($question->interactions));
                if ($this->execute_prt($requirednames, $attemptstatus)) {
                    list($feedback, $answernote, $errors, $valid, $mark, $penalty) = $prt->evaluate_response($questionvars, $options, $answers, $seed);
                    if (!$valid) {
                        $penalty = 0;
                    }
                    // TODO store the outcomes of the attempt..
                    // TODO update the mark....
                } else { // TODO...
                    $feedback = '';
                }
                $questiontext = str_replace("<PRTfeedback>{$index}</PRTfeedback>", $feedback, $questiontext);
            }
        }
        return $question->format_text($questiontext, $question->questiontextformat,
                $qa, 'question', 'questiontext', $question->id);
    }

    /**
     * Decides if the potential response tree should be executed.
     */
    private function execute_prt($requirednames, $attemptstatus) {
        $execute = true;
        foreach ($requirednames as $name) {
            if (array_key_exists ($name, $attemptstatus)) {
                if ('score' != $attemptstatus[$name]) {
                    $execute = false;
                }
            } else {
                $execute = false;
            }
        }
        return $execute;
    }
    /**
     * Tests whether the interaction element exists inside a math region.
     */
    private function is_inside_maths($ie, $string) {
        // remove all delimited regions and see if $ie remains in $string
        $patterns = array('/\\$\\$(.+?)\\$\\$/', '/\\$(.+?)\\$/', '/\\\\\[(.+?)\\\\\]/', '/\\\\\((.+?)\\\\\)/');
        $string = preg_replace($patterns, '', $string);
        return strpos($string, $ie) === true;
    }

}
