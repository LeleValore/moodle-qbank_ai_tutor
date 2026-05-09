<?php
/**
 * Internal Moodle test page for Bedrock connectivity.
 * Access: site admins only.
 */

require('../../../config.php');
require_once($CFG->dirroot . '/question/bank/genai/lib.php');
require_once($CFG->dirroot . '/question/bank/genai/vendor/autoload.php');

use qbank_genai\bedrock\connector;

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/question/bank/genai/test_bedrock.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('bedrocksettings', 'qbank_genai'));
$PAGE->set_heading(get_string('bedrocksettings', 'qbank_genai'));

echo $OUTPUT->header();

$cfg = qbank_genai_get_bedrock_config();

echo html_writer::tag('h3', 'Bedrock configuration (from plugin settings or env)');
echo html_writer::start_tag('ul');
echo html_writer::tag('li', 'Region: ' . s($cfg['region'] ?? '(empty)'));
echo html_writer::tag('li', 'Model ID: ' . s($cfg['modelid'] ?? '(empty)'));
echo html_writer::tag('li', 'Knowledge Base ID: ' . s($cfg['knowledge_base_id'] ?? '(empty)'));
echo html_writer::tag('li', 'Data Source ID: ' . s($cfg['data_source_id'] ?? '(empty)'));
echo html_writer::tag('li', 'S3 Bucket: ' . s($cfg['s3_bucket'] ?? '(empty)'));
echo html_writer::end_tag('ul');

if (empty($cfg['enabled'])) {
    echo html_writer::div('Bedrock not configured. Set values under Site administration → Plugins → Generative AI Question Bank.', 'alert alert-danger');
    echo $OUTPUT->footer();
    exit;
}

// Instantiate connector using plugin settings.
$connector = new connector(
    $cfg['region'],
    $cfg['access_key'] ?? '',
    $cfg['secret_key'] ?? '',
    $cfg['knowledge_base_id'] ?? '',
    $cfg['modelid'] ?? '',
    $cfg['data_source_id'] ?? '',
    $cfg['s3_bucket'] ?? '',
    $cfg['inference_profile_arn'] ?? ''
);

echo html_writer::tag('h3', 'Runtime test');
echo html_writer::start_tag('div');
$prompt = 'Health check: reply with the single word OK.';
$res = $connector->direct_generate('healthcheck', $prompt);
if ($res === null) {
    echo html_writer::div('Runtime test FAILED: ' . s($connector->get_last_error()), 'alert alert-danger');
} else {
    echo html_writer::div('Runtime test OK. Response: ' . s($res), 'alert alert-success');
}
echo html_writer::end_tag('div');

if (!empty($cfg['knowledge_base_id'])) {
    echo html_writer::tag('h3', 'Knowledge base retrieval test');
    $payload = [
        'knowledgeBaseId' => $cfg['knowledge_base_id'],
        'retrievalQuery' => ['text' => 'health check'],
        'retrievalConfiguration' => ['vectorSearchConfiguration' => ['numberOfResults' => 3]]
    ];

    $kbres = $connector->retrieve_and_generate($payload);
    if ($kbres === null) {
        echo html_writer::div('KB retrieval FAILED: ' . s($connector->get_last_error()), 'alert alert-danger');
    } else {
        echo html_writer::div('KB retrieval OK. Preview JSON below.', 'alert alert-success');
        echo html_writer::tag('pre', s(json_encode($kbres, JSON_PRETTY_PRINT)));
    }
}

echo $OUTPUT->single_button(new moodle_url('/admin/settings.php', ['section' => 'qbank_genai_settings']), get_string('settings', 'qbank_genai'));

echo $OUTPUT->footer();
