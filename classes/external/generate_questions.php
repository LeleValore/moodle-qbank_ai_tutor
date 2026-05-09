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

namespace qbank_genai\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/bank/genai/lib.php');
require_once($CFG->dirroot . '/question/bank/genai/vendor/autoload.php');

/**
 * Class generate_questions
 *
 * @package    qbank_genai
 * @copyright  2026 Christian Grévisse <christian.grevisse@uni.lu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_questions extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'contextID' => new external_value(PARAM_INT, 'Context ID'),
            'courseID' => new external_value(PARAM_INT, 'Course ID'),
            'fileID' => new external_value(PARAM_INT, 'File ID'),
            'numberMCQs' => new external_value(PARAM_INT, 'Number of MCQs'),
            'numberEssays' => new external_value(PARAM_INT, 'Number of essays'),
        ]);
    }

    /**
     * Returns description of method result value
     * @return external_value
     */
    public static function execute_returns() {
        return new external_value(PARAM_TEXT, 'Result of the generation');
    }

    /**
     * Generate questions.
     *
     * The selected file is uploaded to OpenAI, whereupon questions are generated based on its content.
     * Questions are then programmatically added to a newly created question bank category.
     *
     * @param int $contextid The ID of the context
     * @param int $courseid The ID of the course
     * @param int $fileid The ID of the file
     * @param int $numbermcqs Number of multiple choice questions to be generated
     * @param int $numberessays Number of essay questions to be generated
     * @return string The result
     */
    public static function execute($contextid, $courseid, $fileid, $numbermcqs, $numberessays) {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['contextID' => $contextid, 'courseID' => $courseid, 'fileID' => $fileid,
                'numberMCQs' => $numbermcqs, 'numberEssays' => $numberessays]
        );

        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_all_capabilities(qbank_genai_required_capabilities(), $context, null, false);

        // Require Bedrock configuration (no OpenAI fallback).
        $bedrockcfg = qbank_genai_get_bedrock_config();
        if (empty($bedrockcfg['enabled'])) {
            throw new \Exception(get_string('nobedrockconfig', 'qbank_genai'));
        }

        // Get file info and extract text.
        $fileinfo = qbank_genai_get_fileinfo_for_resource($fileid);
        if (!$fileinfo) {
            throw new \Exception(get_string('noquestionsgenerated', 'qbank_genai'));
        }

        $filetext = qbank_genai_extract_text_from_file($fileinfo, 15000);
        if (empty($filetext)) {
            throw new \Exception(get_string('noquestionsgenerated', 'qbank_genai'));
        }

        // Build prompts.
        $systemprompt = "You are a quiz generator. You will be provided a file about which you should create ";
        $systemprompt .= "an indicated number of multiple choice questions (MCQ) respectively essay questions, ";
        $systemprompt .= "in the same language as its content. Each multiple choice question shall have between ";
        $systemprompt .= "3 and 5 answers and only 1 correct answer. Make sure all distractors are plausible and ";
        $systemprompt .= "that the correct answer is not much longer than any distractor. For essay questions, ";
        $systemprompt .= "also indicate grading information (a rubric with grading criteria and points assigned ";
        $systemprompt .= "to each criterion, each on a separate line) that can be used for automatic grading, ";
        $systemprompt .= "as well as the maximum number of points.";

        $userprompt = 'Create ' . $numbermcqs . ' multiple choice questions and ' . $numberessays;
        $userprompt .= ' essay questions on the content of the provided file.';

        $connector = new \qbank_genai\bedrock\connector(
            $bedrockcfg['region'],
            $bedrockcfg['access_key'],
            $bedrockcfg['secret_key'],
            $bedrockcfg['knowledge_base_id'] ?? '',
            $bedrockcfg['modelid'],
            $bedrockcfg['data_source_id'] ?? '',
            $bedrockcfg['s3_bucket'] ?? ''
        );

        $prompt = $systemprompt . "\n\n" . $filetext . "\n\n" . $userprompt;

        $raw = $connector->direct_generate($filetext, $prompt);

        $questiondata = qbank_genai_extract_json_from_text($raw);
        if ($questiondata === null) {
            throw new \Exception(get_string('questiongenerationparsingerror', 'qbank_genai'));
        }

        $multiplechoicequestions = $questiondata->mcq ?? [];
        $essayquestions = $questiondata->essay ?? [];

        if (count($multiplechoicequestions) + count($essayquestions) > 0) {
            // Create question bank category and questions.
            $category = qbank_genai_create_question_category($contextid, get_coursemodule_from_id("resource", $fileid)->name);

            $i = 0;

            foreach ($multiplechoicequestions as $data) {
                $question = new \stdClass();
                $question->stem = $data->stem;
                $question->answers = [];

                foreach ($data->answers as $answer) {
                    $question->answers[] = (object) ["text" => $answer->text, "weight" => $answer->correct ? 1.0 : 0.0];
                }

                $questionname = str_pad(strval(++$i), 3, "0", STR_PAD_LEFT);

                qbank_genai_create_mcq($questionname, $question, $category);
            }

            foreach ($essayquestions as $data) {
                $questionname = str_pad(strval(++$i), 3, "0", STR_PAD_LEFT);
                qbank_genai_create_essay($questionname, $data->stem, $data->maxpoints, nl2br($data->graderinfo), $category);
            }

            return get_string(
                'questiongenerationsuccess',
                'qbank_genai',
                ['number' => count($multiplechoicequestions) + count($essayquestions), 'category' => $category->name]
            );
        } else {
            throw new \Exception(get_string('noquestionsgenerated', 'qbank_genai'));
        }
    }
}
