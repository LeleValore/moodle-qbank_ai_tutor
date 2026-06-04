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
    private string $session_token;
    private string $knowledge_base_id;
    private string $chat_model_id;
    private string $data_source_id;
    private string $inference_profile_arn;
    private string $s3_bucket;
    private ?string $last_error = null;
    private ?int $last_http_code = null;
    private ?array $last_response_headers = null;
    private ?string $last_response_body = null;
    private ?string $last_request_method = null;
    private ?string $last_request_url = null;
    private ?array $last_request_headers = null;
    private ?string $last_request_body = null;
    private ?Request $last_signed_request = null;

    public function __construct(
        string $region,
        string $access_key,
        string $secret_key,
        string $knowledge_base_id = '',
        string $chat_model_id = '',
        string $data_source_id = '',
        string $s3_bucket = '',
        string $inference_profile_arn = '',
        string $session_token = ''
    ) {
        $this->region = $region;
        $this->access_key = $access_key;
        $this->secret_key = $secret_key;
        $this->session_token = $session_token;
        $this->knowledge_base_id = $knowledge_base_id;
        $this->chat_model_id = $chat_model_id;
        $this->data_source_id = $data_source_id;
        $this->s3_bucket = $s3_bucket;
        $this->inference_profile_arn = $inference_profile_arn;
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

        $identifier = !empty($this->inference_profile_arn) ? $this->inference_profile_arn : $this->chat_model_id;
        $path = '/model/' . rawurlencode($identifier) . '/converse';
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
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        if (!empty($this->inference_profile_arn)) {
            $headers['x-amzn-inference-profile-arn'] = $this->inference_profile_arn;
        }
        $request = new Request($method, $url, $headers, $body);

        $signed = $this->sign_request($request, $service);
        if ($signed === null) {
            return null;
        }

        // Save signed request info for diagnostics and curl reproduction.
        $this->last_signed_request = $signed;
        $this->last_request_method = $signed->getMethod();
        $this->last_request_url = (string)$signed->getUri();
        $this->last_request_headers = $signed->getHeaders();
        $this->last_request_body = (string)$signed->getBody();

        try {
            $client = new Client(['timeout' => 60]);
            $response = $client->send($signed);
            $this->last_http_code = $response->getStatusCode();
            $this->last_response_headers = $response->getHeaders();
            $this->last_response_body = (string)$response->getBody();
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Try to extract response body for better diagnostics.
            $resp = $e->getResponse();
            if ($resp) {
                $this->last_http_code = $resp->getStatusCode();
                $this->last_response_headers = $resp->getHeaders();
                $this->last_response_body = (string)$resp->getBody();
                $this->last_error = 'HTTP error ' . $this->last_http_code . ': ' . $this->last_response_body;
            } else {
                $this->last_error = 'HTTP request error: ' . $e->getMessage();
            }
            return null;
        } catch (\Exception $e) {
            $this->last_error = 'HTTP request error: ' . $e->getMessage();
            return null;
        }

        if ($this->last_http_code < 200 || $this->last_http_code >= 300) {
            $this->last_error = 'HTTP error ' . $this->last_http_code . ': ' . $this->last_response_body;
            return null;
        }

        if ($this->last_response_body === '') {
            $this->last_error = 'Empty response body from ' . $path;
            return null;
        }

        $decoded = json_decode($this->last_response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->last_error = 'json_decode error: ' . json_last_error_msg();
            return null;
        }

        return $decoded;
    }

    /**
     * Convert a PSR-7 Request to a curl command for reproduction.
     */
    private function request_to_curl(Request $request): string {
        $method = $request->getMethod();
        $uri = (string)$request->getUri();
        $headers = $request->getHeaders();
        $body = (string)$request->getBody();

        // Safely escape single quotes in body for shell single-quoted string.
        $body_escaped = str_replace("'", "'\"'\"'", $body);

        $cmd = "curl -i -X " . escapeshellarg($method) . " ";
        foreach ($headers as $k => $vals) {
            foreach ($vals as $v) {
                $cmd .= "-H '" . str_replace("'", "'\\''", $k . ': ' . $v) . "' ";
            }
        }
        if ($body !== '') {
            $cmd .= "--data-binary '" . $body_escaped . "' ";
        }
        $cmd .= "'" . str_replace("'", "'\\''", $uri) . "'";
        return $cmd;
    }

    /**
     * Return structured diagnostics for the last request/response.
     */
    public function get_last_diagnostics(): array {
        $mask_header = function($k, $v) {
            $lk = strtolower($k);
            if ($lk === 'authorization' || strpos($lk, 'x-amz-') === 0 || strpos($lk, 'x-amzn-') === 0) {
                return substr($v, 0, 16) . '...';
            }
            return $v;
        };

        $masked_request_headers = [];
        if (!empty($this->last_request_headers)) {
            foreach ($this->last_request_headers as $k => $vals) {
                $masked_vals = [];
                foreach ($vals as $v) {
                    $masked_vals[] = $mask_header($k, $v);
                }
                $masked_request_headers[$k] = $masked_vals;
            }
        }

        $masked_response_headers = [];
        if (!empty($this->last_response_headers)) {
            foreach ($this->last_response_headers as $k => $vals) {
                $masked_vals = [];
                foreach ($vals as $v) {
                    $masked_vals[] = $mask_header($k, $v);
                }
                $masked_response_headers[$k] = $masked_vals;
            }
        }

        $curl = $this->last_signed_request ? $this->request_to_curl($this->last_signed_request) : null;

        return [
            'error' => $this->last_error,
            'request' => [
                'method' => $this->last_request_method,
                'url' => $this->last_request_url,
                'headers_masked' => $masked_request_headers,
                'body_snippet' => $this->last_request_body !== null ? substr($this->last_request_body, 0, 2000) : null,
            ],
            'response' => [
                'status' => $this->last_http_code,
                'headers_masked' => $masked_response_headers,
                'body_snippet' => $this->last_response_body !== null ? substr($this->last_response_body, 0, 8000) : null,
            ],
            'curl' => $curl,
        ];
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
            $token = $this->session_token !== '' ? $this->session_token : null;
            $credentials = new Credentials($this->access_key, $this->secret_key, $token);
            $signer = new SignatureV4($service, $this->region);
            $signed = $signer->signRequest($request, $credentials);
            return $signed;
        } catch (\Exception $e) {
            $this->last_error = 'Signing error: ' . $e->getMessage();
            return null;
        }
    }

    /**
     * Probe a model by trying common endpoints (/converse and /invoke).
     * Returns an associative array with path => ['ok'=>bool, 'response'| 'error']
     */
    public function probe_model(string $modelid, string $prompt): array {
        $results = [];
        $host = 'bedrock-runtime.' . $this->region . '.amazonaws.com';

        $probeIdentifier = !empty($this->inference_profile_arn) ? $this->inference_profile_arn : $modelid;

        $paths = [
            '/model/' . rawurlencode($probeIdentifier) . '/converse' => [
                'payload' => [
                    'system' => [[ 'text' => 'Probe' ]],
                    'messages' => [[ 'role' => 'user', 'content' => [[ 'text' => $prompt ]] ]],
                    'inferenceConfig' => [ 'maxTokens' => 64, 'temperature' => 0.3 ],
                ],
                'method' => 'POST',
            ],
            '/model/' . rawurlencode($probeIdentifier) . '/invoke' => [
                'payload' => [ 'input' => $prompt ],
                'method' => 'POST',
            ],
        ];

        foreach ($paths as $path => $info) {
            $res = $this->signed_json_request('bedrock', $host, $path, $info['payload'], $info['method']);
            if ($res === null) {
                $results[$path] = ['ok' => false, 'error' => $this->last_error];
            } else {
                $results[$path] = ['ok' => true, 'response' => $res];
            }
        }

        return $results;
    }

    /**
     * Return last error message.
     */
    public function get_last_error(): ?string {
        return $this->last_error;
    }
}
