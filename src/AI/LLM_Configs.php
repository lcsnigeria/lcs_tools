<?php
namespace LCSNG\Tools\AI;

/**
 * Trait LLM_Configs
 *
 * Defines all static configuration for Large Language Models used across the
 * LCS AI tools: provider lists, model catalogues (master + state-partitioned),
 * model group mappings, API endpoints, and helper accessors.
 *
 * Conventions:
 *  - Family/group names in MODEL_GROUPS MUST match the keys used in MODELS,
 *    STABLE_MODELS, PREVIEW_MODELS, and DEPRECATED_MODELS exactly.
 *  - Prefer empty arrays over removing group keys in state-specific lists.
 *  - API_ENDPOINTS must use {{MODEL}} and {{VERSION}} as substitution tokens
 *    (double-braces) to match the str_replace() calls in LCS_AIManager.
 */
trait LLM_Configs
{
    // =========================================================================
    // Supported providers
    // =========================================================================

    /**
     * Complete list of supported AI provider identifiers.
     *
     * @var string[]
     */
    const PROVIDERS = [
        'openai',
        'google',
        'anthropic',
        'microsoft',
    ];

    // =========================================================================
    // Master model catalogue  (all states combined)
    // =========================================================================

    /**
     * Master model list grouped by provider → family.
     *
     * May include stable, preview, and deprecated identifiers together.
     * Use the state-specific constants (STABLE_MODELS etc.) for stricter
     * filtering.
     *
     * Family/group key names here are the canonical source of truth — they
     * MUST match MODEL_GROUPS values exactly.
     *
     * ```php
     * $veoModels   = self::MODELS['google']['veo']    ?? [];
     * $imageModels = self::MODELS['openai']['image']  ?? [];
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

            // NOTE: key is 'chatgpt', NOT 'chatgpt_aliases' — MODEL_GROUPS matches this
            'chatgpt' => [
                'chatgpt-4o',
                'gpt-5-chat',
                'gpt-5.1-chat',
                'gpt-5.2-chat',
                'gpt-5.3-chat',
            ],

            // NOTE: key is 'reasoning_research', NOT 'reasoning_other' — MODEL_GROUPS matches this
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
            'azure_openai' => [],
        ],
    ];

    // =========================================================================
    // Stable models
    // =========================================================================

    /**
     * Models considered production-stable, grouped by provider → family.
     *
     * Family keys MUST mirror those in MODELS exactly.
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
                'chatgpt-4o',
            ],

            'reasoning_research' => [
                'o1',
                'o1-pro',
                'o3',
                'o3-pro',
                'o4-mini',
            ],

            'coding' => [
                'codex-mini-latest',
            ],

            'image' => [
                'dall-e-3',
                'gpt-image-1',
            ],

            'video' => [
                'sora-2',
            ],

            'audio_realtime' => [
                'whisper-1',
                'tts-1',
                'tts-1-hd',
            ],

            'search'       => [],
            'embeddings'   => [
                'text-embedding-3-small',
                'text-embedding-3-large',
            ],

            'moderation'   => [
                'text-moderation',
                'text-moderation-stable',
            ],

            'open_weight'  => [],

            'legacy' => [
                'babbage-002',
                'davinci-002',
                'gpt-3.5-turbo',
                'gpt-4',
                'gpt-4-turbo',
                'gpt-4o',
                'gpt-4o-mini',
            ],
        ],

        'google' => [
            'gemini' => [
                'gemini-2.0-flash',
                'gemini-2.0-flash-001',
                'gemini-2.0-flash-lite',
                'gemini-2.0-flash-lite-001',
                'gemini-2.5-flash',
                'gemini-2.5-pro',
            ],

            'veo' => [
                'veo-3.0-generate-preview',
            ],

            'imagen' => [
                'imagen-4.0-generate-001',
                'imagen-4.0-ultra-generate-001',
            ],

            'lyria'      => [],
            'embeddings' => [
                'embedding-001',
                'embedding-gecko-001',
                'gemini-embedding-001',
            ],
        ],

        'anthropic' => [
            'claude' => [
                'claude-3-haiku-20240307',
                'claude-3-5-haiku-20241022',
                'claude-haiku-4-5',
                'claude-haiku-4-5-20251001',
                'claude-sonnet-4-6',
                'claude-opus-4-6',
            ],
        ],

        'microsoft' => [
            'azure_openai' => [],
        ],
    ];

    // =========================================================================
    // Preview models
    // =========================================================================

    /**
     * Models currently in preview / beta, grouped by provider → family.
     *
     * Family keys MUST mirror those in MODELS exactly.
     */
    const PREVIEW_MODELS = [
        'openai' => [
            'frontier' => [],

            'chatgpt' => [
                'gpt-5-chat',
                'gpt-5.1-chat',
                'gpt-5.2-chat',
                'gpt-5.3-chat',
            ],

            'reasoning_research' => [
                'o1-preview',
                'o1-mini',
                'o3-mini',
                'o3-deep-research',
                'o4-mini-deep-research',
                'computer-use-preview',
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
                'gpt-image-1-mini',
                'chatgpt-image-latest',
                'gpt-image-1.5',
            ],

            'video' => [
                'sora-2-pro',
            ],

            'audio_realtime' => [
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
            ],

            'moderation' => [
                'text-moderation-latest',
                'omni-moderation',
                'omni-moderation-latest',
            ],

            'open_weight' => [
                'gpt-oss-20b',
                'gpt-oss-120b',
            ],

            'legacy' => [
                'gpt-4-turbo-preview',
                'gpt-4.5-preview',
            ],
        ],

        'google' => [
            'gemini' => [
                'gemini-1.5-pro',
                'gemini-2.5-flash-image',
                'gemini-2.5-flash-lite',
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
                'veo-3.0-fast-generate-preview',
                'veo-3.1-generate-preview',
                'veo-3.1-fast-generate-preview',
            ],

            'imagen' => [
                'imagen-4.0-fast-generate-001',
            ],

            'lyria' => [
                'lyria-3-clip-preview',
                'lyria-3-pro-preview',
                'lyria-realtime-exp',
            ],

            'embeddings' => [
                'gemini-embedding-exp',
                'gemini-embedding-exp-03-07',
            ],
        ],

        'anthropic' => [
            'claude' => [
                'claude-opus-4-5',
                'claude-opus-4-5-20251101',
            ],
        ],

        'microsoft' => [
            'azure_openai' => [],
        ],
    ];

    // =========================================================================
    // Deprecated models
    // =========================================================================

    /**
     * Models that are deprecated and should not be used for new projects,
     * grouped by provider → family.
     *
     * Family keys MUST mirror those in MODELS exactly.
     */
    const DEPRECATED_MODELS = [
        'openai' => [
            'frontier'           => [],
            'chatgpt'            => [],
            'reasoning_research' => [],
            'coding'             => [],
            'image'              => [],
            'video'              => [],
            'audio_realtime'     => [],
            'search'             => [],
            'embeddings'         => [],
            'moderation'         => [],
            'open_weight'        => [],
            'legacy'             => [
                'gpt-3.5-turbo',
                'gpt-4',
                'gpt-4-turbo-preview',
                'gpt-4.5-preview',
            ],
        ],

        'google' => [
            'gemini'     => ['gemini-1.5-pro'],
            'veo'        => [],
            'imagen'     => [],
            'lyria'      => [],
            'embeddings' => [],
        ],

        'anthropic' => [
            'claude' => [
                'claude-2.0',
                'claude-2.1',
                'claude-3-sonnet-20240229',
            ],
        ],

        'microsoft' => [
            'azure_openai' => [],
        ],
    ];

    // =========================================================================
    // Model groups  (numeric key → family name mapping per provider)
    // =========================================================================

    /**
     * Maps integer group keys to family names for each provider.
     *
     * CRITICAL: values here MUST exactly match the array keys used in MODELS,
     * STABLE_MODELS, PREVIEW_MODELS, and DEPRECATED_MODELS.
     *
     * This is the authoritative source for setModelGroup() and UI organisation.
     */
    const MODEL_GROUPS = [
        'openai' => [
            1  => 'frontier',
            2  => 'chatgpt',            // was 'chatgpt_aliases' — fixed to match MODELS key
            3  => 'reasoning_research', // was 'reasoning_other'  — fixed to match MODELS key
            4  => 'coding',
            5  => 'image',
            6  => 'video',
            7  => 'audio_realtime',
            8  => 'search',
            9  => 'embeddings',
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

    // =========================================================================
    // State list & defaults
    // =========================================================================

    /** @var string[] Valid model state identifiers. */
    const MODEL_STATES = ['stable', 'preview', 'deprecated', 'all'];

    /** @var string Provider used when none is specified. */
    const DEFAULT_PROVIDER = 'openai';

    /** @var string Model used when none is specified. */
    const DEFAULT_MODEL = 'gpt-4.1';

    /** @var string Model state filter applied at boot. */
    const DEFAULT_MODEL_STATE = 'all'; // 'all' so setModel() accepts any known model out of the box

    /** @var int Default model group key. */
    const DEFAULT_MODEL_GROUP = 1;

    // =========================================================================
    // API endpoints
    // =========================================================================

    /**
     * Base API endpoint templates per provider.
     *
     * Tokens {{VERSION}} and {{MODEL}} are substituted at runtime by
     * LCS_AIManager::getEndpoint() using str_replace().
     *
     * OpenAI  — Chat Completions API (POST /v1/chat/completions).
     *           No version or model token needed in the URL; the model is
     *           passed in the JSON body.
     *
     * Google  — Generative Language API; {{MODEL}} is replaced with the active
     *           model string (e.g. gemini-2.5-pro).
     *
     * Anthropic — Messages API; {{VERSION}} is not used in the URL (version is
     *             sent as the 'anthropic-version' request header instead).
     *             URL is constant.
     *
     * Microsoft — Built dynamically in getEndpoint() from setAzureConfig()
     *             values; null here signals that special handling is required.
     */
    const API_ENDPOINTS = [
        'openai'    => 'https://api.openai.com/v1/chat/completions',
        'google'    => 'https://generativelanguage.googleapis.com/v1beta/models/{{MODEL}}:generateContent',
        'anthropic' => 'https://api.anthropic.com/v1/messages',
        'microsoft' => null,
    ];

    // =========================================================================
    // Static helper methods
    // =========================================================================

    /**
     * Return the list of all supported provider identifiers.
     *
     * @return string[]
     */
    protected static function getProviders(): array
    {
        return self::PROVIDERS;
    }

    /**
     * Return a flat array of model identifiers for a given provider and state.
     *
     * @param string $provider A value from PROVIDERS.
     * @param string $state    One of 'stable', 'preview', 'deprecated', 'all'.
     *
     * @return string[] Flat list of model identifier strings.
     */
    protected static function getModels(string $provider = self::DEFAULT_PROVIDER, string $state = 'all'): array
    {
        $stateKey = match ($state) {
            'stable'     => 'STABLE_MODELS',
            'preview'    => 'PREVIEW_MODELS',
            'deprecated' => 'DEPRECATED_MODELS',
            'all'        => 'MODELS',
            default      => null,
        };

        if (!$stateKey || !isset(self::$$stateKey[$provider])) {
            return [];
        }

        $models = [];
        foreach (self::$$stateKey[$provider] as $groupModels) {
            if (is_array($groupModels)) {
                $models = array_merge($models, $groupModels);
            }
        }

        return array_values(array_unique($models));
    }

    /**
     * Return the default provider identifier.
     *
     * @return string
     */
    protected static function getDefaultProvider(): string
    {
        return self::DEFAULT_PROVIDER;
    }

    /**
     * Return the default model identifier.
     *
     * @return string
     */
    protected static function getDefaultModel(): string
    {
        return self::DEFAULT_MODEL;
    }

    /**
     * Check whether a provider identifier is supported.
     *
     * @param string $provider
     *
     * @return bool
     */
    protected static function isValidProvider(string $provider): bool
    {
        return in_array($provider, self::PROVIDERS, true);
    }

    /**
     * Check whether a model identifier exists under a given provider in the
     * master (all-states) list.
     *
     * Previously broken: it compared a string against a nested grouped array.
     * Now correctly flattens the nested structure before searching.
     *
     * @param string $provider
     * @param string $model
     *
     * @return bool
     */
    protected static function isValidModel(string $provider, string $model): bool
    {
        return in_array($model, self::getModels($provider, 'all'), true);
    }

    /**
     * Check whether a model is listed as stable for a given provider.
     *
     * @param string $provider
     * @param string $model
     *
     * @return bool
     */
    protected static function isStableModel(string $provider, string $model): bool
    {
        return in_array($model, self::getModels($provider, 'stable'), true);
    }

    /**
     * Check whether a model is listed as preview for a given provider.
     *
     * @param string $provider
     * @param string $model
     *
     * @return bool
     */
    protected static function isPreviewModel(string $provider, string $model): bool
    {
        return in_array($model, self::getModels($provider, 'preview'), true);
    }

    /**
     * Check whether a model is listed as deprecated for a given provider.
     *
     * @param string $provider
     * @param string $model
     *
     * @return bool
     */
    protected static function isDeprecatedModel(string $provider, string $model): bool
    {
        return in_array($model, self::getModels($provider, 'deprecated'), true);
    }

    /**
     * Detect which provider owns a given model string by searching MODELS.
     *
     * @param string $model
     *
     * @return string|null Provider identifier, or null if not found.
     */
    protected static function getProvider(string $model): ?string
    {
        foreach (self::MODELS as $provider => $groups) {
            foreach ($groups as $models) {
                if (in_array($model, $models, true)) {
                    return $provider;
                }
            }
        }

        return null;
    }

    /**
     * Detect which family/group a model belongs to by searching MODELS.
     *
     * @param string $model
     *
     * @return string|null Group/family name, or null if not found.
     */
    protected static function getModelGroup(string $model): ?string
    {
        foreach (self::MODELS as $groups) {
            foreach ($groups as $group => $models) {
                if (in_array($model, $models, true)) {
                    return $group;
                }
            }
        }

        return null;
    }

    /**
     * Return all model identifiers in a specific provider + group.
     *
     * @param string $provider
     * @param string $group    Family name, e.g. 'frontier', 'image', 'claude'.
     *
     * @return string[]
     */
    protected static function getGroupModels(string $provider, string $group): array
    {
        return self::MODELS[$provider][$group] ?? [];
    }
}