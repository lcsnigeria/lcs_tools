<?php
namespace LCSNG\Tools\AI;

use LCSNG\Tools\Requests\LCS_Requests;

/**
 * Class LCS_AIManager
 *
 * Central manager for all AI provider interactions within the LCS ecosystem.
 * Supports OpenAI, Google, Anthropic, and Microsoft Azure OpenAI providers.
 *
 * This class acts as a unified interface for:
 *  - Text/content generation  (generateContent)
 *  - Image generation          (generateImage)
 *  - Audio generation/TTS      (generateAudio)
 *  - Video generation          (generateVideo)
 *
 * Provider-specific request payloads, headers, and response normalisation are
 * handled internally so callers can switch providers without changing their code.
 *
 * Usage example:
 * ```php
 * $ai = new LCS_AIManager('sk-...', '2023-06-01');   // Anthropic needs apiVersion
 * $ai->setProvider('anthropic');
 * $ai->setModel('claude-sonnet-4-6');
 * $ai->setSystemPrompt('You are a helpful assistant.');
 * $ai->setPrompt('Explain photosynthesis in simple terms.');
 *
 * $result = $ai->generateContent();
 * echo $result['text'];
 * ```
 *
 * @package LCSNG\Tools\AI
 */
final class LCS_AIManager
{
    use LLM_Configs;

    // -------------------------------------------------------------------------
    // Public state properties
    // -------------------------------------------------------------------------

    /** @var string|null Active AI provider (openai | google | anthropic | microsoft) */
    public ?string $provider = null;

    /** @var string|null Active model identifier */
    public ?string $model = null;

    /** @var string|null The user prompt / instruction to send to the model */
    public ?string $prompt = null;

    /** @var string|null An optional system-level prompt (supported by most providers) */
    public ?string $systemPrompt = null;

    /**
     * Model state filter: 'stable' | 'preview' | 'deprecated' | 'all'
     *
     * Used when auto-selecting or validating models by state.
     *
     * @var string|null
     */
    public ?string $modelState = null;

    /** @var int|null Numeric model group key (maps to MODEL_GROUPS in LLM_Configs) */
    public ?int $modelGroup = null;

    // -------------------------------------------------------------------------
    // Generation parameter properties
    // -------------------------------------------------------------------------

    /**
     * Maximum number of tokens the model may generate in a single response.
     * Defaults to 1024. Set to null to use the provider's own default.
     *
     * @var int|null
     */
    public ?int $maxTokens = 1024;

    /**
     * Sampling temperature (0.0 – 2.0).
     * Higher values produce more creative/random output; lower values are more
     * deterministic. Set to null to use the provider default.
     *
     * @var float|null
     */
    public ?float $temperature = null;

    /**
     * Top-P nucleus sampling (0.0 – 1.0).
     * An alternative to temperature. Set to null to use the provider default.
     *
     * @var float|null
     */
    public ?float $topP = null;

    /**
     * Number of completions to generate for each request.
     * Most providers support 1–10. Defaults to 1.
     *
     * @var int
     */
    public int $n = 1;

    /**
     * Conversation history for multi-turn sessions.
     *
     * Each element must follow the shape:
     * ```
     * ['role' => 'user'|'assistant'|'system', 'content' => '...']
     * ```
     * Build this array before calling generateContent() to maintain context
     * across multiple turns.
     *
     * @var array<int, array{role: string, content: string}>
     */
    public array $conversationHistory = [];

    // -------------------------------------------------------------------------
    // Private/protected credentials & internals
    // -------------------------------------------------------------------------

    /** @var string|null API key for the selected provider */
    private ?string $apiKey = null;

    /**
     * API version string.
     *
     * - Anthropic: required header value, e.g. '2023-06-01'
     * - OpenAI / Google: used in endpoint URL substitution where applicable
     *
     * @var string|null
     */
    private ?string $apiVersion = null;

    /**
     * Optional Microsoft Azure deployment name.
     * Only used when provider is 'microsoft'.
     *
     * @var string|null
     */
    private ?string $azureDeploymentName = null;

    /**
     * Optional Microsoft Azure resource name (subdomain).
     * Only used when provider is 'microsoft'.
     *
     * @var string|null
     */
    private ?string $azureResourceName = null;

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /**
     * Construct a new LCS_AIManager instance.
     *
     * @param string      $apiKey     API key for the chosen AI provider.
     * @param string|null $apiVersion Optional API version string.
     *                                Required for Anthropic (e.g. '2023-06-01').
     *                                Used in endpoint URL substitution for Google.
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
    // Setter / configuration methods
    // =========================================================================

    /**
     * Set the active AI provider.
     *
     * Also resets the active model to the first model in the new provider's
     * default group so the instance remains in a valid state.
     *
     * @param string $provider One of the values in LLM_Configs::PROVIDERS.
     *
     * @throws \InvalidArgumentException If the provider is not supported.
     */
    public function setProvider(string $provider): void
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new \InvalidArgumentException(
                "Unsupported provider: '$provider'. Supported providers: " . implode(', ', self::PROVIDERS)
            );
        }

        $this->provider = $provider;

        // Reset to first model in the default group for the new provider
        $groupModels = self::MODELS[$provider][
            self::MODEL_GROUPS[$provider][self::DEFAULT_MODEL_GROUP] ?? array_key_first(self::MODEL_GROUPS[$provider])
        ] ?? [];

        $this->model      = $groupModels[0] ?? null;
        $this->modelGroup = self::DEFAULT_MODEL_GROUP;
    }

    /**
     * Set the active model.
     *
     * Validates that the model belongs to the current provider (across all
     * state lists) before accepting it.
     *
     * @param string $model A model identifier listed under the current provider.
     *
     * @throws \InvalidArgumentException If the model is not valid for the current provider.
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
     * @throws \InvalidArgumentException If the state is not recognised.
     */
    public function setModelState(string $modelState): void
    {
        if (!in_array($modelState, self::MODEL_STATES, true)) {
            throw new \InvalidArgumentException(
                "Unsupported model state: '$modelState'. Valid states: " . implode(', ', self::MODEL_STATES)
            );
        }

        $this->modelState = $modelState;
    }

    /**
     * Set the active model group.
     *
     * The group must exist in MODEL_GROUPS for the current provider.  When a
     * new group is set the active model is automatically switched to the first
     * model in that group.
     *
     * @param int $modelGroup A numeric key from MODEL_GROUPS for the current provider.
     *
     * @throws \InvalidArgumentException If the group key is not valid for the current provider.
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

        // Auto-switch model to the first entry in the new group
        $groupName   = self::MODEL_GROUPS[$this->provider][$modelGroup];
        $groupModels = self::MODELS[$this->provider][$groupName] ?? [];
        $this->model = $groupModels[0] ?? $this->model;
    }

    /**
     * Set the user prompt.
     *
     * @param string $prompt The instruction or question to send to the model.
     */
    public function setPrompt(string $prompt): void
    {
        $this->prompt = $prompt;
    }

    /**
     * Set a system-level prompt.
     *
     * The system prompt is sent before the conversation history and the user
     * prompt. It is used to give the model a persona, context, or constraints.
     *
     * @param string $systemPrompt High-level instruction for the model's behaviour.
     */
    public function setSystemPrompt(string $systemPrompt): void
    {
        $this->systemPrompt = $systemPrompt;
    }

    /**
     * Set the maximum number of tokens to generate.
     *
     * @param int $maxTokens Positive integer; provider-specific upper limits apply.
     *
     * @throws \InvalidArgumentException If the value is less than 1.
     */
    public function setMaxTokens(int $maxTokens): void
    {
        if ($maxTokens < 1) {
            throw new \InvalidArgumentException('maxTokens must be at least 1.');
        }

        $this->maxTokens = $maxTokens;
    }

    /**
     * Set the sampling temperature.
     *
     * @param float $temperature Value between 0.0 and 2.0.
     *
     * @throws \InvalidArgumentException If out of the 0.0 – 2.0 range.
     */
    public function setTemperature(float $temperature): void
    {
        if ($temperature < 0.0 || $temperature > 2.0) {
            throw new \InvalidArgumentException('Temperature must be between 0.0 and 2.0.');
        }

        $this->temperature = $temperature;
    }

    /**
     * Set the top-P nucleus sampling parameter.
     *
     * @param float $topP Value between 0.0 and 1.0.
     *
     * @throws \InvalidArgumentException If out of the 0.0 – 1.0 range.
     */
    public function setTopP(float $topP): void
    {
        if ($topP < 0.0 || $topP > 1.0) {
            throw new \InvalidArgumentException('topP must be between 0.0 and 1.0.');
        }

        $this->topP = $topP;
    }

    /**
     * Set the number of completions to generate per request.
     *
     * @param int $n Between 1 and 10.
     *
     * @throws \InvalidArgumentException If out of the 1 – 10 range.
     */
    public function setN(int $n): void
    {
        if ($n < 1 || $n > 10) {
            throw new \InvalidArgumentException('n must be between 1 and 10.');
        }

        $this->n = $n;
    }

    /**
     * Configure Microsoft Azure-specific credentials.
     *
     * Must be called before any request when provider is 'microsoft'.
     *
     * @param string $resourceName   The Azure resource name (subdomain part of the host).
     * @param string $deploymentName The Azure deployment name.
     * @param string $apiVersion     The Azure OpenAI API version (e.g. '2024-02-01').
     */
    public function setAzureConfig(string $resourceName, string $deploymentName, string $apiVersion): void
    {
        $this->azureResourceName   = $resourceName;
        $this->azureDeploymentName = $deploymentName;
        $this->apiVersion          = $apiVersion;
    }

    /**
     * Replace the entire conversation history.
     *
     * Each message must be an associative array with at least 'role' and
     * 'content' keys:
     * ```php
     * [
     *   ['role' => 'user',      'content' => 'Hello!'],
     *   ['role' => 'assistant', 'content' => 'Hi there!'],
     * ]
     * ```
     *
     * @param array<int, array{role: string, content: string}> $history
     */
    public function setConversationHistory(array $history): void
    {
        $this->conversationHistory = $history;
    }

    /**
     * Append a single message to the conversation history.
     *
     * @param string $role    'user' | 'assistant' | 'system'
     * @param string $content The message text.
     */
    public function addMessage(string $role, string $content): void
    {
        $this->conversationHistory[] = ['role' => $role, 'content' => $content];
    }

    /**
     * Clear the conversation history.
     *
     * Call this to start a new, unrelated conversation on the same instance.
     */
    public function clearConversationHistory(): void
    {
        $this->conversationHistory = [];
    }

    // =========================================================================
    // Endpoint resolution
    // =========================================================================

    /**
     * Resolve the API endpoint URL for the current provider, version and model.
     *
     * Replaces the {{VERSION}} and {{MODEL}} placeholders defined in
     * LLM_Configs::API_ENDPOINTS with the actual runtime values.
     *
     * For Microsoft Azure the endpoint is constructed dynamically from the
     * resource name, deployment name, and API version set via setAzureConfig().
     *
     * @return string The fully-formed endpoint URL ready for use in an HTTP request.
     *
     * @throws \RuntimeException If no endpoint is configured for the current provider,
     *                           or if required Azure config is missing.
     */
    private function getEndpoint(): string
    {
        // Microsoft Azure: build the endpoint dynamically
        if ($this->provider === 'microsoft') {
            if (empty($this->azureResourceName) || empty($this->azureDeploymentName) || empty($this->apiVersion)) {
                throw new \RuntimeException(
                    'Microsoft Azure requires azureResourceName, azureDeploymentName, and apiVersion. ' .
                    'Call setAzureConfig() before making a request.'
                );
            }

            return sprintf(
                'https://%s.openai.azure.com/openai/deployments/%s/chat/completions?api-version=%s',
                $this->azureResourceName,
                $this->azureDeploymentName,
                $this->apiVersion
            );
        }

        $endpoint = self::API_ENDPOINTS[$this->provider] ?? null;

        if (empty($endpoint)) {
            throw new \RuntimeException(
                "No API endpoint configured for provider: '{$this->provider}'"
            );
        }

        return str_replace(
            ['{{VERSION}}', '{{MODEL}}'],
            [$this->apiVersion ?? '', $this->model ?? ''],
            $endpoint
        );
    }

    // =========================================================================
    // Header construction
    // =========================================================================

    /**
     * Build the HTTP headers required for the current provider.
     *
     * Each provider has a different authentication and versioning convention:
     * - OpenAI        → Authorization: Bearer {key}
     * - Google        → Authorization: Bearer {key} (Vertex) or ?key={key} in URL
     * - Anthropic     → x-api-key + anthropic-version headers
     * - Microsoft     → api-key header
     *
     * @return array<int, string> List of header strings ready for cURL.
     */
    private function buildHeaders(): array
    {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        switch ($this->provider) {
            case 'openai':
                $headers[] = 'Authorization: Bearer ' . $this->apiKey;
                break;

            case 'google':
                // Vertex AI uses OAuth bearer; Generative Language API uses a query param
                // We support both — if an apiKey looks like an OAuth token use Bearer,
                // otherwise it will be appended as ?key= in getEndpoint (caller's choice).
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
    // Payload builders (provider-specific)
    // =========================================================================

    /**
     * Build the request payload for content generation.
     *
     * The payload shape differs per provider:
     * - OpenAI / Microsoft → chat completions format  (messages array)
     * - Anthropic           → messages API format
     * - Google              → generateContent format   (contents array)
     *
     * @param string $userContent The user's text input for this turn.
     *
     * @return array<string, mixed> Associative payload ready for JSON encoding.
     */
    private function buildContentPayload(string $userContent): array
    {
        switch ($this->provider) {

            // -----------------------------------------------------------------
            case 'openai':
            case 'microsoft':
            // -----------------------------------------------------------------
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

                if ($this->maxTokens !== null)  { $payload['max_tokens']  = $this->maxTokens;  }
                if ($this->temperature !== null){ $payload['temperature'] = $this->temperature; }
                if ($this->topP !== null)       { $payload['top_p']       = $this->topP;        }

                return $payload;

            // -----------------------------------------------------------------
            case 'anthropic':
            // -----------------------------------------------------------------
                $messages = [];

                foreach ($this->conversationHistory as $msg) {
                    // Anthropic does not support 'system' role in messages array
                    if ($msg['role'] !== 'system') {
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

                if ($this->temperature !== null){ $payload['temperature'] = $this->temperature; }
                if ($this->topP !== null)       { $payload['top_p']       = $this->topP;        }

                return $payload;

            // -----------------------------------------------------------------
            case 'google':
            // -----------------------------------------------------------------
                $contents = [];

                foreach ($this->conversationHistory as $msg) {
                    $googleRole  = ($msg['role'] === 'assistant') ? 'model' : 'user';
                    $contents[]  = ['role' => $googleRole, 'parts' => [['text' => $msg['content']]]];
                }

                $contents[] = ['role' => 'user', 'parts' => [['text' => $userContent]]];

                $payload = ['contents' => $contents];

                if (!empty($this->systemPrompt)) {
                    $payload['systemInstruction'] = ['parts' => [['text' => $this->systemPrompt]]];
                }

                $generationConfig = [];
                if ($this->maxTokens  !== null) { $generationConfig['maxOutputTokens'] = $this->maxTokens;  }
                if ($this->temperature !== null) { $generationConfig['temperature']     = $this->temperature; }
                if ($this->topP        !== null) { $generationConfig['topP']            = $this->topP;        }
                if ($this->n > 1)               { $generationConfig['candidateCount']  = $this->n;           }

                if (!empty($generationConfig)) {
                    $payload['generationConfig'] = $generationConfig;
                }

                return $payload;

            // -----------------------------------------------------------------
            default:
            // -----------------------------------------------------------------
                throw new \RuntimeException("buildContentPayload(): Unknown provider '{$this->provider}'");
        }
    }

    // =========================================================================
    // Response normalisation
    // =========================================================================

    /**
     * Normalise the raw API response into a consistent structure.
     *
     * All provider responses are mapped to the same output shape so callers
     * never need to inspect which provider was used:
     *
     * ```php
     * [
     *   'success'    => bool,
     *   'text'       => string,          // Primary generated text
     *   'texts'      => string[],        // All candidates (n > 1)
     *   'usage'      => [                // Token usage if available
     *       'prompt_tokens'     => int,
     *       'completion_tokens' => int,
     *       'total_tokens'      => int,
     *   ],
     *   'raw'        => array,           // The full decoded JSON from the API
     *   'http_code'  => int,
     *   'error'      => string|null,
     * ]
     * ```
     *
     * @param array  $curlResult The array returned by LCS_Requests::send_curl().
     * @param string $type       One of 'content', 'image', 'audio', 'video'.
     *
     * @return array<string, mixed> Normalised result.
     */
    private function normaliseResponse(array $curlResult, string $type = 'content'): array
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

        if (!empty($curlResult['error'])) {
            $base['error'] = $curlResult['error'];
            return $base;
        }

        $json      = $curlResult['json'] ?? [];
        $httpCode  = (int)($curlResult['http_code'] ?? 0);

        if ($httpCode < 200 || $httpCode >= 300 || empty($json)) {
            $base['error'] = $json['error']['message']
                ?? $json['message']
                ?? ('HTTP ' . $httpCode . ': unexpected response');
            return $base;
        }

        $texts = [];

        switch ($this->provider) {

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

            case 'anthropic':
                foreach ($json['content'] ?? [] as $block) {
                    if (($block['type'] ?? '') === 'text') {
                        $texts[] = $block['text'] ?? '';
                    }
                }
                $base['usage'] = [
                    'prompt_tokens'     => $json['usage']['input_tokens']  ?? 0,
                    'completion_tokens' => $json['usage']['output_tokens'] ?? 0,
                    'total_tokens'      => ($json['usage']['input_tokens'] ?? 0) + ($json['usage']['output_tokens'] ?? 0),
                ];
                break;

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
    // Core generation methods
    // =========================================================================

    /**
     * Generate text content using the configured AI provider.
     *
     * The method supports three input styles so you can use whichever fits
     * your workflow:
     *
     * 1. **Property-based** — set $this->prompt beforehand, call with no args:
     *    ```php
     *    $ai->setPrompt('What is 2+2?');
     *    $result = $ai->generateContent();
     *    ```
     *
     * 2. **String shorthand** — pass a plain string directly:
     *    ```php
     *    $result = $ai->generateContent('What is 2+2?');
     *    ```
     *
     * 3. **Structured array** — pass a messages array to override history/prompt
     *    completely (advanced, provider-agnostic shape used):
     *    ```php
     *    $result = $ai->generateContent([
     *        ['role' => 'system',    'content' => 'Be concise.'],
     *        ['role' => 'user',      'content' => 'What is 2+2?'],
     *    ]);
     *    ```
     *
     * Returned array shape (see normaliseResponse() for full details):
     * ```php
     * [
     *   'success'   => true,
     *   'text'      => 'The answer is 4.',
     *   'texts'     => ['The answer is 4.'],
     *   'usage'     => ['prompt_tokens' => 14, 'completion_tokens' => 6, 'total_tokens' => 20],
     *   'raw'       => [...],  // full decoded JSON from the provider
     *   'http_code' => 200,
     *   'error'     => null,
     * ]
     * ```
     *
     * @param int|string|array|null $input Optional prompt override.
     *                                     - string: used as the user message.
     *                                     - array:  used as a full messages override.
     *                                     - null:   falls back to $this->prompt.
     *
     * @return array<string, mixed> Normalised generation result.
     *
     * @throws \RuntimeException If no prompt or input is provided, or if the
     *                           provider/endpoint is not correctly configured.
     */
    public function generateContent(int|string|array|null $input = null): array
    {
        // Resolve user content ------------------------------------------------
        if (is_string($input) || is_int($input)) {
            $userContent = (string)$input;

        } elseif (is_array($input)) {
            // Caller passed a full messages array — use it directly by
            // temporarily overriding history and extracting the last user msg.
            $userContent = '';
            $this->conversationHistory = [];

            foreach ($input as $msg) {
                if (isset($msg['role'], $msg['content'])) {
                    if ($msg['role'] === 'system') {
                        $this->systemPrompt = $msg['content'];
                    } elseif ($msg['role'] === 'user') {
                        $userContent = $msg['content'];
                        $this->addMessage('user', $msg['content']);
                    } elseif ($msg['role'] === 'assistant') {
                        $this->addMessage('assistant', $msg['content']);
                    }
                }
            }

            // Pop the last user message — buildContentPayload appends it again
            if (!empty($this->conversationHistory) &&
                end($this->conversationHistory)['role'] === 'user') {
                array_pop($this->conversationHistory);
            }

        } else {
            // Fallback to the prompt property
            $userContent = $this->prompt ?? '';
        }

        if (empty($userContent)) {
            throw new \RuntimeException(
                'generateContent() requires a prompt. Set $this->prompt or pass input as argument.'
            );
        }

        // Build request -------------------------------------------------------
        $endpoint = $this->getEndpoint();
        $headers  = $this->buildHeaders();
        $payload  = $this->buildContentPayload($userContent);

        // Send ----------------------------------------------------------------
        $REQUESTS = new LCS_Requests();
        $result   = $REQUESTS->send_curl($endpoint, $payload, [
            'METHOD'  => 'POST',
            'HEADERS' => $headers,
            'TIMEOUT' => 60,
        ]);

        return $this->normaliseResponse($result, 'content');
    }

    /**
     * Generate an image using the configured AI provider.
     *
     * Supported providers: openai (DALL-E / gpt-image), google (Imagen).
     *
     * Input can be:
     * - **string** — the image generation prompt.
     * - **array**  — extended options merged with defaults, e.g.:
     *   ```php
     *   [
     *     'prompt' => 'A sunset over the ocean',
     *     'size'   => '1024x1024',
     *     'n'      => 2,
     *     'style'  => 'vivid',          // OpenAI only
     *     'quality'=> 'hd',             // OpenAI only
     *   ]
     *   ```
     * - **null** — falls back to $this->prompt.
     *
     * Returned array shape:
     * ```php
     * [
     *   'success'   => true,
     *   'text'      => '',              // not used for images
     *   'images'    => [                // list of result images
     *       ['url' => '...'],           // or ['b64_json' => '...']
     *   ],
     *   'raw'       => [...],
     *   'http_code' => 200,
     *   'error'     => null,
     * ]
     * ```
     *
     * @param int|string|array|null $input Image prompt or options array.
     *
     * @return array<string, mixed> Normalised image generation result.
     *
     * @throws \RuntimeException If no prompt is available or provider is unsupported for images.
     */
    public function generateImage(int|string|array|null $input = null): array
    {
        // Resolve prompt and options ------------------------------------------
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
                'generateImage() requires a prompt. Set $this->prompt or pass it as the first argument.'
            );
        }

        // Build provider-specific payload and endpoint ------------------------
        switch ($this->provider) {

            case 'openai':
                $endpoint = 'https://api.openai.com/v1/images/generations';
                $payload  = [
                    'model'  => $this->model ?? 'dall-e-3',
                    'prompt' => $prompt,
                    'n'      => $options['n'] ?? $this->n,
                    'size'   => $options['size']    ?? '1024x1024',
                    'style'  => $options['style']   ?? 'vivid',
                    'quality'=> $options['quality'] ?? 'standard',
                ];
                break;

            case 'google':
                $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' .
                            ($this->model ?? 'imagen-4.0-generate-001') .
                            ':predict';
                $payload  = [
                    'instances'  => [['prompt' => $prompt]],
                    'parameters' => [
                        'sampleCount' => $options['n'] ?? $this->n,
                    ],
                ];
                break;

            default:
                throw new \RuntimeException(
                    "generateImage() is not supported for provider '{$this->provider}'."
                );
        }

        // Send ----------------------------------------------------------------
        $REQUESTS = new LCS_Requests();
        $result   = $REQUESTS->send_curl($endpoint, $payload, [
            'METHOD'  => 'POST',
            'HEADERS' => $this->buildHeaders(),
            'TIMEOUT' => 120,
        ]);

        // Normalise -----------------------------------------------------------
        $base = [
            'success'   => false,
            'text'      => '',
            'texts'     => [],
            'images'    => [],
            'usage'     => [],
            'raw'       => $result['json'] ?? [],
            'http_code' => $result['http_code'] ?? 0,
            'error'     => $result['error'] ?? null,
        ];

        $json     = $result['json'] ?? [];
        $httpCode = (int)($result['http_code'] ?? 0);

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
     * Generate audio (text-to-speech) using the configured AI provider.
     *
     * Supported providers: openai (tts-1, tts-1-hd, gpt-4o-mini-tts).
     *
     * Input can be:
     * - **string** — the text to convert to speech.
     * - **array**  — extended options:
     *   ```php
     *   [
     *     'input'          => 'Hello world',
     *     'voice'          => 'alloy',   // alloy|echo|fable|onyx|nova|shimmer
     *     'response_format'=> 'mp3',     // mp3|opus|aac|flac
     *     'speed'          => 1.0,       // 0.25 – 4.0
     *   ]
     *   ```
     * - **null** — falls back to $this->prompt.
     *
     * Returned array shape:
     * ```php
     * [
     *   'success'       => true,
     *   'text'          => '',          // not used for audio
     *   'audio_binary'  => '...',       // raw binary string of the audio file
     *   'format'        => 'mp3',
     *   'raw'           => [],
     *   'http_code'     => 200,
     *   'error'         => null,
     * ]
     * ```
     *
     * @param int|string|array|null $input Text to speak, or options array.
     *
     * @return array<string, mixed> Normalised audio generation result.
     *
     * @throws \RuntimeException If provider does not support audio generation.
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

        // Provider dispatch ---------------------------------------------------
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

        // Send ----------------------------------------------------------------
        $REQUESTS = new LCS_Requests();
        $result   = $REQUESTS->send_curl($endpoint, $payload, [
            'METHOD'  => 'POST',
            'HEADERS' => $this->buildHeaders(),
            'TIMEOUT' => 60,
        ]);

        // Audio comes back as raw binary, not JSON
        $httpCode = (int)($result['http_code'] ?? 0);

        $base = [
            'success'      => false,
            'text'         => '',
            'texts'        => [],
            'audio_binary' => '',
            'format'       => $options['response_format'] ?? 'mp3',
            'raw'          => $result['json'] ?? [],
            'http_code'    => $httpCode,
            'error'        => $result['error'] ?? null,
        ];

        if (!empty($result['error']) || $httpCode < 200 || $httpCode >= 300) {
            $errJson     = $result['json'] ?? [];
            $base['error'] = $result['error']
                ?? $errJson['error']['message']
                ?? ('HTTP ' . $httpCode);
            return $base;
        }

        $base['success']      = true;
        $base['audio_binary'] = $result['body'] ?? '';

        return $base;
    }

    /**
     * Generate a video using the configured AI provider.
     *
     * Supported providers: openai (Sora), google (Veo).
     *
     * Video generation is typically asynchronous. This method submits the job
     * and returns the raw provider response including any job/operation ID that
     * you can use to poll for completion with pollVideoJob().
     *
     * Input can be:
     * - **string** — the video generation prompt.
     * - **array**  — extended options:
     *   ```php
     *   [
     *     'prompt'   => 'A cat surfing on a wave',
     *     'duration' => 5,      // seconds (provider limits apply)
     *     'size'     => '1280x720',
     *     'n'        => 1,
     *   ]
     *   ```
     * - **null** — falls back to $this->prompt.
     *
     * Returned array shape:
     * ```php
     * [
     *   'success'    => true,
     *   'text'       => '',
     *   'job_id'     => 'gen_abc123',   // use with pollVideoJob()
     *   'status'     => 'queued',
     *   'video_url'  => null,           // populated once job completes
     *   'raw'        => [...],
     *   'http_code'  => 200,
     *   'error'      => null,
     * ]
     * ```
     *
     * @param int|string|array|null $input Video prompt or options array.
     *
     * @return array<string, mixed> Normalised video generation result.
     *
     * @throws \RuntimeException If provider does not support video generation.
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

        // Provider dispatch ---------------------------------------------------
        switch ($this->provider) {

            case 'openai':
                // Sora API (async job)
                $endpoint = 'https://api.openai.com/v1/video/generations';
                $payload  = [
                    'model'       => $this->model ?? 'sora-2',
                    'prompt'      => $prompt,
                    'n'           => $options['n']        ?? 1,
                    'duration'    => $options['duration'] ?? 5,
                    'size'        => $options['size']     ?? '1280x720',
                ];
                break;

            case 'google':
                // Veo via Vertex AI / Generative Language API (long-running operation)
                $endpoint = sprintf(
                    'https://generativelanguage.googleapis.com/v1beta/models/%s:generateVideo',
                    $this->model ?? 'veo-3.0-generate-preview'
                );
                $payload  = [
                    'prompt'         => $prompt,
                    'sampleCount'    => $options['n']        ?? 1,
                    'durationSeconds'=> $options['duration'] ?? 5,
                ];
                break;

            default:
                throw new \RuntimeException(
                    "generateVideo() is not yet supported for provider '{$this->provider}'."
                );
        }

        // Send ----------------------------------------------------------------
        $REQUESTS = new LCS_Requests();
        $result   = $REQUESTS->send_curl($endpoint, $payload, [
            'METHOD'  => 'POST',
            'HEADERS' => $this->buildHeaders(),
            'TIMEOUT' => 60,
        ]);

        // Normalise -----------------------------------------------------------
        $json     = $result['json'] ?? [];
        $httpCode = (int)($result['http_code'] ?? 0);

        $base = [
            'success'   => false,
            'text'      => '',
            'texts'     => [],
            'job_id'    => null,
            'status'    => null,
            'video_url' => null,
            'raw'       => $json,
            'http_code' => $httpCode,
            'error'     => $result['error'] ?? null,
        ];

        if (!empty($result['error']) || $httpCode < 200 || $httpCode >= 300) {
            $base['error'] = $result['error']
                ?? $json['error']['message']
                ?? ('HTTP ' . $httpCode);
            return $base;
        }

        $base['success'] = true;
        $base['job_id']  = $json['id'] ?? $json['name'] ?? null;   // OpenAI: 'id', Google: 'name' (operation)
        $base['status']  = $json['status'] ?? 'queued';

        // If the provider returned a completed video synchronously
        if (!empty($json['data'][0]['url'])) {
            $base['video_url'] = $json['data'][0]['url'];
            $base['status']    = 'completed';
        }

        return $base;
    }

    /**
     * Poll an async video generation job for completion.
     *
     * Use the job_id returned by generateVideo() to query status until the
     * video is ready. Poll at a reasonable interval (e.g. every 5–10 seconds);
     * do not hammer the provider endpoint.
     *
     * @param string $jobId The job/operation ID returned by generateVideo().
     *
     * @return array<string, mixed> Result with 'status', 'video_url', and 'raw' keys.
     *
     * @throws \RuntimeException If polling is not supported for the current provider.
     */
    public function pollVideoJob(string $jobId): array
    {
        switch ($this->provider) {

            case 'openai':
                $endpoint = 'https://api.openai.com/v1/video/generations/' . urlencode($jobId);
                break;

            case 'google':
                // Long-running operation name is used as the polling path
                $endpoint = 'https://generativelanguage.googleapis.com/v1beta/' . ltrim($jobId, '/');
                break;

            default:
                throw new \RuntimeException(
                    "pollVideoJob() is not supported for provider '{$this->provider}'."
                );
        }

        $REQUESTS = new LCS_Requests();
        $result   = $REQUESTS->send_curl($endpoint, null, [
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
            'raw'       => $json,
            'http_code' => $httpCode,
            'error'     => $result['error'] ?? null,
        ];

        if (!empty($result['error']) || $httpCode < 200 || $httpCode >= 300) {
            $base['error'] = $result['error'] ?? $json['error']['message'] ?? ('HTTP ' . $httpCode);
            return $base;
        }

        $base['success'] = true;
        $base['status']  = $json['status'] ?? ($json['done'] ? 'completed' : 'processing');

        if (!empty($json['data'][0]['url'])) {
            $base['video_url'] = $json['data'][0]['url'];
        } elseif (!empty($json['response']['videos'][0]['uri'])) {
            $base['video_url'] = $json['response']['videos'][0]['uri'];
        }

        return $base;
    }

    // =========================================================================
    // Convenience / utility methods
    // =========================================================================

    /**
     * Quick one-shot content generation without mutating instance state.
     *
     * Useful when you need a single response without altering the ongoing
     * conversation history or prompt properties of the instance.
     *
     * ```php
     * $answer = $ai->ask('What is the capital of France?');
     * echo $answer; // "Paris"
     * ```
     *
     * @param string $question The user question / prompt.
     *
     * @return string The generated text, or an empty string on failure.
     */
    public function ask(string $question): string
    {
        // Preserve existing state
        $savedPrompt   = $this->prompt;
        $savedHistory  = $this->conversationHistory;

        // Run isolated request
        $this->prompt              = $question;
        $this->conversationHistory = [];

        $result = $this->generateContent();

        // Restore state
        $this->prompt              = $savedPrompt;
        $this->conversationHistory = $savedHistory;

        return $result['success'] ? ($result['text'] ?? '') : '';
    }

    /**
     * Continue a multi-turn conversation.
     *
     * Sends the user message, then automatically appends both the user message
     * and the assistant reply to the conversation history so the next call
     * retains full context.
     *
     * ```php
     * $ai->chat('Who wrote Hamlet?');
     * $ai->chat('And what year was it written?');  // model has context from turn 1
     * ```
     *
     * @param string $userMessage The next user message in the conversation.
     *
     * @return array<string, mixed> Full normalised response (same shape as generateContent()).
     */
    public function chat(string $userMessage): array
    {
        $this->prompt = $userMessage;
        $result       = $this->generateContent();

        // Add both turns to history for next call
        $this->addMessage('user', $userMessage);

        if ($result['success'] && !empty($result['text'])) {
            $this->addMessage('assistant', $result['text']);
        }

        return $result;
    }

    /**
     * Return a snapshot of the current manager configuration.
     *
     * Useful for debugging, logging, or persisting session state.
     *
     * @return array<string, mixed> Current configuration values.
     */
    public function getConfig(): array
    {
        return [
            'provider'            => $this->provider,
            'model'               => $this->model,
            'modelState'          => $this->modelState,
            'modelGroup'          => $this->modelGroup,
            'maxTokens'           => $this->maxTokens,
            'temperature'         => $this->temperature,
            'topP'                => $this->topP,
            'n'                   => $this->n,
            'hasSystemPrompt'     => !empty($this->systemPrompt),
            'hasPrompt'           => !empty($this->prompt),
            'historyLength'       => count($this->conversationHistory),
            'hasAzureConfig'      => !empty($this->azureResourceName),
            'apiVersionSet'       => !empty($this->apiVersion),
        ];
    }

    /**
     * List all available models for the current provider and state filter.
     *
     * Delegates to LLM_Configs::getModels() with the currently set provider
     * and modelState, returning a flat array of model identifier strings.
     *
     * @return array<int, string> List of model identifiers.
     */
    public function listModels(): array
    {
        return self::getModels($this->provider, $this->modelState ?? 'all');
    }

    /**
     * List all available models for the current provider, grouped by family.
     *
     * @return array<string, array<int, string>> Model list grouped by family name.
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
     * Check whether the current model belongs to a specific family/group.
     *
     * @param string $groupName Group/family name, e.g. 'frontier', 'image', 'claude'.
     *
     * @return bool True if the active model is in the specified group.
     */
    public function modelBelongsToGroup(string $groupName): bool
    {
        $group = self::getModelGroup($this->model ?? '');
        return $group === $groupName;
    }

    /**
     * Reset the manager to its default configuration.
     *
     * Clears conversation history, prompt, system prompt, and all generation
     * parameters. Provider, model, and API credentials are preserved.
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