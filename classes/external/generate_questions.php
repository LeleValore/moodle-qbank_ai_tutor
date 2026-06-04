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
            'numberShortAnswers' => new external_value(PARAM_INT, 'Number of short answer questions'),
            'numberTrueFalse' => new external_value(PARAM_INT, 'Number of true/false questions'),
            'numberMatch' => new external_value(PARAM_INT, 'Number of matching questions'),
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
     * The selected file is uploaded to OpenAI/Bedrock, whereupon questions are generated based on its content.
     * Questions are then programmatically added to a newly created question bank category.
     *
     * @param int $contextid The ID of the context
     * @param int $courseid The ID of the course
     * @param int $fileid The ID of the file
     * @param int $numbermcqs Number of multiple choice questions to be generated
     * @param int $numberessays Number of essay questions to be generated
     * @param int $numbershortanswers Number of short answer questions to be generated
     * @param int $numbertruefalse Number of true/false questions to be generated
     * @param int $numbermatch Number of matching questions to be generated
     * @return string The result
     */
    public static function execute($contextid, $courseid, $fileid, $numbermcqs, $numberessays, $numbershortanswers, $numbertruefalse, $numbermatch) {
        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'contextID' => $contextid, 
                'courseID' => $courseid, 
                'fileID' => $fileid,
                'numberMCQs' => $numbermcqs, 
                'numberEssays' => $numberessays,
                'numberShortAnswers' => $numbershortanswers,
                'numberTrueFalse' => $numbertruefalse,
                'numberMatch' => $numbermatch
            ]
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

        // Build system prompt with the new question types instructions.
        $systemprompt = "You are a quiz generator. You will be provided a file about which you should create ";
        $systemprompt .= "an indicated number of questions for each requested type, in the same language as its content.\n\n";
        
        $systemprompt .= "Requirements per type:\n";
        $systemprompt .= "- Multiple Choice (mcq): Between 3 and 5 answers, exactly 1 correct answer. Plausible distractors. Correct answer not much longer than distractors.\n";
        $systemprompt .= "- Essay (essay): Include a grading rubric with criteria and points assigned to each criterion (each on a separate line) and the maxpoints.\n";
        $systemprompt .= "- Short Answer (shortanswer): A question requiring a short text or word answer. Provide a list of accepted correct answers.\n";
        $systemprompt .= "- True/False (truefalse): A statement that is either true or false.\n";
        $systemprompt .= "- Matching (match): A set of sub-questions/stems and their corresponding matching answers.\n\n";
        
        $systemprompt .= "You must output valid JSON matching this structure:\n";
        $systemprompt .= "{\n";
        $systemprompt .= "  \"mcq\": [ {\"stem\": \"...\", \"answers\": [{\"text\": \"...\", \"correct\": true}]} ],\n";
        $systemprompt .= "  \"essay\": [ {\"stem\": \"...\", \"maxpoints\": 10, \"graderinfo\": \"...\"} ],\n";
        $systemprompt .= "  \"shortanswer\": [ {\"stem\": \"...\", \"accepted_answers\": [\"ans1\", \"ans2\"]} ],\n";
        $systemprompt .= "  \"truefalse\": [ {\"stem\": \"...\", \"correct_answer\": true} ],\n";
        $systemprompt .= "  \"match\": [ {\"stem\": \"...\", \"pairs\": [{\"subquestion\": \"...\", \"subanswer\": \"...\"}]} ]\n";
        $systemprompt .= "}";

        // Build user prompt dynamically based on requested counts.
        $userprompt = 'Create ';
        $userprompt .= $numbermcqs . ' multiple choice questions, ';
        $userprompt .= $numberessays . ' essay questions, ';
        $userprompt .= $numbershortanswers . ' short answer questions, ';
        $userprompt .= $numbertruefalse . ' true/false questions, and ';
        $userprompt .= $numbermatch . ' matching questions on the content of the provided file.';

        $connector = new \qbank_genai\bedrock\connector(
            $bedrockcfg['region'],
            $bedrockcfg['access_key'],
            $bedrockcfg['secret_key'],
            $bedrockcfg['knowledge_base_id'] ?? '',
            $bedrockcfg['modelid'],
            $bedrockcfg['data_source_id'] ?? '',
            $bedrockcfg['s3_bucket'] ?? '',
            $bedrockcfg['inference_profile_arn'] ?? ''
        );

        $prompt = $systemprompt . "\n\n" . $filetext . "\n\n" . $userprompt;

        $raw = $connector->direct_generate($filetext, $prompt);

        $questiondata = qbank_genai_extract_json_from_text($raw);
        if ($questiondata === null) {
            throw new \Exception(get_string('questiongenerationparsingerror', 'qbank_genai'));
        }

        $multiplechoicequestions = $questiondata->mcq ?? [];
        $essayquestions          = $questiondata->essay ?? [];
        $shortanswerquestions    = $questiondata->shortanswer ?? [];
        $truefalsequestions      = $questiondata->truefalse ?? [];
        $matchquestions          = $questiondata->match ?? [];

        $totalquestions = count($multiplechoicequestions) + count($essayquestions) + 
                          count($shortanswerquestions) + count($truefalsequestions) + count($matchquestions);

        if ($totalquestions > 0) {
            // Create question bank category and questions.
            $category = qbank_genai_create_question_category($contextid, get_coursemodule_from_id("resource", $fileid)->name);

            $i = 0;

            // 1. Multiple Choice Questions
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

            // 2. Essay Questions
            foreach ($essayquestions as $data) {
                $questionname = str_pad(strval(++$i), 3, "0", STR_PAD_LEFT);
                qbank_genai_create_essay($questionname, $data->stem, $data->maxpoints, nl2br($data->graderinfo), $category);
            }

            // 3. Short Answer Questions (Risposta breve)
            foreach ($shortanswerquestions as $data) {
                $questionname = str_pad(strval(++$i), 3, "0", STR_PAD_LEFT);
                $question = new \stdClass();
                $question->stem = $data->stem;
                $question->answers = [];
                
                // Assegna il 100% del punteggio a tutte le varianti di risposte corrette fornite
                foreach ($data->accepted_answers as $answertext) {
                    $question->answers[] = (object) ["text" => $answertext, "weight" => 1.0];
                }
                
                qbank_genai_create_shortanswer($questionname, $question, $category);
            }

            // 4. True/False Questions (Vero/Falso)
            foreach ($truefalsequestions as $data) {
                $questionname = str_pad(strval(++$i), 3, "0", STR_PAD_LEFT);
                $question = new \stdClass();
                $question->stem = $data->stem;
                // In Moodle tipicamente si passa un booleano o il testo corretto per stabilire la risposta esatta
                $question->correctanswer = (bool)$data->correct_answer;
                
                qbank_genai_create_truefalse($questionname, $question, $category);
            }

            // 5. Matching Questions (Corrispondenze)
            foreach ($matchquestions as $data) {
                $questionname = str_pad(strval(++$i), 3, "0", STR_PAD_LEFT);
                $question = new \stdClass();
                $question->stem = $data->stem;
                $question->pairs = [];
                
                foreach ($data->pairs as $pair) {
                    $question->pairs[] = (object) [
                        "subquestion" => $pair->subquestion,
                        "subanswer"   => $pair->subanswer
                    ];
                }
                
                qbank_genai_create_match($questionname, $question, $category);
            }

            return get_string(
                'questiongenerationsuccess',
                'qbank_genai',
                ['number' => $totalquestions, 'category' => $category->name]
            );
        } else {
            throw new \Exception(get_string('noquestionsgenerated', 'qbank_genai'));
        }
    }
}