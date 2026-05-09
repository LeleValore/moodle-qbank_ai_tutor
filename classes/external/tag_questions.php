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
use core_external\external_multiple_structure;
use core_external\external_value;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/bank/genai/lib.php');
require_once($CFG->dirroot . '/question/bank/genai/vendor/autoload.php');

/**
 * Class tag_questions
 *
 * @package    qbank_genai
 * @copyright  2025 Christian Grévisse <christian.grevisse@uni.lu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tag_questions extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'questionlist' => new external_multiple_structure(
                new external_value(PARAM_INT, 'ID of question')
            ),
        ]);
    }

    /**
     * Returns description of method result value
     * @return external_value
     */
    public static function execute_returns() {
        return new external_value(PARAM_TEXT, 'Result of the autotagging');
    }

    /**
     * Tag questions
     * @param object $questionlist The list of question IDs to tag
     * @return string The result
     */
    public static function execute($questionlist) {
        $params = self::validate_parameters(self::execute_parameters(), ['questionlist' => $questionlist]);

        $numbersuccessfullytagged = 0;

        // Require Bedrock configuration (no OpenAI fallback).
        $bedrockcfg = qbank_genai_get_bedrock_config();
        if (empty($bedrockcfg['enabled'])) {
            throw new \Exception(get_string('nobedrockconfig', 'qbank_genai'));
        }

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

        foreach ($questionlist as $qid) {
            $question = \question_bank::load_question($qid);

            $context = \context::instance_by_id($question->contextid);
            self::validate_context($context);
            question_require_capability_on($qid, 'tag');

            // Check if question has already been tagged, if so, skip it.
            $questiontags = \core_tag_tag::get_item_tags('core_question', 'question', $qid);

            if (!empty($questiontags)) {
                continue;
            }

            $questiontext = strip_tags($question->questiontext);

            foreach ($question->answers as $a) {
                $questiontext .= '\n- ' . strip_tags($a->answer);
            }

            // Call Bedrock to get tags and parse JSON-only response.
            $system = 'You are a tagging assistant. Your task is to extract a list of the most important tags for the given content. All tags shall be given in English.';
            $prompt = $system . "\n\n" . $questiontext . "\n\nReturn ONLY valid JSON with the shape: {\"tags\":[\"tag1\",\"tag2\"]}.";

            $raw = $connector->direct_generate($questiontext, $prompt);
            $parsed = qbank_genai_extract_json_from_text($raw);
            if ($parsed === null || !isset($parsed->tags)) {
                throw new \Exception(get_string('autotagparsingerror', 'qbank_genai'));
            }
            $tags = $parsed->tags;

            \core_tag_tag::set_item_tags('core_question', 'question', $qid, \context::instance_by_id($question->contextid), $tags);

            $numbersuccessfullytagged++;
        }

        return get_string('autotagsuccess', 'qbank_genai', $numbersuccessfullytagged);
    }
}
