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
