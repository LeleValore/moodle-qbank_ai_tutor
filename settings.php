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
 * Plugin administration pages are defined here.
 *
 * @package     qbank_genai
 * @category    admin
 * @copyright   2023 Christian Grévisse <christian.grevisse@uni.lu>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('qbank_genai_settings', new lang_string('pluginname', 'qbank_genai'));

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configpasswordunmask(
            'qbank_genai/openaiapikey',
            get_string('openaiapikey', 'qbank_genai'),
            get_string('openaiapikey_help', 'qbank_genai'),
            '',
        ));
<<<<<<< HEAD
=======

        // Bedrock settings.
        $settings->add(new admin_setting_heading('qbank_genai/bedrockheading',
            get_string('bedrocksettings', 'qbank_genai'),
            get_string('bedrocksettings_help', 'qbank_genai')));

        $settings->add(new admin_setting_configtext(
            'qbank_genai/bedrock_region',
            get_string('bedrock_region', 'qbank_genai'),
            get_string('bedrock_region_help', 'qbank_genai'),
            ''
        ));

        $settings->add(new admin_setting_configtext(
            'qbank_genai/bedrock_modelid',
            get_string('bedrock_modelid', 'qbank_genai'),
            get_string('bedrock_modelid_help', 'qbank_genai'),
            ''
        ));

        $settings->add(new admin_setting_configtext(
            'qbank_genai/bedrock_inference_profile_arn',
            get_string('bedrock_inference_profile_arn', 'qbank_genai'),
            get_string('bedrock_inference_profile_arn_help', 'qbank_genai'),
            ''
        ));

        $settings->add(new admin_setting_configtext(
            'qbank_genai/bedrock_access_key',
            get_string('bedrock_access_key', 'qbank_genai'),
            get_string('bedrock_access_key_help', 'qbank_genai'),
            ''
        ));

        $settings->add(new admin_setting_configpasswordunmask(
            'qbank_genai/bedrock_secret_key',
            get_string('bedrock_secret_key', 'qbank_genai'),
            get_string('bedrock_secret_key_help', 'qbank_genai'),
            ''
        ));

        $settings->add(new admin_setting_configtext(
            'qbank_genai/bedrock_knowledge_base_id',
            get_string('bedrock_knowledge_base_id', 'qbank_genai'),
            get_string('bedrock_knowledge_base_id_help', 'qbank_genai'),
            ''
        ));

        $settings->add(new admin_setting_configtext(
            'qbank_genai/bedrock_data_source_id',
            get_string('bedrock_data_source_id', 'qbank_genai'),
            get_string('bedrock_data_source_id_help', 'qbank_genai'),
            ''
        ));

        $settings->add(new admin_setting_configtext(
            'qbank_genai/bedrock_s3_bucket',
            get_string('bedrock_s3_bucket', 'qbank_genai'),
            get_string('bedrock_s3_bucket_help', 'qbank_genai'),
            ''
        ));
>>>>>>> 0d2a43cabdd967796a1d7d1876051a71483f9f32
    }
}
