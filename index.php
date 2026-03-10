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
 * Index file of the GenAI question generation plugin.
 *
 * @package    qbank_genai
 * @copyright  2023 Christian Grévisse <christian.grevisse@uni.lu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');
require_once($CFG->dirroot . '/question/bank/genai/lib.php');

$courseid = required_param('courseid', PARAM_INT);

$url = new moodle_url('/question/bank/genai/index.php', ['courseid' => $courseid]);
$PAGE->set_url($url);

$course = get_course($courseid);

require_login($course);
core_question\local\bank\helper::require_plugin_enabled('qbank_genai');

$course = course_get_format($course)->get_course();

$context = context_course::instance($course->id);
$PAGE->set_context($context);

require_all_capabilities(qbank_genai_required_capabilities(), $context);

$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('title', 'qbank_genai'));

$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();

// Print tertiary navigation.
$renderer = $PAGE->get_renderer('core_question', 'bank');
$qbankaction = new \core_question\output\qbank_action_menu($url);
echo $renderer->render($qbankaction);

echo $OUTPUT->heading(get_string('title', 'qbank_genai'));

// Check for OpenAI API key.
$openaiapikey = qbank_genai_get_openai_apikey($course->id);
if (empty($openaiapikey)) {
    echo html_writer::tag('div', get_string('noopenaiapikey', 'qbank_genai'), ['class' => 'alert alert-warning']);
}

// Get course resources.
$resources = qbank_genai_get_course_resources($course);

if (count($resources) == 0) {
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning']);
    echo html_writer::tag('p', get_string('noresources', 'qbank_genai'));
    echo html_writer::end_tag('div');
} else {
    $mform = new \qbank_genai\form\generation_form($url, $resources);
    $mform->display();

    echo html_writer::tag('button', get_string('title', 'qbank_genai'), ["class" => "btn btn-primary mt-3",
        "id" => "id_questiongenerationbutton"]);

    echo html_writer::tag('div', '', ["class" => "mt-3 alert", "id" => "id_questiongenerationresult"]);
}

// In Moodle 5.0, shared question banks were introduced. New courses do no longer contain a default question bank.
global $CFG;
if ($CFG->version > 2025041400) {
    $qbank = \core_question\local\bank\question_bank_helper::get_default_open_instance_system_type($course, true);
    $contextqbankid = context_module::instance($qbank->id)->id;
} else {
    $contextqbankid = $context->id;
}

// Add Javascript module.
global $PAGE;
$PAGE->requires->js_call_amd('qbank_genai/questiongeneration', 'init', [$contextqbankid, $course->id]);

echo $OUTPUT->footer();
