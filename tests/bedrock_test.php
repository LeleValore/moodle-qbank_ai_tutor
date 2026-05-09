<?php
// CLI test for Bedrock connector.
// Usage (env):
// BEDROCK_REGION=us-east-1 AWS_ACCESS_KEY_ID=... AWS_SECRET_ACCESS_KEY=... BEDROCK_MODELID=... php tests/bedrock_test.php

define('MOODLE_INTERNAL', true);
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../classes/bedrock/connector.php';

use qbank_genai\bedrock\connector;

$region = getenv('BEDROCK_REGION') ?: ($argv[1] ?? '');
$access = getenv('AWS_ACCESS_KEY_ID') ?: ($argv[2] ?? '');
$secret = getenv('AWS_SECRET_ACCESS_KEY') ?: ($argv[3] ?? '');
$modelid = getenv('BEDROCK_MODELID') ?: ($argv[4] ?? '');
$kbid = getenv('BEDROCK_KNOWLEDGE_BASE_ID') ?: '';
$datasource = getenv('BEDROCK_DATA_SOURCE_ID') ?: '';
$s3bucket = getenv('BEDROCK_S3_BUCKET') ?: '';

if (empty($region) || empty($access) || empty($secret) || empty($modelid)) {
    echo "Usage: set env BEDROCK_REGION, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, BEDROCK_MODELID\n";
    echo "Or pass them as args: php tests/bedrock_test.php <region> <access> <secret> <modelid>\n";
    exit(1);
}

$connector = new connector($region, $access, $secret, $kbid, $modelid, $datasource, $s3bucket);

echo "Testing Bedrock runtime (direct_generate)...\n";
$prompt = "Health check: reply with the single word OK.";
$res = $connector->direct_generate('health-check', $prompt);
if ($res === null) {
    echo "Runtime test FAILED: " . $connector->get_last_error() . PHP_EOL;
} else {
    echo "Runtime test OK. Response:\n";
    echo $res . PHP_EOL;
}

if (!empty($kbid)) {
    echo "\nTesting KB retrieval...\n";
    $payload = [
        'knowledgeBaseId' => $kbid,
        'retrievalQuery' => ['text' => 'health check'],
        'retrievalConfiguration' => ['vectorSearchConfiguration' => ['numberOfResults' => 3]]
    ];
    $kbres = $connector->retrieve_and_generate($payload);
    if ($kbres === null) {
        echo "KB retrieval FAILED: " . $connector->get_last_error() . PHP_EOL;
    } else {
        echo "KB retrieval OK. Result keys: " . implode(', ', array_keys($kbres)) . PHP_EOL;
        echo "Sample JSON:\n" . json_encode($kbres, JSON_PRETTY_PRINT) . PHP_EOL;
    }
}

<?php
// CLI test for Bedrock connector.
// Usage (env):
// BEDROCK_REGION=us-east-1 AWS_ACCESS_KEY_ID=... AWS_SECRET_ACCESS_KEY=... BEDROCK_MODELID=... php tests/bedrock_test.php

define('MOODLE_INTERNAL', true);
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../classes/bedrock/connector.php';

use qbank_genai\bedrock\connector;

$region = getenv('BEDROCK_REGION') ?: ($argv[1] ?? 'us-east-1');
$access = getenv('AWS_ACCESS_KEY_ID') ?: ($argv[2] ?? '');
$secret = getenv('AWS_SECRET_ACCESS_KEY') ?: ($argv[3] ?? '');
$modelid = getenv('BEDROCK_MODELID') ?: ($argv[4] ?? 'anthropic.claude-sonnet-4-6');
$kbid = getenv('BEDROCK_KNOWLEDGE_BASE_ID') ?: 'CYFP7KN7PZ';
$datasource = getenv('BEDROCK_DATA_SOURCE_ID') ?: 'PXDDUEQWQ4';
$s3bucket = getenv('BEDROCK_S3_BUCKET') ?: 'moodle-quiz-bucket-925091289863-us-east-1-an';
$inference_profile_arn = getenv('BEDROCK_INFERENCE_PROFILE_ARN') ?: 'arn:aws:bedrock:us-east-1:925091289863:inference-profile/global.anthropic.claude-sonnet-4-6';

// If this script is run from inside a Moodle installation, try to load plugin config
// so tests can be executed using site settings. This allows running the same script
// both outside and inside Moodle.
$maybeconfig = __DIR__ . '/../../../../config.php';
if (file_exists($maybeconfig)) {
    echo "Detected Moodle config at $maybeconfig - loading Moodle to read plugin config...\n";
    require_once $maybeconfig;
    // Ensure plugin lib is available.
    if (isset($CFG) && !empty($CFG->dirroot)) {
        require_once $CFG->dirroot . '/question/bank/genai/lib.php';
        $pcfg = qbank_genai_get_bedrock_config();
        $region = $region ?: ($pcfg['region'] ?? '');
        $access = $access ?: ($pcfg['access_key'] ?? '');
        $secret = $secret ?: ($pcfg['secret_key'] ?? '');
        $modelid = $modelid ?: ($pcfg['modelid'] ?? '');
        $kbid = $kbid ?: ($pcfg['knowledge_base_id'] ?? '');
        $datasource = $datasource ?: ($pcfg['data_source_id'] ?? '');
        $s3bucket = $s3bucket ?: ($pcfg['s3_bucket'] ?? '');
    }
}

if (empty($region) || empty($access) || empty($secret)) {
    echo "Usage: set env BEDROCK_REGION, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY\n";
    echo "Or pass them as args: php tests/bedrock_test.php <region> <access> <secret> <modelid>\n";
    echo "To auto-probe a set of modelIds, omit <modelid> or pass 'probe' as <modelid>.\n";
    exit(1);
}

$connector = new connector($region, $access, $secret, $kbid, $modelid, $datasource, $s3bucket, $inference_profile_arn);

echo "Testing Bedrock runtime (direct_generate)...\n";
$prompt = "Health check: reply with the single word OK.";
$res = $connector->direct_generate('health-check', $prompt);
if ($res === null) {
    echo "Runtime test FAILED: " . $connector->get_last_error() . PHP_EOL;
    $direct_failed = true;
    // Print detailed diagnostics to help understand the rejection.
    $diag = $connector->get_last_diagnostics();
    echo "\n---- Detailed Diagnostics ----\n";
    echo json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    // Quick suggestions based on common error snippets.
    $snippet = strtolower($diag['response']['body_snippet'] ?? '');
    if (strpos($snippet, 'on-demand') !== false || strpos($snippet, 'on‑demand') !== false) {
        echo "Suggestion: model likely requires provisioned throughput (console: Bedrock → Models → provision capacity).\n";
    }
    if (strpos($snippet, 'end of its life') !== false || strpos($snippet, 'deprecated') !== false) {
        echo "Suggestion: model version deprecated — pick a newer modelId from Bedrock Console.\n";
    }
    if (strpos($snippet, 'invalid') !== false) {
        echo "Suggestion: model identifier may be invalid in this account/region. Run 'aws bedrock list-models' to list available modelIds.\n";
    }
} else {
    echo "Runtime test OK. Response:\n";
    echo $res . PHP_EOL;
    $direct_failed = false;
}

// Multi-model probing removed — this test will only run against the configured
// model / inference profile (see variables at top of this script).

if (!empty($kbid)) {
    echo "\nTesting KB retrieval...\n";
    $payload = [
        'input' => ['text' => 'health check'],
        'retrieveAndGenerateConfiguration' => [
            'type' => 'KNOWLEDGE_BASE',
            'knowledgeBaseConfiguration' => [
                'knowledgeBaseId' => $kbid,
                'modelArn' => 'arn:aws:bedrock:' . $region . '::foundation-model/' . $modelid,
                'retrievalConfiguration' => [
                    'vectorSearchConfiguration' => ['numberOfResults' => 3],
                ],
                'generationConfiguration' => [
                    'promptTemplate' => [
                        'textPromptTemplate' => "Answer the user question using only the following retrieved context:\n\n\$search_results\$\n\nQuestion: \$query\$",
                    ],
                ],
            ],
        ],
    ];
    $kbres = $connector->retrieve_and_generate($payload);
    if ($kbres === null) {
        echo "KB retrieval FAILED: " . $connector->get_last_error() . PHP_EOL;
        $diag = $connector->get_last_diagnostics();
        echo "\n---- KB Detailed Diagnostics ----\n";
        echo json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        $snippet = strtolower($diag['response']['body_snippet'] ?? '');
        if (strpos($snippet, 'inference profile') !== false || strpos($snippet, 'on-demand') !== false) {
            echo "Suggestion: This model may require invocation via an inference profile ARN.\n";
            echo "If you have an inference profile ARN, set BEDROCK_INFERENCE_PROFILE_ARN and re-run the test.\n";
        }
    } else {
        echo "KB retrieval OK. Result keys: " . implode(', ', array_keys($kbres)) . PHP_EOL;
        echo "Sample JSON:\n" . json_encode($kbres, JSON_PRETTY_PRINT) . PHP_EOL;
    }
}
