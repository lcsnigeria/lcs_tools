<?php 
namespace LCSNG\Tools\AI;

/**
 * Trait LLM_Configs
 *
 * This trait defines the configuration for Large Language Models (LLMs) used in the LCS AI tools.
 * It includes lists of supported providers and models, as well as default settings.
 */
trait LLM_Configs 
{
    /**
     * List of supported AI providers. This is a static list and should be 
     * updated as new providers are added or removed.
     */
    const PROVIDERS = [
        'openai',
        'google',
        'anthropic',
        'microsoft'
    ];

    /**
     * Master model list grouped by provider and family.
     *
     * This list may include stable, preview, and deprecated model identifiers.
     * Use the state-specific constants below when you need stricter filtering.
     *
     * Rules:
     * - Keep provider family names consistent across all states.
     * - Prefer empty arrays over changing/removing group names in other states.
     *
     * Sample usage:
     * ```php
     * $googleVeoModels = MODELS['google']['veo'] ?? [];
     * $openAIImageModels = MODELS['openai']['image'] ?? [];
     * ```
     */
    const MODELS = [
        'openai' => [
            'frontier' => [
                'gpt-4.1',
                'gpt-4.1-mini',
                'gpt-4.1-nano',
                'gpt-5',
                'gpt-5-mini',
                'gpt-5-nano',
                'gpt-5.1',
                'gpt-5.2',
                'gpt-5.2-pro',
                'gpt-5-pro',
                'gpt-5.4',
                'gpt-5.4-mini',
                'gpt-5.4-nano',
                'gpt-5.4-pro',
            ],

            'chatgpt' => [
                'chatgpt-4o',
                'gpt-5-chat',
                'gpt-5.1-chat',
                'gpt-5.2-chat',
                'gpt-5.3-chat',
            ],

            'reasoning_research' => [
                'o1-preview',
                'o1-mini',
                'o1',
                'o1-pro',
                'o3-mini',
                'o3',
                'o3-pro',
                'o3-deep-research',
                'o4-mini',
                'o4-mini-deep-research',
                'computer-use-preview',
            ],

            'coding' => [
                'codex-mini-latest',
                'gpt-5.1-codex',
                'gpt-5.1-codex-mini',
                'gpt-5.1-codex-max',
                'gpt-5.2-codex',
                'gpt-5.3-codex',
                'gpt-5-codex',
            ],

            'image' => [
                'dall-e-2',
                'dall-e-3',
                'gpt-image-1',
                'gpt-image-1-mini',
                'chatgpt-image-latest',
                'gpt-image-1.5',
            ],

            'video' => [
                'sora-2',
                'sora-2-pro',
            ],

            'audio_realtime' => [
                'whisper-1',
                'tts-1',
                'tts-1-hd',
                'gpt-4o-mini-tts',
                'gpt-4o-transcribe',
                'gpt-4o-mini-transcribe',
                'gpt-4o-transcribe-diarize',
                'gpt-4o-audio',
                'gpt-4o-mini-audio',
                'gpt-4o-realtime',
                'gpt-4o-mini-realtime',
                'gpt-audio',
                'gpt-audio-mini',
                'gpt-audio-1.5',
                'gpt-realtime',
                'gpt-realtime-mini',
                'gpt-realtime-1.5',
            ],

            'search' => [
                'gpt-4o-search-preview',
                'gpt-4o-mini-search-preview',
            ],

            'embeddings' => [
                'text-embedding-ada-002',
                'text-embedding-3-small',
                'text-embedding-3-large',
            ],

            'moderation' => [
                'text-moderation',
                'text-moderation-stable',
                'text-moderation-latest',
                'omni-moderation',
                'omni-moderation-latest',
            ],

            'open_weight' => [
                'gpt-oss-20b',
                'gpt-oss-120b',
            ],

            'legacy' => [
                'babbage-002',
                'davinci-002',
                'gpt-3.5-turbo',
                'gpt-4',
                'gpt-4-turbo-preview',
                'gpt-4-turbo',
                'gpt-4.5-preview',
                'gpt-4o',
                'gpt-4o-mini',
            ],
        ],

        'google' => [
            'gemini' => [
                'gemini-1.5-pro',
                'gemini-2.0-flash',
                'gemini-2.0-flash-001',
                'gemini-2.0-flash-lite',
                'gemini-2.0-flash-lite-001',
                'gemini-2.5-flash',
                'gemini-2.5-flash-image',
                'gemini-2.5-flash-lite',
                'gemini-2.5-pro',
                'gemini-2.5-flash-live-preview',
                'gemini-2.5-flash-native-audio-preview-12-2025',
                'gemini-2.5-flash-tts-preview',
                'gemini-2.5-pro-tts-preview',
                'gemini-3-flash-preview',
                'gemini-3-pro-preview',
                'gemini-3.1-flash-lite-preview',
                'gemini-3.1-flash-live-preview',
                'gemini-3.1-pro-preview',
                'gemini-3.1-pro-preview-customtools',
                'gemini-3.1-flash-image-preview',
                'gemini-3-pro-image-preview',
            ],

            'veo' => [
                'veo-3.0-generate-preview',
                'veo-3.0-fast-generate-preview',
                'veo-3.1-generate-preview',
                'veo-3.1-fast-generate-preview',
            ],

            'imagen' => [
                'imagen-4.0-generate-001',
                'imagen-4.0-ultra-generate-001',
                'imagen-4.0-fast-generate-001',
            ],

            'lyria' => [
                'lyria-3-clip-preview',
                'lyria-3-pro-preview',
                'lyria-realtime-exp',
            ],

            'embeddings' => [
                'embedding-001',
                'embedding-gecko-001',
                'gemini-embedding-exp',
                'gemini-embedding-exp-03-07',
                'gemini-embedding-001',
            ],
        ],

        'anthropic' => [
            'claude' => [
                'claude-2.0',
                'claude-2.1',
                'claude-3-sonnet-20240229',
                'claude-3-haiku-20240307',
                'claude-3-5-haiku-20241022',
                'claude-opus-4-5',
                'claude-opus-4-5-20251101',
                'claude-haiku-4-5',
                'claude-haiku-4-5-20251001',
                'claude-sonnet-4-6',
                'claude-opus-4-6',
            ],
        ],

        'microsoft' => [
            'azure_openai' => [
            ],
        ],
    ];

    /**
     * Stable model identifiers grouped by provider and family.
     *
     * The family/group names intentionally mirror MODELS exactly for each provider.
     *
     * Sample usage:
     * ```php
     * $stableClaude = STABLE_MODELS['anthropic']['claude'] ?? [];
     * ```
     */
    const STABLE_MODELS = [
        'openai' => [
            'frontier' => [
                'gpt-4.1',
                'gpt-4.1-mini',
                'gpt-4.1-nano',
                'gpt-5',
                'gpt-5-mini',
                'gpt-5-nano',
                'gpt-5.1',
                'gpt-5.2',
                'gpt-5.2-pro',
                'gpt-5-pro',
                'gpt-5.4',
                'gpt-5.4-mini',
                'gpt-5.4-nano',
                'gpt-5.4-pro',
            ],

            'chatgpt' => [
                'gpt-5-chat',
                'gpt-5.1-chat',
                'gpt-5.2-chat',
                'gpt-5.3-chat',
            ],

            'reasoning_research' => [
                'o1',
                'o1-pro',
                'o3',
                'o3-pro',
                'o3-deep-research',
                'o4-mini',
                'o4-mini-deep-research',
            ],

            'coding' => [
                'gpt-5.1-codex',
                'gpt-5.1-codex-mini',
                'gpt-5.1-codex-max',
                'gpt-5.2-codex',
                'gpt-5.3-codex',
                'gpt-5-codex',
            ],

            'image' => [
                'dall-e-2',
                'dall-e-3',
                'gpt-image-1',
                'gpt-image-1-mini',
                'gpt-image-1.5',
            ],

            'video' => [
            ],

            'audio_realtime' => [
                'whisper-1',
                'tts-1',
                'tts-1-hd',
                'gpt-4o-mini-tts',
                'gpt-4o-transcribe',
                'gpt-4o-mini-transcribe',
                'gpt-4o-transcribe-diarize',
                'gpt-4o-audio',
                'gpt-4o-mini-audio',
                'gpt-4o-realtime',
                'gpt-4o-mini-realtime',
                'gpt-audio',
                'gpt-audio-mini',
                'gpt-audio-1.5',
                'gpt-realtime',
                'gpt-realtime-mini',
                'gpt-realtime-1.5',
            ],

            'search' => [
            ],

            'embeddings' => [
                'text-embedding-ada-002',
                'text-embedding-3-small',
                'text-embedding-3-large',
            ],

            'moderation' => [
                'omni-moderation',
            ],

            'open_weight' => [
                'gpt-oss-20b',
                'gpt-oss-120b',
            ],

            'legacy' => [
                'gpt-4o',
                'gpt-4o-mini',
                'gpt-4-turbo',
                'gpt-4',
                'gpt-3.5-turbo',
            ],
        ],

        'google' => [
            'gemini' => [
                'gemini-2.0-flash',
                'gemini-2.0-flash-001',
                'gemini-2.0-flash-lite',
                'gemini-2.0-flash-lite-001',
                'gemini-2.5-pro',
                'gemini-2.5-flash',
                'gemini-2.5-flash-image',
                'gemini-2.5-flash-lite',
            ],

            'veo' => [
                'veo-2.0-generate-001',
                'veo-3.0-generate-001',
                'veo-3.0-fast-generate-001',
            ],

            'imagen' => [
                'imagen-4.0-generate-001',
                'imagen-4.0-ultra-generate-001',
                'imagen-4.0-fast-generate-001',
            ],

            'lyria' => [
            ],

            'embeddings' => [
                'gemini-embedding-001',
            ],
        ],

        'anthropic' => [
            'claude' => [
                'claude-haiku-4-5',
                'claude-haiku-4-5-20251001',
                'claude-sonnet-4-6',
                'claude-opus-4-6',
            ],
        ],

        'microsoft' => [
            'azure_openai' => [
            ],
        ],
    ];

    /**
     * Preview / experimental model identifiers grouped by provider and family.
     *
     * The family/group names intentionally mirror MODELS exactly for each provider.
     *
     * Sample usage:
     * ```php
     * $previewGemini = PREVIEW_MODELS['google']['gemini'] ?? [];
     * ```
     */
    const PREVIEW_MODELS = [
        'openai' => [
            'frontier' => [
            ],

            'chatgpt' => [
            ],

            'reasoning_research' => [
                'computer-use-preview',
            ],

            'coding' => [
            ],

            'image' => [
            ],

            'video' => [
            ],

            'audio_realtime' => [
            ],

            'search' => [
                'gpt-4o-search-preview',
                'gpt-4o-mini-search-preview',
            ],

            'embeddings' => [
            ],

            'moderation' => [
            ],

            'open_weight' => [
            ],

            'legacy' => [
            ],
        ],

        'google' => [
            'gemini' => [
                'gemini-3-flash-preview',
                'gemini-3.1-pro-preview',
                'gemini-3.1-flash-lite-preview',
                'gemini-3.1-flash-image-preview',
                'gemini-3.1-flash-live-preview',
                'gemini-2.5-flash-native-audio-preview-12-2025',
                'gemini-3-pro-preview',
            ],

            'veo' => [
                'veo-3.1-generate-preview',
                'veo-3.1-fast-generate-preview',
                'veo-3.1-lite-generate-preview',
                'veo-3.0-generate-preview',
                'veo-3.0-fast-generate-preview',
            ],

            'imagen' => [
                'gemini-3-pro-image-preview',
            ],

            'lyria' => [
                'lyria-3-clip-preview',
                'lyria-3-pro-preview',
                'lyria-realtime-exp',
            ],

            'embeddings' => [
            ],
        ],

        'anthropic' => [
            'claude' => [
            ],
        ],

        'microsoft' => [
            'azure_openai' => [
            ],
        ],
    ];

    /**
     * Deprecated model identifiers grouped by provider and family.
     *
     * The family/group names intentionally mirror MODELS exactly for each provider.
     *
     * Sample usage:
     * ```php
     * $deprecatedOpenAI = DEPRECATED_MODELS['openai']['legacy'] ?? [];
     * ```
     */
    const DEPRECATED_MODELS = [
        'openai' => [
            'frontier' => [
                'gpt-4.5-preview',
            ],

            'chatgpt' => [
                'chatgpt-4o',
            ],

            'reasoning_research' => [
                'o1-preview',
                'o1-mini',
                'o3-mini',
            ],

            'coding' => [
                'codex-mini-latest',
            ],

            'image' => [
            ],

            'video' => [
            ],

            'audio_realtime' => [
            ],

            'search' => [
            ],

            'embeddings' => [
            ],

            'moderation' => [
                'text-moderation',
                'text-moderation-stable',
            ],

            'open_weight' => [
            ],

            'legacy' => [
                'babbage-002',
                'davinci-002',
                'gpt-4-turbo-preview',
            ],
        ],

        'google' => [
            'gemini' => [
                'gemini-2.5-pro-preview-03-25',
                'gemini-2.5-pro-preview-05-06',
                'gemini-2.5-pro-preview-06-05',
                'gemini-2.5-flash-lite-preview-09-2025',
                'gemini-2.5-flash-preview-05-20',
                'gemini-2.5-flash-image-preview',
                'gemini-2.5-flash-preview-09-25',
                'gemini-2.0-flash-preview-image-generation',
                'gemini-2.0-flash-lite-preview',
                'gemini-2.0-flash-lite-preview-02-05',
                'gemini-live-2.5-flash-preview',
                'gemini-embedding-exp',
                'gemini-embedding-exp-03-07',
            ],

            'veo' => [
                'veo-3.0-generate-preview',
                'veo-3.0-fast-generate-preview',
            ],

            'imagen' => [
                'imagen-3.0-generate-002',
                'imagen-4.0-generate-preview-06-06',
                'imagen-4.0-ultra-generate-preview-06-06',
            ],

            'lyria' => [
            ],

            'embeddings' => [
                'embedding-001',
                'embedding-gecko-001',
            ],
        ],

        'anthropic' => [
            'claude' => [
                'claude-3-haiku-20240307',
                'claude-3-5-haiku-20241022',
            ],
        ],

        'microsoft' => [
            'azure_openai' => [
            ],
        ],
    ];

    /**
     * Grouping of models by provider and category. This can be used for UI organization or filtering.
     * The keys should correspond to the groups defined in the MODELS constant.
     */
    const MODEL_GROUPS = [
        'openai' => [
            1 => 'frontier',
            2 => 'chatgpt_aliases',
            3 => 'reasoning_other',
            4 => 'coding',
            5 => 'image',
            6 => 'video',
            7 => 'audio_realtime',
            8 => 'search',
            9 => 'embeddings',
            10 => 'moderation',
            11 => 'open_weight',
            12 => 'legacy',
        ],
        'google' => [
            1 => 'gemini',
            2 => 'veo',
            3 => 'imagen',
            4 => 'lyria',
            5 => 'embeddings',
        ],
        'anthropic' => [
            1 => 'claude',
        ],
        'microsoft' => [
            1 => 'azure_openai',
        ],
    ];

    const MODEL_STATES = ['stable', 'preview', 'deprecated', 'all'];

    /**
     * Default provider to use if none is specified. This should be a valid provider from the PROVIDERS list.
     * It can be updated based on user preferences or changes in the AI landscape.
     */
    const DEFAULT_PROVIDER = 'openai';

    /**
     * Default model to use if none is specified. This should be a valid model from the MODELS list.
     * It can be updated as new models are released or based on user preferences.
     */
    const DEFAULT_MODEL = 'gpt-4.1';

    /**
     * Default model state to use if none is specified. 
     * This can be 'stable', 'preview', 'deprecated', or 'all'.
     * It can be updated based on user preferences or changes in the AI landscape.
     */
    const DEFAULT_MODEL_STATE = 'stable';

    /**
     * Default model group to use if none is specified. This should correspond to a valid group in MODEL_GROUPS.
     * It can be updated based on user preferences or changes in the AI landscape.
     */
    const DEFAULT_MODEL_GROUP = 1;

    /**
     * API endpoints for each provider. This can be used to route requests to the correct provider based on the selected model.
     * It should be updated if providers change their API endpoints or if new providers are added.
     */
    const API_ENDPOINTS = [
        'openai' => 'https://api.openai.com/{VERSION}/responses',
        'google' => 'https://generativelanguage.googleapis.com/{VERSION}/models/{MODEL}:generateContent',
        'anthropic' => 'https://api.anthropic.com/{VERSION}/messages',
        'microsoft' => null, // Microsoft Azure OpenAI endpoints can vary and may require additional configuration
    ];

    protected static function getProviders(): array
    {
        return self::PROVIDERS;
    }

    protected static function getModels(string $provider = self::DEFAULT_PROVIDER, string $state = 'stable'): array
    {
        $stateKey = match ($state) {
            'stable' => 'STABLE_MODELS',
            'preview' => 'PREVIEW_MODELS',
            'deprecated' => 'DEPRECATED_MODELS',
            'all' => 'MODELS',
            default => null,
        };
        if (!$stateKey || !isset(self::${$stateKey}[$provider])) {
            return [];
        }
        $models = [];
        foreach (self::${$stateKey}[$provider] as $groupModels) {
            if (is_array($groupModels)) {
                $models = array_merge($models, $groupModels);
            }
        }
        return $models;
    }

    protected static function getDefaultProvider(): string
    {
        return self::DEFAULT_PROVIDER;
    }

    protected static function getDefaultModel(): string
    {
        return self::DEFAULT_MODEL;
    }

    protected static function isValidProvider(string $provider): bool
    {
        return in_array($provider, self::PROVIDERS);
    }

    protected static function isValidModel(string $provider, string $model): bool
    {
        return isset(self::MODELS[$provider]) && in_array($model, self::MODELS[$provider]);
    }

    protected static function isStableModel(string $provider, string $model): bool
    {
        return isset(self::STABLE_MODELS[$provider]) && in_array($model, self::STABLE_MODELS[$provider]);
    }

    protected static function isPreviewModel(string $provider, string $model): bool
    {
        return isset(self::PREVIEW_MODELS[$provider]) && in_array($model, self::PREVIEW_MODELS[$provider]);
    }

    protected static function isDeprecatedModel(string $provider, string $model): bool
    {
        return isset(self::DEPRECATED_MODELS[$provider]) && in_array($model, self::DEPRECATED_MODELS[$provider]);
    }

    protected static function getProvider(string $model): string|array|null
    {
        foreach (self::MODELS as $provider => $modelsGroups) {
            foreach ($modelsGroups as $models) {
                if (in_array($model, $models)) {
                    return $provider;
                }
            }
        }
        return null;
    }

    protected static function getModelGroup(string $model): string|array|null
    {
        foreach (self::MODELS as $provider => $modelsGroups) {
            foreach ($modelsGroups as $group => $models) {
                if (in_array($model, $models)) {
                    return $group;
                }
            }
        }
        return null;
    }

    protected static function getGroupModels(string $provider, string $group): array
    {
        return self::MODELS[$provider][$group] ?? [];
    }

}