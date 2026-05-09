<?php
// List available Bedrock models using AWS SDK if present, otherwise show AWS CLI command.
// Usage:
// php tests/bedrock_list_models.php <region> <access_key> <secret_key>

require __DIR__ . '/../vendor/autoload.php';

$region = $argv[1] ?? getenv('BEDROCK_REGION') ?? '';
$access = $argv[2] ?? getenv('AWS_ACCESS_KEY_ID') ?? '';
$secret = $argv[3] ?? getenv('AWS_SECRET_ACCESS_KEY') ?? '';

if (empty($region) || empty($access) || empty($secret)) {
    echo "Usage: php tests/bedrock_list_models.php <region> <access_key> <secret_key>\n";
    echo "Or set env BEDROCK_REGION, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY\n";
    exit(1);
}

echo "Listing Bedrock models for region $region...\n";

if (class_exists('\\Aws\\Bedrock\\BedrockClient')) {
    try {
        $client = new \Aws\Bedrock\BedrockClient([
            'region' => $region,
            'version' => 'latest',
            'credentials' => [
                'key' => $access,
                'secret' => $secret,
            ],
        ]);

        $result = $client->listModels([]);
        $arr = $result->toArray();
        echo json_encode($arr, JSON_PRETTY_PRINT) . PHP_EOL;
        exit(0);
    } catch (\Exception $e) {
        echo "AWS SDK BedrockClient error: " . $e->getMessage() . PHP_EOL;
        echo "You can alternatively run the AWS CLI command shown below.\n";
    }
}

echo "Aws Bedrock client not available or failed. Run with AWS CLI:\n";
echo "aws bedrock list-models --region $region --output json" . PHP_EOL;

echo "\nLook for 'modelId' values and any fields mentioning 'invocation' or 'throughput' to identify on-demand supported models.\n";

?>
