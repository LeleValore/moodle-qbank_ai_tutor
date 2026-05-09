<?php
/**
 * Bedrock connector for qbank_genai plugin.
 *
 * Adapted from existing weaviate connector but simplified and namespaced for this plugin.
 * Uses AWS SignatureV4 (aws/aws-sdk-php) and Guzzle to sign and perform requests to Bedrock and S3.
 *
 * @package qbank_genai
 */

namespace qbank_genai\bedrock;

defined('MOODLE_INTERNAL') || die();

use Aws\Credentials\Credentials;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class connector {
    private string $region;
    private string $access_key;
    private string $secret_key;
    private string $knowledge_base_id;
    private string $chat_model_id;
    private string $data_source_id;
    private string $s3_bucket;
    private ?string $last_error = null;

    public function __construct(
        string $region,
        string $access_key,
        string $secret_key,
        string $knowledge_base_id = '',
        string $chat_model_id = '',
        string $data_source_id = '',
        string $s3_bucket = ''
    ) {
        $this->region = trim($region);
        $this->access_key = trim($access_key);
        $this->secret_key = trim($secret_key);
        $this->knowledge_base_id = trim($knowledge_base_id);
        $this->chat_model_id = trim($chat_model_id);
        $this->data_source_id = trim($data_source_id);
        $this->s3_bucket = trim($s3_bucket);
    }

    /**
     * Direct converse call to Bedrock Runtime.
     * Returns textual response or null on error.
     */
    public function direct_generate(string $question, string $task): ?string {
        $payload = [
            'system' => [
                ['text' => $task],
            ],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [['text' => $task]],
                ],
            ],
            'inferenceConfig' => [
                'maxTokens' => 700,
                'temperature' => 0.3,
            ],
        ];

        $path = '/model/' . rawurlencode($this->chat_model_id) . '/converse';
        $host = 'bedrock-runtime.' . $this->region . '.amazonaws.com';

        $result = $this->signed_json_request('bedrock', $host, $path, $payload, 'POST');
        if ($result === null) {
            return null;
        }

        $text = $result['output']['message']['content'][0]['text'] ?? null;
        if (!$text) {
            $this->last_error = 'Invalid response format from Bedrock runtime.';
            return null;
        }

        return trim($text);
    }

    /**
     * Retrieve-and-generate via Bedrock Agent (Knowledge Base).
     * Returns text or null on error.
     */
    public function retrieve_and_generate(array $payload): ?array {
        $host = 'bedrock-agent-runtime.' . $this->region . '.amazonaws.com';
        return $this->signed_json_request('bedrock', $host, '/retrieveAndGenerate', $payload, 'POST');
    }

    /**
     * Start ingestion job for knowledge base data source.
     */
    public function start_ingestion(string $knowledgeBaseId, string $dataSourceId): ?array {
        $host = 'bedrock-agent.' . $this->region . '.amazonaws.com';
        $path = '/knowledgebases/' . rawurlencode($knowledgeBaseId) . '/datasources/' . rawurlencode($dataSourceId) . '/ingestionjobs';
        $payload = ['knowledgeBaseId' => $knowledgeBaseId, 'dataSourceId' => $dataSourceId];
        return $this->signed_json_request('bedrock', $host, $path, $payload, 'PUT');
    }

    /**
     * Put object to S3 using SigV4-signed request.
     */
    public function s3_put_object(string $bucket, string $key, string $content, string $content_type = 'text/plain; charset=utf-8'): bool {
        $host = $bucket . '.s3.' . $this->region . '.amazonaws.com';
        $path = '/' . ltrim($key, '/');

        $url = 'https://' . $host . $path;
        $request = new Request('PUT', $url, ['Content-Type' => $content_type], $content);

        $signed = $this->sign_request($request, 's3');
        if ($signed === null) {
            return false;
        }

        try {
            $client = new Client(['timeout' => 120]);
            $response = $client->send($signed);
            $code = $response->getStatusCode();
            if ($code >= 200 && $code < 300) {
                return true;
            }
            $this->last_error = 'S3 PUT HTTP ' . $code . ': ' . (string)$response->getBody();
            return false;
        } catch (\Exception $e) {
            $this->last_error = 'S3 PUT error: ' . $e->getMessage();
            return false;
        }
    }

    /**
     * Delete object from S3.
     */
    public function s3_delete_object(string $bucket, string $key): bool {
        $host = $bucket . '.s3.' . $this->region . '.amazonaws.com';
        $path = '/' . ltrim($key, '/');

        $url = 'https://' . $host . $path;
        $request = new Request('DELETE', $url, [], '');

        $signed = $this->sign_request($request, 's3');
        if ($signed === null) {
            return false;
        }

        try {
            $client = new Client(['timeout' => 30]);
            $response = $client->send($signed);
            $code = $response->getStatusCode();
            if (in_array($code, [200, 204, 404])) {
                return true;
            }
            $this->last_error = 'S3 DELETE HTTP ' . $code . ': ' . (string)$response->getBody();
            return false;
        } catch (\Exception $e) {
            $this->last_error = 'S3 DELETE error: ' . $e->getMessage();
            return false;
        }
    }

    /**
     * General helper to sign and send JSON requests to Bedrock services.
     */
    private function signed_json_request(string $service, string $host, string $path, array $payload, string $method = 'POST'): ?array {
        if (!class_exists(SignatureV4::class)) {
            $this->last_error = 'AWS SDK (aws/aws-sdk-php) is required. Run: composer require aws/aws-sdk-php';
            return null;
        }

        $body = json_encode($payload);
        if ($body === false) {
            $this->last_error = 'json_encode error: ' . json_last_error_msg();
            return null;
        }

        $url = 'https://' . $host . $path;
        $request = new Request($method, $url, ['Content-Type' => 'application/json', 'Accept' => 'application/json'], $body);

        $signed = $this->sign_request($request, $service);
        if ($signed === null) {
            return null;
        }

        try {
            $client = new Client(['timeout' => 60]);
            $response = $client->send($signed);
            $httpcode = $response->getStatusCode();
            $respbody = (string)$response->getBody();
        } catch (\Exception $e) {
            $this->last_error = 'HTTP request error: ' . $e->getMessage();
            return null;
        }

        if ($httpcode < 200 || $httpcode >= 300) {
            $this->last_error = 'HTTP error ' . $httpcode . ': ' . $respbody;
            return null;
        }

        if ($respbody === '') {
            $this->last_error = 'Empty response body from ' . $path;
            return null;
        }

        $decoded = json_decode($respbody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->last_error = 'json_decode error: ' . json_last_error_msg();
            return null;
        }

        return $decoded;
    }

    /**
     * Sign a PSR-7 request using SignatureV4 and return the signed request.
     */
    private function sign_request(Request $request, string $service): ?Request {
        if (!class_exists(SignatureV4::class)) {
            $this->last_error = 'AWS SDK (aws/aws-sdk-php) is required.';
            return null;
        }

        try {
            $credentials = new Credentials($this->access_key, $this->secret_key);
            $signer = new SignatureV4($service, $this->region);
            $signed = $signer->signRequest($request, $credentials);
            return $signed;
        } catch (\Exception $e) {
            $this->last_error = 'Signing error: ' . $e->getMessage();
            return null;
        }
    }

    /**
     * Return last error message.
     */
    public function get_last_error(): ?string {
        return $this->last_error;
    }
}
