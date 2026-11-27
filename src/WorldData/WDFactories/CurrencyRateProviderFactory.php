<?php
namespace LCSNG\Tools\WorldData\WDFactories;

use LCSNG\Tools\WorldData\WDInterfaces\CurrencyRateProviderInterface;
use LCSNG\Tools\WorldData\WDProviders\CurrencyApiProvider;
use LCSNG\Tools\WorldData\WDProviders\ExchangeRateApiProvider;
use LCSNG\Tools\WorldData\WDProviders\FixerProvider;

final class CurrencyRateProviderFactory
{
    public const SUPPORTED_PROVIDERS = [
        'exchangerate-api',
        'currencyapi',
        'fixer',
    ];

    public static function create(string $provider, ?string $apiKey = null): CurrencyRateProviderInterface
    {
        return match (strtolower(trim($provider))) {
            'exchangerate-api' => new ExchangeRateApiProvider($apiKey),
            'currencyapi' => new CurrencyApiProvider($apiKey),
            'fixer' => new FixerProvider($apiKey),
            default => throw new \InvalidArgumentException(
                sprintf(
                    "Unsupported API provider '%s'. Supported providers: %s.",
                    $provider,
                    implode(', ', self::SUPPORTED_PROVIDERS)
                )
            ),
        };
    }

    public static function isSupported(string $provider): bool
    {
        return in_array(strtolower(trim($provider)), self::SUPPORTED_PROVIDERS, true);
    }
}
