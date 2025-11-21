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
 * This page allows users to change settings related to the OpenAI API
 *
 * @package    qbank_genai
 * @copyright  2024 Christian Grévisse <christian.grevisse@uni.lu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');
require_once($CFG->dirroot . '/question/bank/genai/lib.php');

$courseid = required_param('courseid', PARAM_INT);

$url = new moodle_url('/question/bank/genai/genai_settings.php', ['courseid' => $courseid]);
$PAGE->set_url($url);

$course = get_course($courseid);

require_login($course);
core_question\local\bank\helper::require_plugin_enabled('qbank_genai');

$course = course_get_format($course)->get_course();

$context = context_course::instance($course->id);
$PAGE->set_context($context);

require_all_capabilities(qbank_genai_required_capabilities(), $context);

$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('settings', 'qbank_genai'));

$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('settings', 'qbank_genai'));

$settings = $DB->get_record('qbank_genai_openai_settings', ["courseid" => $course->id, "userid" => $USER->id]);

$mform = new \qbank_genai\form\settings_form($url);

if ($settings) {
    // Pass previous settings.
    $mform->set_data($settings);
}

if ($fromform = $mform->get_data()) {
    // Delete previous settings (for this user and course), if any.
    $DB->delete_records('qbank_genai_openai_settings', ["courseid" => $course->id, "userid" => $USER->id]);

    // Save new settings.
    $DB->insert_record('qbank_genai_openai_settings', ["courseid" => $course->id, "userid" => $USER->id,
        "openaiapikey" => $fromform->openaiapikey, "assistantid" => $fromform->assistantid]);

    // Redirect to this page again.
    redirect($PAGE->url);
} else {
    $mform->display();
}

echo $OUTPUT->footer();
