<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     qbank_genai
 * @category    string
 * @copyright   2023 Christian Grévisse <christian.grevisse@uni.lu>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['autotag'] = 'AutoTag';
$string['autotagintro'] = 'The following questions will be auto-tagged:';
$string['autotagparsingerror'] = 'Error while parsing the generated tags.';
$string['autotagsuccess'] = '{$a} questions have been tagged successfully.';

$string['noopenaiapikey'] = 'You need to set an OpenAI API key.';
$string['nobedrockconfig'] = 'Bedrock is not configured. Set Bedrock region and model ID in plugin settings or via environment variables.';
$string['noquestionselected'] = 'No question selected.';
$string['noquestionsgenerated'] = 'Issue during question generation. No questions were generated.';
$string['noresources'] = 'There are no resources in your course.';
$string['numberessays'] = 'Number of essay questions';
$string['numbermcqs'] = 'Number of multiple choice questions';

$string['openaiapikey'] = 'OpenAI API key';
$string['openaiapikey_help'] = 'To be created at <a href="https://platform.openai.com/api-keys" target="_blank">https://platform.openai.com/api-keys</a>.';
$string['openaiapisettings'] = 'OpenAI API Settings';

$string['pluginname'] = 'Generative AI Question Bank';

$string['privacy:metadata:qbank_genai_openai_settings'] = 'The user\'s ID';
$string['privacy:metadata:qbank_genai_openai_settings:userid'] = 'Table that stores data related to the OpenAI API';

$string['questiongenerationparsingerror'] = 'Error while parsing the generated questions.';
$string['questiongenerationsuccess'] = '{$a->number} questions have been generated successfully. You can find them in the question bank under the category "{$a->category}".';

$string['return'] = 'Return to question bank';

$string['settings'] = 'Generative AI Question Bank Settings';
$string['title'] = 'Generate questions';

$string['bedrocksettings'] = 'Bedrock settings';
$string['bedrocksettings_help'] = 'Configure Amazon Bedrock runtime and knowledge base access for the plugin.';

$string['bedrock_region'] = 'Bedrock region';
$string['bedrock_region_help'] = 'AWS region where Bedrock is available (eg. us-east-1). Can also be set via BEDROCK_REGION env var.';

$string['bedrock_modelid'] = 'Bedrock model ID';
$string['bedrock_modelid_help'] = 'ID of the Bedrock model to use for generation (eg. model name provided by Bedrock). Can also be set via BEDROCK_MODELID env var.';

$string['bedrock_inference_profile_arn'] = 'Bedrock inference profile ARN';
$string['bedrock_inference_profile_arn_help'] = 'ARN of an inference profile that contains the target model (eg. arn:aws:bedrock:us-east-1:123456789012:inference-profile/your-profile). If set, the connector will include it in requests to invoke provisioned models. Can also be set via BEDROCK_INFERENCE_PROFILE_ARN env var.';

$string['bedrock_access_key'] = 'AWS access key ID';
$string['bedrock_access_key_help'] = 'AWS access key ID with permissions to call Bedrock and S3. Can also be set via AWS_ACCESS_KEY_ID env var.';

$string['bedrock_secret_key'] = 'AWS secret access key';
$string['bedrock_secret_key_help'] = 'AWS secret access key corresponding to the access key. Can also be set via AWS_SECRET_ACCESS_KEY env var.';

$string['bedrock_knowledge_base_id'] = 'Bedrock knowledge base ID';
$string['bedrock_knowledge_base_id_help'] = 'Identifier of the Bedrock knowledge base to query (optional). Can also be set via BEDROCK_KNOWLEDGE_BASE_ID env var.';

$string['bedrock_data_source_id'] = 'Bedrock data source ID';
$string['bedrock_data_source_id_help'] = 'Identifier of the data source inside the knowledge base (optional). Can also be set via BEDROCK_DATA_SOURCE_ID env var.';

$string['bedrock_s3_bucket'] = 'S3 bucket for ingestion';
$string['bedrock_s3_bucket_help'] = 'S3 bucket name used to stage files for ingestion into the knowledge base (optional). Can also be set via BEDROCK_S3_BUCKET env var.';
