<?php
namespace qbank_genai\bedrock;

defined('MOODLE_INTERNAL') || die();

use Aws\Credentials\Credentials;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

/**
 * Simple Bedrock adapter that signs requests with SigV4 and invokes a model.
 * Requires aws/aws-sdk-php and guzzlehttp/guzzle installed via Composer.
 */
class adapter {
    private $region;
    private $modelId;
    private $accessKey;
    private $secretKey;
    private $endpoint;

    public function __construct(string $region, string $modelId, ?string $accessKey = null, ?string $secretKey = null, ?string $endpoint = null) {
        $this->region = $region;
        $this->modelId = $modelId;
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->endpoint = $endpoint ?: 'https://bedrock.' . $region . '.amazonaws.com';
    }

    /**
     * Invoke the Bedrock model with a plain prompt string.
     * Returns the raw response body as string.
     *
     * @param string $prompt
     * @param array $options
     * @return string
     * @throws \Exception
     */
    public function invoke(string $prompt, array $options = []): string {
        // Build request body. Different Bedrock models may expect different shapes.
        $body = json_encode(['input' => ['text' => $prompt]]);

        $url = rtrim($this->endpoint, '/') . '/models/' . $this->modelId . '/invoke';

        $request = new Request('POST', $url, ['Content-Type' => 'application/json', 'Accept' => 'application/json'], $body);

        if (!class_exists(SignatureV4::class)) {
            throw new \Exception('AWS SDK (aws/aws-sdk-php) is required for Bedrock calls. Run: composer require aws/aws-sdk-php');
        }

        if ($this->accessKey && $this->secretKey) {
            $credentials = new Credentials($this->accessKey, $this->secretKey);
        } else {
            throw new \Exception('AWS credentials not provided. Configure AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY or pass them to the adapter.');
        }

        $signer = new SignatureV4('bedrock', $this->region);
        $signedRequest = $signer->signRequest($request, $credentials);

        $client = new Client(['timeout' => $options['timeout'] ?? 120]);
        $response = $client->send($signedRequest);

        return (string)$response->getBody();
    }
}
