<?php
namespace LCSNG\Tools\AI;

/**
 * Class LCS_AIManager
 *
 * Central manager for all AI provider interactions within the LCS ecosystem.
 * Supports OpenAI, Google (Gemini/Imagen/Veo), Anthropic (Claude), and
 * Microsoft Azure OpenAI.
 *
 * Self-contained — owns its own HTTP transport layer (sendRequest) and carries
 * zero runtime dependencies on any other LCS class.
 *
 * Capabilities:
 *  - Text / content generation  → generateContent()
 *  - Image generation           → generateImage()
 *  - Audio / TTS generation     → generateAudio()
 *  - Video generation           → generateVideo()  +  pollVideoJob()
 *
 * Quick start:
 * ```php
 * // OpenAI (default)
 * $ai = new LCS_AIManager(getenv('OPENAI_API_KEY'));
 * echo $ai->ask('What is the capital of Nigeria?');
 * // "The capital of Nigeria is Abuja."
 *
 * // Anthropic
 * $ai = new LCS_AIManager(getenv('ANTHROPIC_API_KEY'), '2023-06-01');
 * $ai->setProvider('anthropic');
 * $ai->setModel('claude-sonnet-4-6');
 * $result = $ai->generateContent('Explain recursion in two sentences.');
 * echo $result['text'];
 *
 * // Google Gemini
 * $ai = new LCS_AIManager(getenv('GOOGLE_API_KEY'));
 * $ai->setProvider('google');
 * $ai->setModel('gemini-2.5-pro');
 * $result = $ai->generateContent('List three benefits of PHP 8.');
 * echo $result['text'];
 * ```
 *
 * @package LCSNG\Tools\AI
 */
final class LCS_AIManager
{
    use LLM_Configs;

    // =========================================================================
    // Public state properties
    // =========================================================================

    /** @var string|null Active AI provider: openai | google | anthropic | microsoft */
    public ?string $provider = null;

    /** @var string|null Active model identifier */
    public ?string $model = null;

    /** @var string|null User prompt for the next generation call */
    public ?string $prompt = null;

    /**
     * System-level instruction sent before conversation history and user prompt.
     * Mapped to the correct provider field internally:
     *  - OpenAI / Azure  → messages[0] role 'system'
     *  - Anthropic        → top-level 'system' key
     *  - Google           → 'systemInstruction' key
     *
     * @var string|null
     */
    public ?string $systemPrompt = null;

    /**
     * Model state filter: 'stable' | 'preview' | 'deprecated' | 'all'
     * Used when listing or auto-selecting models.
     *
     * @var string|null
     */
    public ?string $modelState = null;

    /** @var int|null Numeric model group key (see LLM_Configs::MODEL_GROUPS) */
    public ?int $modelGroup = null;

    // =========================================================================
    // Generation parameters
    // =========================================================================

    /**
     * Maximum tokens the model may generate.
     * Defaults to 1024. Null defers to the provider default.
     *
     * @var int|null
     */
    public ?int $maxTokens = 1024;

    /**
     * Sampling temperature (0.0 – 2.0).
     * Low = deterministic/factual. High = creative/varied.
     * Null defers to the provider default.
     *
     * @var float|null
     */
    public ?float $temperature = null;

    /**
     * Top-P nucleus sampling (0.0 – 1.0).
     * Alternative to temperature — use one, not both.
     * Null defers to the provider default.
     *
     * @var float|null
     */
    public ?float $topP = null;

    /**
     * Number of independent completions per request (1 – 10).
     * Results appear in the 'texts' response key.
     *
     * @var int
     */
    public int $n = 1;

    /**
     * Conversation history for multi-turn sessions.
     *
     * Shape: [['role' => 'user'|'assistant'|'system', 'content' => '...'], ...]
     *
     * Use chat() to let the class manage this automatically, or manage it
     * manually with addMessage() / setConversationHistory().
     *
     * @var array<int, array{role: string, content: string}>
     */
    public array $conversationHistory = [];

    // =========================================================================
    // Private credentials
    // =========================================================================

    /** @var string|null API key for the active provider */
    private ?string $apiKey = null;

    /**
     * API version string.
     *  - Anthropic: sent as 'anthropic-version' header (e.g. '2023-06-01')
     *  - OpenAI / Google: substituted into the endpoint URL where applicable
     *
     * @var string|null
     */
    private ?string $apiVersion = null;

    /** @var string|null Azure deployment name (microsoft provider only) */
    private ?string $azureDeploymentName = null;

    /** @var string|null Azure resource subdomain (microsoft provider only) */
    private ?string $azureResourceName = null;

    // =========================================================================
    // Constructor
    // =========================================================================

    /**
     * Create a new LCS_AIManager instance.
     *
     * @param string      $apiKey     API key for the chosen provider.
     * @param string|null $apiVersion Optional API version.
     *                                Required for Anthropic (e.g. '2023-06-01').
     */
    public function __construct(string $apiKey, ?string $apiVersion = null)
    {
        $this->apiKey     = $apiKey;
        $this->apiVersion = $apiVersion;

        $this->provider   = self::DEFAULT_PROVIDER;
        $this->model      = self::DEFAULT_MODEL;
        $this->modelState = self::DEFAULT_MODEL_STATE;
        $this->modelGroup = self::DEFAULT_MODEL_GROUP;
    }

    // =========================================================================
    // Setters — provider & model
    // =========================================================================

    /**
     * Set the active AI provider.
     *
     * Resets the model to the first entry in the new provider's default group.
     *
     * @param string $provider One of the values in LLM_Configs::PROVIDERS.
     *
     * @throws \InvalidArgumentException If the provider is not supported.
     */
    public function setProvider(string $provider): void
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new \InvalidArgumentException(
                "Unsupported provider: '$provider'. Supported: " . implode(', ', self::PROVIDERS)
            );
        }

        $this->provider = $provider;

        $defaultGroupName = self::MODEL_GROUPS[$provider][self::DEFAULT_MODEL_GROUP]
            ?? array_key_first(self::MODEL_GROUPS[$provider]);

        $groupModels      = self::MODELS[$provider][$defaultGroupName] ?? [];
        $this->model      = $groupModels[0] ?? null;
        $this->modelGroup = self::DEFAULT_MODEL_GROUP;
    }

    /**
     * Set the active model.
     *
     * Validates against every state list for the current provider so any known
     * model string (stable, preview, or deprecated) is accepted.
     *
     * @param string $model A model identifier from the current provider's catalogue.
     *
     * @throws \InvalidArgumentException If the model is not recognised.
     */
    public function setModel(string $model): void
    {
        $allModels = self::getModels($this->provider, 'all');

        if (!in_array($model, $allModels, true)) {
            throw new \InvalidArgumentException(
                "Unsupported model '$model' for provider '{$this->provider}'."
            );
        }

        $this->model = $model;
    }

    /**
     * Set the model state filter.
     *
     * @param string $modelState One of: 'stable', 'preview', 'deprecated', 'all'.
     *
     * @throws \InvalidArgumentException For unrecognised values.
     */
    public function setModelState(string $modelState): void
    {
        if (!in_array($modelState, self::MODEL_STATES, true)) {
            throw new \InvalidArgumentException(
                "Unsupported model state: '$modelState'. Valid: " . implode(', ', self::MODEL_STATES)
            );
        }

        $this->modelState = $modelState;
    }

    /**
     * Set the active model group.
     *
     * The model is auto-switched to the first entry in the new group.
     *
     * @param int $modelGroup Numeric key from MODEL_GROUPS for the current provider.
     *
     * @throws \InvalidArgumentException If the key does not exist.
     */
    public function setModelGroup(int $modelGroup): void
    {
        $validGroups = array_keys(self::MODEL_GROUPS[$this->provider] ?? []);

        if (!in_array($modelGroup, $validGroups, true)) {
            throw new \InvalidArgumentException(
                "Unsupported model group '$modelGroup' for provider '{$this->provider}'."
            );
        }

        $this->modelGroup = $modelGroup;

        $groupName   = self::MODEL_GROUPS[$this->provider][$modelGroup];
        $groupModels = self::MODELS[$this->provider][$groupName] ?? [];
        $this->model = $groupModels[0] ?? $this->model;
    }

    // =========================================================================
    // Setters — prompts
    // =========================================================================

    /**
     * Set the user prompt used when generateContent() is called with no argument.
     *
     * @param string $prompt The instruction or question to send.
     */
    public function setPrompt(string $prompt): void
    {
        $this->prompt = $prompt;
    }

    /**
     * Set the system-level instruction.
     *
     * @param string $systemPrompt High-level behavioural instruction for the model.
     */
    public function setSystemPrompt(string $systemPrompt): void
    {
        $this->systemPrompt = $systemPrompt;
    }

    // =========================================================================
    // Setters — generation parameters
    // =========================================================================

    /**
     * Set the maximum tokens to generate.
     *
     * @param int $maxTokens Positive integer; provider model caps apply.
     *
     * @throws \InvalidArgumentException If less than 1.
     */
    public function setMaxTokens(int $maxTokens): void
    {
        if ($maxTokens < 1) {
            throw new \InvalidArgumentException('maxTokens must be at least 1.');
        }
        $this->maxTokens = $maxTokens;
    }

    /**
     * Set the sampling temperature (0.0 – 2.0).
     *
     * @throws \InvalidArgumentException If outside 0.0 – 2.0.
     */
    public function setTemperature(float $temperature): void
    {
        if ($temperature < 0.0 || $temperature > 2.0) {
            throw new \InvalidArgumentException('Temperature must be between 0.0 and 2.0.');
        }
        $this->temperature = $temperature;
    }

    /**
     * Set the top-P nucleus sampling value (0.0 – 1.0).
     *
     * @throws \InvalidArgumentException If outside 0.0 – 1.0.
     */
    public function setTopP(float $topP): void
    {
        if ($topP < 0.0 || $topP > 1.0) {
            throw new \InvalidArgumentException('topP must be between 0.0 and 1.0.');
        }
        $this->topP = $topP;
    }

    /**
     * Set the number of completions to generate per request (1 – 10).
     *
     * @throws \InvalidArgumentException If outside 1 – 10.
     */
    public function setN(int $n): void
    {
        if ($n < 1 || $n > 10) {
            throw new \InvalidArgumentException('n must be between 1 and 10.');
        }
        $this->n = $n;
    }

    /**
     * Configure Microsoft Azure OpenAI credentials.
     *
     * Must be called before any generation when provider is 'microsoft'.
     *
     * Endpoint constructed as:
     * https://{resource}.openai.azure.com/openai/deployments/{deployment}/chat/completions?api-version={version}
     *
     * @param string $resourceName   Azure resource subdomain.
     * @param string $deploymentName Azure portal deployment name.
     * @param string $apiVersion     API version string, e.g. '2024-02-01'.
     */
    public function setAzureConfig(string $resourceName, string $deploymentName, string $apiVersion): void
    {
        $this->azureResourceName   = $resourceName;
        $this->azureDeploymentName = $deploymentName;
        $this->apiVersion          = $apiVersion;
    }

    // =========================================================================
    // Conversation management
    // =========================================================================

    /**
     * Append a message to the conversation history.
     *
     * @param string $role    'user' | 'assistant' | 'system'
     * @param string $content Message text.
     */
    public function addMessage(string $role, string $content): void
    {
        $this->conversationHistory[] = ['role' => $role, 'content' => $content];
    }

    /**
     * Replace the entire conversation history.
     *
     * @param array<int, array{role: string, content: string}> $history
     */
    public function setConversationHistory(array $history): void
    {
        $this->conversationHistory = $history;
    }

    /**
     * Clear all conversation history to start a fresh thread.
     */
    public function clearConversationHistory(): void
    {
        $this->conversationHistory = [];
    }

    // =========================================================================
    // Private — HTTP transport (self-contained, zero external dependencies)
    // =========================================================================

    /**
     * Send an HTTP request via cURL and return a normalised result array.
     *
     * Faithful port of LCS_Requests::send_curl() — same option keys, same
     * return shape — kept private so LCS_AIManager has no runtime dependencies.
     *
     * Option keys (UPPERCASE preferred; lowercase aliases accepted):
     *  METHOD          GET | POST | PUT | PATCH | DELETE  (auto-detected if omitted)
     *  HEADERS         string[]  Additional header strings
     *  TIMEOUT         int       Total timeout in seconds   (default 30)
     *  CONNECT_TIMEOUT int       Connect timeout in seconds (default 10)
     *  CURL            array     Raw CURLOPT_* => value overrides
     *
     * Return shape:
     * ```
     * [
     *   'success'    => bool,
     *   'http_code'  => int,
     *   'headers'    => array<string, string>,
     *   'body'       => string,
     *   'json'       => array|null,
     *   'error'      => string|null,
     *   'curl_errno' => int|null,
     * ]
     * ```
     *
     * @param string            $url
     * @param array|string|null $data    Body data. Arrays are JSON-encoded automatically.
     * @param array             $options
     *
     * @return array<string, mixed>
     */
    private function sendRequest(string $url, array|string|null $data = null, array $options = []): array
    {
        $method         = strtoupper((string)(
            $options['METHOD'] ?? $options['method'] ?? ($data === null ? 'GET' : 'POST')
        ));
        $timeout        = (int)($options['TIMEOUT']         ?? $options['timeout']         ?? 30);
        $connectTimeout = (int)($options['CONNECT_TIMEOUT'] ?? $options['connect_timeout'] ?? 10);
        $userHeaders    =       $options['HEADERS']         ?? $options['headers']         ?? [];
        $extraCurl      =       $options['CURL']            ?? $options['curl']            ?? [];

        // Build body / append query string
        $body = '';

        if (in_array($method, ['GET', 'DELETE'], true) && !empty($data) && is_array($data)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($data);

        } elseif (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            if (is_array($data)) {
                $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } elseif (is_string($data) && $data !== '') {
                $body = $data;
            }
        }

        // Headers: Content-Type added only when there is a body; user headers win
        $defaultHeaders = ['Accept: application/json'];
        if ($body !== '') {
            $defaultHeaders[] = 'Content-Type: application/json';
        }

        $merged = $this->mergeRequestHeaders($defaultHeaders, $userHeaders);

        // cURL options
        $curlOpts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_POSTREDIR      => CURL_REDIR_POST_ALL,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => $merged,
        ];

        switch ($method) {
            case 'GET':
                $curlOpts[CURLOPT_HTTPGET] = true;
                break;

            case 'POST':
                $curlOpts[CURLOPT_POST] = true;
                if ($body !== '') {
                    $curlOpts[CURLOPT_POSTFIELDS] = $body;
                }
                break;

            default:
                $curlOpts[CURLOPT_CUSTOMREQUEST] = $method;
                if ($body !== '') {
                    $curlOpts[CURLOPT_POSTFIELDS] = $body;
                }
        }

        foreach ($extraCurl as $k => $v) {
            $curlOpts[(int)$k] = $v;
        }

        $ch = curl_init();
        curl_setopt_array($ch, $curlOpts);

        $raw        = curl_exec($ch);
        $curlErrNo  = curl_errno($ch);
        $curlErr    = $curlErrNo ? curl_error($ch) : null;
        $httpCode   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($curlErrNo) {
            return [
                'success'    => false,
                'http_code'  => $httpCode,
                'headers'    => [],
                'body'       => '',
                'json'       => null,
                'error'      => $curlErr,
                'curl_errno' => $curlErrNo,
            ];
        }

        $rawHeaders = substr($raw, 0, $headerSize);
        $bodyRaw    = substr($raw, $headerSize);

        $decoded = json_decode($bodyRaw, true);
        $json    = (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;

        return [
            'success'    => true,
            'http_code'  => $httpCode,
            'headers'    => $this->parseResponseHeaders($rawHeaders),
            'body'       => $bodyRaw,
            'json'       => $json,
            'error'      => null,
            'curl_errno' => null,
        ];
    }

    /**
     * Merge two header arrays; user headers win on name collision (case-insensitive).
     *
     * @param string[] $defaults
     * @param string[] $user
     *
     * @return string[]
     */
    private function mergeRequestHeaders(array $defaults, array $user): array
    {
        $map = [];
        $key = static fn(string $h): string => strtolower(trim(explode(':', $h, 2)[0]));

        foreach ($defaults as $h) { $map[$key($h)] = $h; }
        foreach ($user    as $h) { $map[$key($h)] = $h; }

        return array_values($map);
    }

    /**
     * Parse the raw cURL header block into an associative array.
     * Takes only the last block when a redirect chain is present.
     *
     * @param string $rawHeaders
     *
     * @return array<string, string>
     */
    private function parseResponseHeaders(string $rawHeaders): array
    {
        $headers = [];
        $blocks  = preg_split('/\r\n\r\n/', trim($rawHeaders));
        $last    = array_pop($blocks);
        $lines   = preg_split('/\r\n/', $last);
        array_shift($lines); // remove HTTP status line

        foreach ($lines as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value]       = explode(':', $line, 2);
            $headers[trim($name)] = trim($value);
        }

        return $headers;
    }

    // =========================================================================
    // Private — endpoint resolution
    // =========================================================================

    /**
     * Resolve the fully-formed API endpoint URL for the current provider.
     *
     * Substitutes {{MODEL}} and {{VERSION}} placeholders from API_ENDPOINTS.
     * For Microsoft Azure the URL is constructed from setAzureConfig() values.
     *
     * @return string
     *
     * @throws \RuntimeException If the endpoint cannot be determined.
     */
    private function getEndpoint(): string
    {
        if ($this->provider === 'microsoft') {
            if (
                empty($this->azureResourceName) ||
                empty($this->azureDeploymentName) ||
                empty($this->apiVersion)
            ) {
                throw new \RuntimeException(
                    'Microsoft Azure requires resourceName, deploymentName, and apiVersion. ' .
                    'Call setAzureConfig() first.'
                );
            }

            return sprintf(
                'https://%s.openai.azure.com/openai/deployments/%s/chat/completions?api-version=%s',
                $this->azureResourceName,
                $this->azureDeploymentName,
                $this->apiVersion
            );
        }

        $template = self::API_ENDPOINTS[$this->provider] ?? null;

        if (empty($template)) {
            throw new \RuntimeException(
                "No API endpoint configured for provider: '{$this->provider}'"
            );
        }

        return str_replace(
            ['{{VERSION}}', '{{MODEL}}'],
            [$this->apiVersion ?? '', $this->model ?? ''],
            $template
        );
    }

    // =========================================================================
    // Private — header construction
    // =========================================================================

    /**
     * Build provider-specific authentication headers.
     *
     *  openai    → Authorization: Bearer {key}
     *  google    → Authorization: Bearer {key}
     *  anthropic → x-api-key: {key}  +  anthropic-version: {version}
     *  microsoft → api-key: {key}
     *
     * @return string[]
     */
    private function buildHeaders(): array
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        switch ($this->provider) {
            case 'openai':
            case 'google':
                $headers[] = 'Authorization: Bearer ' . $this->apiKey;
                break;

            case 'anthropic':
                $headers[] = 'x-api-key: ' . $this->apiKey;
                $headers[] = 'anthropic-version: ' . ($this->apiVersion ?? '2023-06-01');
                break;

            case 'microsoft':
                $headers[] = 'api-key: ' . $this->apiKey;
                break;
        }

        return $headers;
    }

    // =========================================================================
    // Private — payload builders
    // =========================================================================

    /**
     * Build the provider-specific JSON payload for a text generation request.
     *
     * Provider payload formats:
     *
     *  OpenAI / Azure (Chat Completions API — POST /v1/chat/completions)
     *  ─────────────────────────────────────────────────────────────────
     *  Endpoint: https://api.openai.com/v1/chat/completions
     *  Body:     { model, messages[], n, max_tokens, temperature, top_p }
     *  Response: { choices[{ message: { content } }], usage }
     *
     *  Anthropic (Messages API — POST /v1/messages)
     *  ────────────────────────────────────────────
     *  Body:     { model, max_tokens, messages[], system }
     *  Response: { content[{ type, text }], usage }
     *
     *  Google (Generative Language API — POST /v1beta/models/{model}:generateContent)
     *  ───────────────────────────────────────────────────────────────────────────────
     *  Body:     { contents[], systemInstruction, generationConfig }
     *  Response: { candidates[{ content: { parts[{ text }] } }], usageMetadata }
     *
     * @param string $userContent The resolved user message for this turn.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException For unknown providers.
     */
    private function buildContentPayload(string $userContent): array
    {
        switch ($this->provider) {

            // -----------------------------------------------------------------
            // OpenAI Chat Completions API  &  Azure OpenAI
            // POST https://api.openai.com/v1/chat/completions
            // -----------------------------------------------------------------
            case 'openai':
            case 'microsoft':
                $messages = [];

                if (!empty($this->systemPrompt)) {
                    $messages[] = ['role' => 'system', 'content' => $this->systemPrompt];
                }
                foreach ($this->conversationHistory as $msg) {
                    $messages[] = $msg;
                }
                $messages[] = ['role' => 'user', 'content' => $userContent];

                $payload = [
                    'model'    => $this->model,
                    'messages' => $messages,
                    'n'        => $this->n,
                ];

                if ($this->maxTokens  !== null) { $payload['max_tokens']  = $this->maxTokens;  }
                if ($this->temperature !== null) { $payload['temperature'] = $this->temperature; }
                if ($this->topP        !== null) { $payload['top_p']       = $this->topP;        }

                return $payload;

            // -----------------------------------------------------------------
            // Anthropic Messages API
            // POST https://api.anthropic.com/v1/messages
            // -----------------------------------------------------------------
            case 'anthropic':
                $messages = [];

                foreach ($this->conversationHistory as $msg) {
                    if ($msg['role'] !== 'system') { // Anthropic messages[] rejects 'system' role
                        $messages[] = $msg;
                    }
                }
                $messages[] = ['role' => 'user', 'content' => $userContent];

                $payload = [
                    'model'      => $this->model,
                    'max_tokens' => $this->maxTokens ?? 1024,
                    'messages'   => $messages,
                ];

                if (!empty($this->systemPrompt)) {
                    $payload['system'] = $this->systemPrompt;
                }
                if ($this->temperature !== null) { $payload['temperature'] = $this->temperature; }
                if ($this->topP        !== null) { $payload['top_p']       = $this->topP;        }

                return $payload;

            // -----------------------------------------------------------------
            // Google Generative Language API
            // POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
            // -----------------------------------------------------------------
            case 'google':
                $contents = [];

                foreach ($this->conversationHistory as $msg) {
                    $role       = ($msg['role'] === 'assistant') ? 'model' : 'user';
                    $contents[] = ['role' => $role, 'parts' => [['text' => $msg['content']]]];
                }
                $contents[] = ['role' => 'user', 'parts' => [['text' => $userContent]]];

                $payload = ['contents' => $contents];

                if (!empty($this->systemPrompt)) {
                    $payload['systemInstruction'] = ['parts' => [['text' => $this->systemPrompt]]];
                }

                $gc = [];
                if ($this->maxTokens  !== null) { $gc['maxOutputTokens'] = $this->maxTokens;  }
                if ($this->temperature !== null) { $gc['temperature']     = $this->temperature; }
                if ($this->topP        !== null) { $gc['topP']            = $this->topP;        }
                if ($this->n > 1)               { $gc['candidateCount']  = $this->n;           }

                if (!empty($gc)) {
                    $payload['generationConfig'] = $gc;
                }

                return $payload;

            // -----------------------------------------------------------------
            default:
                throw new \RuntimeException(
                    "buildContentPayload(): no payload builder for provider '{$this->provider}'"
                );
        }
    }

    // =========================================================================
    // Private — response normalisation
    // =========================================================================

    /**
     * Map a raw sendRequest() result to the library's normalised response shape.
     *
     * Callers never need to inspect the provider to read a response — every
     * generate*() method returns this same structure.
     *
     * Shape:
     * ```
     * [
     *   'success'    => bool,
     *   'text'       => string,        // primary generated text
     *   'texts'      => string[],      // all candidates when n > 1
     *   'usage'      => [
     *       'prompt_tokens'     => int,
     *       'completion_tokens' => int,
     *       'total_tokens'      => int,
     *   ],
     *   'raw'        => array,         // full decoded JSON from the provider
     *   'http_code'  => int,
     *   'error'      => string|null,
     * ]
     * ```
     *
     * @param array $curlResult Return value of sendRequest().
     *
     * @return array<string, mixed>
     */
    private function normaliseResponse(array $curlResult): array
    {
        $base = [
            'success'   => false,
            'text'      => '',
            'texts'     => [],
            'usage'     => [],
            'raw'       => $curlResult['json'] ?? [],
            'http_code' => $curlResult['http_code'] ?? 0,
            'error'     => $curlResult['error'] ?? null,
        ];

        // Transport-level failure (cURL error)
        if (!empty($curlResult['error'])) {
            return $base;
        }

        $json     = $curlResult['json'] ?? [];
        $httpCode = (int)($curlResult['http_code'] ?? 0);

        // HTTP-level failure or empty / non-JSON body
        if ($httpCode < 200 || $httpCode >= 300 || empty($json)) {
            $base['error'] = $json['error']['message']
                ?? $json['message']
                ?? ('HTTP ' . $httpCode . ': unexpected response');
            return $base;
        }

        $texts = [];

        switch ($this->provider) {

            // OpenAI Chat Completions response:
            // { choices: [{ message: { role, content } }], usage: { ... } }
            case 'openai':
            case 'microsoft':
                foreach ($json['choices'] ?? [] as $choice) {
                    $texts[] = $choice['message']['content'] ?? $choice['text'] ?? '';
                }
                $base['usage'] = [
                    'prompt_tokens'     => $json['usage']['prompt_tokens']     ?? 0,
                    'completion_tokens' => $json['usage']['completion_tokens'] ?? 0,
                    'total_tokens'      => $json['usage']['total_tokens']      ?? 0,
                ];
                break;

            // Anthropic Messages response:
            // { content: [{ type: 'text', text: '...' }], usage: { input_tokens, output_tokens } }
            case 'anthropic':
                foreach ($json['content'] ?? [] as $block) {
                    if (($block['type'] ?? '') === 'text') {
                        $texts[] = $block['text'] ?? '';
                    }
                }
                $base['usage'] = [
                    'prompt_tokens'     => $json['usage']['input_tokens']  ?? 0,
                    'completion_tokens' => $json['usage']['output_tokens'] ?? 0,
                    'total_tokens'      => ($json['usage']['input_tokens']  ?? 0)
                                        + ($json['usage']['output_tokens'] ?? 0),
                ];
                break;

            // Google generateContent response:
            // { candidates: [{ content: { parts: [{ text: '...' }] } }], usageMetadata: { ... } }
            case 'google':
                foreach ($json['candidates'] ?? [] as $candidate) {
                    foreach ($candidate['content']['parts'] ?? [] as $part) {
                        $texts[] = $part['text'] ?? '';
                    }
                }
                $base['usage'] = [
                    'prompt_tokens'     => $json['usageMetadata']['promptTokenCount']     ?? 0,
                    'completion_tokens' => $json['usageMetadata']['candidatesTokenCount'] ?? 0,
                    'total_tokens'      => $json['usageMetadata']['totalTokenCount']      ?? 0,
                ];
                break;
        }

        $base['success'] = true;
        $base['texts']   = $texts;
        $base['text']    = $texts[0] ?? '';

        return $base;
    }

    // =========================================================================
    // Public — content generation
    // =========================================================================

    /**
     * Generate text content using the configured AI provider.
     *
     * Three input styles:
     *
     * 1. No argument — reads $this->prompt:
     *    ```php
     *    $ai->setPrompt('Explain closures in PHP.');
     *    $result = $ai->generateContent();
     *    ```
     *
     * 2. String — used directly as the user message:
     *    ```php
     *    $result = $ai->generateContent('Explain closures in PHP.');
     *    ```
     *
     * 3. Messages array — fully replaces history and extracts roles:
     *    ```php
     *    $result = $ai->generateContent([
     *        ['role' => 'system',    'content' => 'Be concise.'],
     *        ['role' => 'user',      'content' => 'What is a closure?'],
     *        ['role' => 'assistant', 'content' => 'A closure captures its enclosing scope.'],
     *        ['role' => 'user',      'content' => 'Show me a PHP example.'],
     *    ]);
     *    ```
     *
     * @param int|string|array|null $input
     *
     * @return array<string, mixed> Normalised result.
     *
     * @throws \RuntimeException If no prompt is available or the provider is misconfigured.
     */
    public function generateContent(int|string|array|null $input = null): array
    {
        // --- Resolve user content -------------------------------------------
        if (is_string($input) || is_int($input)) {
            $userContent = (string)$input;

        } elseif (is_array($input)) {
            $userContent               = '';
            $this->conversationHistory = [];

            foreach ($input as $msg) {
                if (!isset($msg['role'], $msg['content'])) {
                    continue;
                }
                switch ($msg['role']) {
                    case 'system':
                        $this->systemPrompt = $msg['content'];
                        break;
                    case 'user':
                        $userContent = $msg['content'];
                        $this->addMessage('user', $msg['content']);
                        break;
                    case 'assistant':
                        $this->addMessage('assistant', $msg['content']);
                        break;
                }
            }

            // buildContentPayload() will re-append the last user message,
            // so pop it from history now to prevent duplication.
            if (
                !empty($this->conversationHistory) &&
                end($this->conversationHistory)['role'] === 'user'
            ) {
                array_pop($this->conversationHistory);
            }

        } else {
            $userContent = $this->prompt ?? '';
        }

        if (empty($userContent)) {
            throw new \RuntimeException(
                'generateContent() requires a prompt. Set $this->prompt or pass input as argument.'
            );
        }

        // --- Build & send ---------------------------------------------------
        $result = $this->sendRequest(
            $this->getEndpoint(),
            $this->buildContentPayload($userContent),
            ['METHOD' => 'POST', 'HEADERS' => $this->buildHeaders(), 'TIMEOUT' => 60]
        );

        return $this->normaliseResponse($result);
    }

    /**
     * Generate one or more images from a text prompt.
     *
     * Supported: openai (DALL-E / gpt-image-1), google (Imagen).
     *
     * ```php
     * // String prompt
     * $result = $ai->generateImage('A sunset over Lagos lagoon');
     *
     * // Options array
     * $result = $ai->generateImage([
     *     'prompt'  => 'A sunset over Lagos lagoon',
     *     'size'    => '1792x1024',
     *     'style'   => 'vivid',    // OpenAI only
     *     'quality' => 'hd',       // OpenAI only
     *     'n'       => 2,
     * ]);
     * ```
     *
     * @param int|string|array|null $input
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException If no prompt or unsupported provider.
     */
    public function generateImage(int|string|array|null $input = null): array
    {
        $prompt  = $this->prompt ?? '';
        $options = [];

        if (is_string($input) || is_int($input)) {
            $prompt = (string)$input;
        } elseif (is_array($input)) {
            $prompt  = $input['prompt'] ?? $prompt;
            $options = $input;
        }

        if (empty($prompt)) {
            throw new \RuntimeException(
                'generateImage() requires a prompt. Set $this->prompt or pass it as argument.'
            );
        }

        switch ($this->provider) {
            case 'openai':
                $endpoint = 'https://api.openai.com/v1/images/generations';
                $payload  = [
                    'model'   => $this->model ?? 'dall-e-3',
                    'prompt'  => $prompt,
                    'n'       => $options['n']       ?? $this->n,
                    'size'    => $options['size']    ?? '1024x1024',
                    'style'   => $options['style']   ?? 'vivid',
                    'quality' => $options['quality'] ?? 'standard',
                ];
                break;

            case 'google':
                $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' .
                            ($this->model ?? 'imagen-4.0-generate-001') . ':predict';
                $payload  = [
                    'instances'  => [['prompt' => $prompt]],
                    'parameters' => ['sampleCount' => $options['n'] ?? $this->n],
                ];
                break;

            default:
                throw new \RuntimeException(
                    "generateImage() is not supported for provider '{$this->provider}'."
                );
        }

        $result   = $this->sendRequest($endpoint, $payload, [
            'METHOD'  => 'POST',
            'HEADERS' => $this->buildHeaders(),
            'TIMEOUT' => 120,
        ]);

        $json     = $result['json'] ?? [];
        $httpCode = (int)($result['http_code'] ?? 0);

        $base = [
            'success'   => false,
            'images'    => [],
            'http_code' => $httpCode,
            'error'     => $result['error'] ?? null,
            'raw'       => $json,
        ];

        if (!empty($result['error']) || $httpCode < 200 || $httpCode >= 300) {
            $base['error'] = $result['error']
                ?? $json['error']['message']
                ?? ('HTTP ' . $httpCode);
            return $base;
        }

        $images = [];
        if ($this->provider === 'openai') {
            foreach ($json['data'] ?? [] as $item) {
                $images[] = ['url' => $item['url'] ?? null, 'b64_json' => $item['b64_json'] ?? null];
            }
        } elseif ($this->provider === 'google') {
            foreach ($json['predictions'] ?? [] as $pred) {
                $images[] = ['b64_json' => $pred['bytesBase64Encoded'] ?? null];
            }
        }

        $base['success'] = true;
        $base['images']  = $images;

        return $base;
    }

    /**
     * Convert text to speech.
     *
     * Supported: openai (tts-1, tts-1-hd, gpt-4o-mini-tts).
     *
     * ```php
     * $result = $ai->generateAudio('Welcome to the LCS platform.');
     *
     * $result = $ai->generateAudio([
     *     'input'           => 'Hello world',
     *     'voice'           => 'nova',   // alloy|echo|fable|onyx|nova|shimmer
     *     'response_format' => 'opus',   // mp3|opus|aac|flac
     *     'speed'           => 0.9,      // 0.25 – 4.0
     * ]);
     *
     * file_put_contents('out.' . $result['format'], $result['audio_binary']);
     * ```
     *
     * @param int|string|array|null $input
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException If provider unsupported or no text available.
     */
    public function generateAudio(int|string|array|null $input = null): array
    {
        $text    = $this->prompt ?? '';
        $options = [];

        if (is_string($input) || is_int($input)) {
            $text = (string)$input;
        } elseif (is_array($input)) {
            $text    = $input['input'] ?? $input['text'] ?? $text;
            $options = $input;
        }

        if (empty($text)) {
            throw new \RuntimeException(
                'generateAudio() requires input text. Set $this->prompt or pass it as argument.'
            );
        }

        switch ($this->provider) {
            case 'openai':
                $endpoint = 'https://api.openai.com/v1/audio/speech';
                $payload  = [
                    'model'           => $this->model ?? 'tts-1',
                    'input'           => $text,
                    'voice'           => $options['voice']           ?? 'alloy',
                    'response_format' => $options['response_format'] ?? 'mp3',
                    'speed'           => $options['speed']           ?? 1.0,
                ];
                break;

            default:
                throw new \RuntimeException(
                    "generateAudio() is not yet supported for provider '{$this->provider}'."
                );
        }

        $result   = $this->sendRequest($endpoint, $payload, [
            'METHOD'  => 'POST',
            'HEADERS' => $this->buildHeaders(),
            'TIMEOUT' => 60,
        ]);

        $httpCode = (int)($result['http_code'] ?? 0);
        $format   = $options['response_format'] ?? 'mp3';

        $base = [
            'success'      => false,
            'audio_binary' => '',
            'format'       => $format,
            'http_code'    => $httpCode,
            'error'        => $result['error'] ?? null,
            'raw'          => $result['json'] ?? [],
        ];

        if (!empty($result['error']) || $httpCode < 200 || $httpCode >= 300) {
            $errJson       = $result['json'] ?? [];
            $base['error'] = $result['error']
                ?? $errJson['error']['message']
                ?? ('HTTP ' . $httpCode);
            return $base;
        }

        $base['success']      = true;
        $base['audio_binary'] = $result['body'] ?? ''; // audio is raw binary, not JSON

        return $base;
    }

    /**
     * Submit a video generation job (async).
     *
     * Supported: openai (Sora), google (Veo).
     * Use pollVideoJob() to check completion status.
     *
     * ```php
     * $result = $ai->generateVideo('A drone shot over Victoria Island at golden hour');
     *
     * $result = $ai->generateVideo([
     *     'prompt'   => 'Storm clouds forming over the Atlantic',
     *     'duration' => 8,
     *     'size'     => '1280x720',
     *     'n'        => 1,
     * ]);
     * ```
     *
     * @param int|string|array|null $input
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException If provider unsupported or no prompt available.
     */
    public function generateVideo(int|string|array|null $input = null): array
    {
        $prompt  = $this->prompt ?? '';
        $options = [];

        if (is_string($input) || is_int($input)) {
            $prompt = (string)$input;
        } elseif (is_array($input)) {
            $prompt  = $input['prompt'] ?? $prompt;
            $options = $input;
        }

        if (empty($prompt)) {
            throw new \RuntimeException(
                'generateVideo() requires a prompt. Set $this->prompt or pass it as argument.'
            );
        }

        switch ($this->provider) {
            case 'openai':
                $endpoint = 'https://api.openai.com/v1/video/generations';
                $payload  = [
                    'model'    => $this->model ?? 'sora-2',
                    'prompt'   => $prompt,
                    'n'        => $options['n']        ?? 1,
                    'duration' => $options['duration'] ?? 5,
                    'size'     => $options['size']     ?? '1280x720',
                ];
                break;

            case 'google':
                $endpoint = sprintf(
                    'https://generativelanguage.googleapis.com/v1beta/models/%s:generateVideo',
                    $this->model ?? 'veo-3.0-generate-preview'
                );
                $payload  = [
                    'prompt'          => $prompt,
                    'sampleCount'     => $options['n']        ?? 1,
                    'durationSeconds' => $options['duration'] ?? 5,
                ];
                break;

            default:
                throw new \RuntimeException(
                    "generateVideo() is not yet supported for provider '{$this->provider}'."
                );
        }

        $result   = $this->sendRequest($endpoint, $payload, [
            'METHOD'  => 'POST',
            'HEADERS' => $this->buildHeaders(),
            'TIMEOUT' => 60,
        ]);

        $json     = $result['json'] ?? [];
        $httpCode = (int)($result['http_code'] ?? 0);

        $base = [
            'success'   => false,
            'job_id'    => null,
            'status'    => null,
            'video_url' => null,
            'http_code' => $httpCode,
            'error'     => $result['error'] ?? null,
            'raw'       => $json,
        ];

        if (!empty($result['error']) || $httpCode < 200 || $httpCode >= 300) {
            $base['error'] = $result['error']
                ?? $json['error']['message']
                ?? ('HTTP ' . $httpCode);
            return $base;
        }

        $base['success'] = true;
        $base['job_id']  = $json['id'] ?? $json['name'] ?? null;
        $base['status']  = $json['status'] ?? 'queued';

        if (!empty($json['data'][0]['url'])) {
            $base['video_url'] = $json['data'][0]['url'];
            $base['status']    = 'completed';
        }

        return $base;
    }

    /**
     * Poll an async video generation job for its current status.
     *
     * ```php
     * $job = $ai->generateVideo('...');
     * for ($i = 0; $i < 30; $i++) {
     *     sleep(10);
     *     $poll = $ai->pollVideoJob($job['job_id']);
     *     if ($poll['status'] === 'completed') {
     *         echo $poll['video_url'];
     *         break;
     *     }
     * }
     * ```
     *
     * @param string $jobId Value from generateVideo()['job_id'].
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException If provider does not support polling.
     */
    public function pollVideoJob(string $jobId): array
    {
        switch ($this->provider) {
            case 'openai':
                $endpoint = 'https://api.openai.com/v1/video/generations/' . urlencode($jobId);
                break;

            case 'google':
                $endpoint = 'https://generativelanguage.googleapis.com/v1beta/' . ltrim($jobId, '/');
                break;

            default:
                throw new \RuntimeException(
                    "pollVideoJob() is not supported for provider '{$this->provider}'."
                );
        }

        $result   = $this->sendRequest($endpoint, null, [
            'METHOD'  => 'GET',
            'HEADERS' => $this->buildHeaders(),
            'TIMEOUT' => 30,
        ]);

        $json     = $result['json'] ?? [];
        $httpCode = (int)($result['http_code'] ?? 0);

        $base = [
            'success'   => false,
            'status'    => null,
            'video_url' => null,
            'http_code' => $httpCode,
            'error'     => $result['error'] ?? null,
            'raw'       => $json,
        ];

        if (!empty($result['error']) || $httpCode < 200 || $httpCode >= 300) {
            $base['error'] = $result['error']
                ?? $json['error']['message']
                ?? ('HTTP ' . $httpCode);
            return $base;
        }

        $base['success'] = true;
        $base['status']  = $json['status'] ?? (($json['done'] ?? false) ? 'completed' : 'processing');

        if (!empty($json['data'][0]['url'])) {
            $base['video_url'] = $json['data'][0]['url'];
        } elseif (!empty($json['response']['videos'][0]['uri'])) {
            $base['video_url'] = $json['response']['videos'][0]['uri'];
        }

        return $base;
    }

    // =========================================================================
    // Public — convenience helpers
    // =========================================================================

    /**
     * One-shot question with zero state mutation.
     *
     * Sends a question and returns the plain text answer. Neither $this->prompt
     * nor $this->conversationHistory is affected — state is fully restored.
     *
     * Returns an empty string on API-level failure; never throws for those.
     *
     * ```php
     * echo $ai->ask('What is the capital of Nigeria?');
     * // "The capital of Nigeria is Abuja."
     * ```
     *
     * @param string $question
     *
     * @return string Generated text or empty string on failure.
     */
    public function ask(string $question): string
    {
        $savedPrompt  = $this->prompt;
        $savedHistory = $this->conversationHistory;

        $this->prompt              = $question;
        $this->conversationHistory = [];

        $result = $this->generateContent();

        $this->prompt              = $savedPrompt;
        $this->conversationHistory = $savedHistory;

        return $result['success'] ? ($result['text'] ?? '') : '';
    }

    /**
     * Send a message and automatically maintain multi-turn context.
     *
     * Appends both the user turn and assistant reply to conversationHistory
     * after each call, so subsequent calls carry the full thread.
     *
     * ```php
     * $ai->setSystemPrompt('You are a football analyst.');
     * $ai->chat('Who leads EFL League One?');
     * $ai->chat('How have they performed away from home?');
     * ```
     *
     * @param string $userMessage
     *
     * @return array<string, mixed> Same shape as generateContent().
     */
    public function chat(string $userMessage): array
    {
        $this->prompt = $userMessage;
        $result       = $this->generateContent();

        $this->addMessage('user', $userMessage);
        if ($result['success'] && !empty($result['text'])) {
            $this->addMessage('assistant', $result['text']);
        }

        return $result;
    }

    // =========================================================================
    // Public — introspection & utilities
    // =========================================================================

    /**
     * Return a snapshot of the current instance configuration.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return [
            'provider'        => $this->provider,
            'model'           => $this->model,
            'modelState'      => $this->modelState,
            'modelGroup'      => $this->modelGroup,
            'maxTokens'       => $this->maxTokens,
            'temperature'     => $this->temperature,
            'topP'            => $this->topP,
            'n'               => $this->n,
            'hasSystemPrompt' => !empty($this->systemPrompt),
            'hasPrompt'       => !empty($this->prompt),
            'historyLength'   => count($this->conversationHistory),
            'hasAzureConfig'  => !empty($this->azureResourceName),
            'apiVersionSet'   => !empty($this->apiVersion),
        ];
    }

    /**
     * List all model identifiers for the current provider + modelState filter.
     *
     * @return string[]
     */
    public function listModels(): array
    {
        return self::getModels($this->provider, $this->modelState ?? 'all');
    }

    /**
     * List all models for the current provider grouped by family name.
     *
     * @return array<string, string[]>
     */
    public function listModelsByGroup(): array
    {
        $stateKey = match ($this->modelState) {
            'stable'     => 'STABLE_MODELS',
            'preview'    => 'PREVIEW_MODELS',
            'deprecated' => 'DEPRECATED_MODELS',
            default      => 'MODELS',
        };

        return self::$$stateKey[$this->provider] ?? [];
    }

    /**
     * Return true if the active model belongs to the named family/group.
     *
     * ```php
     * $ai->setModel('dall-e-3');
     * $ai->modelBelongsToGroup('image');    // true
     * $ai->modelBelongsToGroup('frontier'); // false
     * ```
     *
     * @param string $groupName e.g. 'image', 'frontier', 'claude'
     *
     * @return bool
     */
    public function modelBelongsToGroup(string $groupName): bool
    {
        return self::getModelGroup($this->model ?? '') === $groupName;
    }

    /**
     * Reset all prompt, conversation, and generation state to defaults.
     *
     * Provider, model, and API credentials are NOT changed.
     */
    public function reset(): void
    {
        $this->prompt              = null;
        $this->systemPrompt        = null;
        $this->conversationHistory = [];
        $this->maxTokens           = 1024;
        $this->temperature         = null;
        $this->topP                = null;
        $this->n                   = 1;
        $this->modelState          = self::DEFAULT_MODEL_STATE;
        $this->modelGroup          = self::DEFAULT_MODEL_GROUP;
    }
}