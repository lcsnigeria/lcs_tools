<?php
namespace LCSNG\Tools\WorldData;

use LCSNG\Tools\WorldData\WDFactories\CurrencyRateProviderFactory;
use LCSNG\Tools\WorldData\WDInterfaces\CurrencyRateProviderInterface;

/**
 * LCS Currency Converter
 * 
 * A flexible, production-ready currency converter that supports multiple API providers,
 * caching, and conversion between any currency pairs.
 * 
 * @version 1.0.0
 */
class LCS_CurrencyConverter
{
    /**
     * The selected API provider identifier.
     *
     * This determines which external currency exchange service will be used
     * to retrieve live rates. Expected values include:
     * - exchangerate-api
     * - currencyapi
     * - fixer
     *
     * @var string
     */
    private string $apiProvider;

    private CurrencyRateProviderInterface $rateProvider;

    /**
     * Optional API key for providers that require authentication.
     *
     * If the selected provider needs a key, this value is used in requests.
     * Otherwise it may remain null.
     *
     * @var string|null
     */
    private ?string $apiKey;

    /**
     * Filepath used to store cached exchange rate responses.
     *
     * Cached data is reused until expiration to reduce API calls and improve performance.
     *
     * @var string
     */
    private string $cacheFile;

    /**
     * Cache lifetime in seconds.
     *
     * Rate data stored in the cache file is considered invalid after this many seconds.
     *
     * @var int
     */
    private int $cacheExpiryTime;

    /**
     * Loaded exchange rates and metadata from the chosen API provider.
     *
     * This array contains the parsed payload returned by the provider,
     * including the base currency, timestamp, and per-currency rates.
     *
     * @var array
     */
    private array $ratesData = [];

    /**
     * The currency code used as the base for conversions.
     *
     * All external rates are normalized against this currency when conversions are performed.
     *
     * @var string
     */
    private string $baseCurrency;

    /**
     * Flag indicating whether logging is enabled.
     *
     * When true, warnings, errors, and informational messages may be written
     * to the configured log file.
     *
     * @var bool
     */
    private bool $enableLogging;

    /**
     * Path to the log file used when logging is enabled.
     *
     * @var string|null
     */
    private ?string $logFile;

    /**
     * Constructor
     *
     * @param array $config Configuration options:
     *     - apiProvider (string): API provider name ('exchangerate-api', 'currencyapi', 'fixer')
     *     - apiKey (string|null): API key for providers that require it
     *     - cacheFile (string|null): Path to cache file (default: provider/base-specific file in ./WDCache)
     *     - cacheExpiryTime (int): Cache validity in seconds (default: 3600)
     *     - baseCurrency (string): Base currency code (default: 'USD')
     *     - enableLogging (bool): Enable error/info logging (default: false)
     *     - logFile (string|null): Path to log file (default: ./WDLogs/currency_converter.log)
     * 
     * @param CurrencyRateProviderInterface|null $rateProvider Optional provider override, useful for custom providers and testing.
     * @throws \InvalidArgumentException If configuration is invalid.
     * @throws \RuntimeException If the rate provider fails or returns invalid data.
     */
    public function __construct(array $config, ?CurrencyRateProviderInterface $rateProvider = null)
    {
        $this->validateConfig($config);

        $this->apiProvider     = strtolower(trim($config['apiProvider'] ?? 'exchangerate-api'));
        $this->apiKey          = $config['apiKey'] ?? null;
        $this->baseCurrency    = strtoupper($config['baseCurrency'] ?? 'USD');
        $cacheIdentity         = preg_replace('/[^a-z0-9-]/', '', $this->apiProvider) . '_' . $this->baseCurrency;
        $this->cacheFile       = $config['cacheFile'] ?? __DIR__ . '/WDCache/currency_cache_' . $cacheIdentity . '.json';
        $this->cacheExpiryTime = $config['cacheExpiryTime'] ?? 3600;
        $this->enableLogging   = $config['enableLogging'] ?? false;
        $this->logFile         = $config['logFile'] ?? __DIR__ . '/WDLogs/currency_converter.log';
        $this->rateProvider    = $rateProvider ?? CurrencyRateProviderFactory::create($this->apiProvider, $this->apiKey);

        $this->ensureCacheDirectory();
        $this->ratesData = $this->fetchRates();
    }

    /**
     * Validate configuration array
     * 
     * @param array $config
     * @throws \Exception
     */
    private function validateConfig(array $config): void
    {
        if (isset($config['apiProvider']) && (
            !is_string($config['apiProvider'])
            || !CurrencyRateProviderFactory::isSupported($config['apiProvider'])
        )) {
            throw new \InvalidArgumentException(
                'Unsupported API provider. Supported: ' . implode(', ', CurrencyRateProviderFactory::SUPPORTED_PROVIDERS)
            );
        }

        if (isset($config['cacheExpiryTime']) && (!is_int($config['cacheExpiryTime']) || $config['cacheExpiryTime'] < 0)) {
            throw new \InvalidArgumentException("cacheExpiryTime must be a non-negative integer.");
        }

        if (isset($config['baseCurrency']) && (
            !is_string($config['baseCurrency'])
            || !preg_match('/^[A-Z]{3}$/', strtoupper($config['baseCurrency']))
        )) {
            throw new \InvalidArgumentException("baseCurrency must be a valid 3-letter ISO currency code.");
        }

        foreach (['apiKey', 'cacheFile', 'logFile'] as $stringField) {
            if (isset($config[$stringField]) && !is_string($config[$stringField])) {
                throw new \InvalidArgumentException("{$stringField} must be a string.");
            }
        }

        if (isset($config['enableLogging']) && !is_bool($config['enableLogging'])) {
            throw new \InvalidArgumentException('enableLogging must be a boolean.');
        }
    }

    /**
     * Ensure cache directory exists
     */
    private function ensureCacheDirectory(): void
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Unable to create currency cache directory: {$dir}");
        }
    }

    /**
     * Log message to file if logging is enabled
     * 
     * @param string $message
     * @param string $level
     */
    private function log(string $message, string $level = 'INFO'): void
    {
        if (!$this->enableLogging) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Fetch rates from cache or API if expired
     * 
     * @return array Rates data structure
     */
    private function fetchRates(): array
    {
        $cache = $this->readCache();
        if ($cache !== null) {
            $cacheAge = time() - $cache['timestamp'];
            if ($cacheAge >= 0 && $cacheAge < $this->cacheExpiryTime) {
                $this->log("Using cached rates (age: {$cacheAge}s)");
                return $cache['data'];
            }

            $this->log('Cache expired or has an invalid timestamp; fetching fresh data.', 'WARNING');
        }

        return $this->refreshRates();
    }

    private function readCache(): ?array
    {
        if (!is_file($this->cacheFile)) {
            return null;
        }

        $contents = @file_get_contents($this->cacheFile);
        if ($contents === false) {
            $this->log('Unable to read currency cache; fetching fresh data.', 'WARNING');
            return null;
        }

        try {
            $cache = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->log('Currency cache is invalid JSON; fetching fresh data.', 'WARNING');
            return null;
        }

        if (
            !is_array($cache)
            || ($cache['provider'] ?? null) !== $this->apiProvider
            || ($cache['requestedBaseCurrency'] ?? null) !== $this->baseCurrency
            || !is_int($cache['timestamp'] ?? null)
            || !is_array($cache['data'] ?? null)
        ) {
            $this->log('Currency cache does not match the configured provider/base currency.', 'WARNING');
            return null;
        }

        try {
            $this->validateRatesData($cache['data']);
        } catch (\RuntimeException $e) {
            $this->log('Currency cache contains invalid rates; fetching fresh data.', 'WARNING');
            return null;
        }

        return $cache;
    }

    /**
     * Force refresh rates from API and update cache
     * 
     * @return array Updated rates data
     * @throws \RuntimeException If the provider request fails or returns invalid data.
     */
    public function refreshRates(): array
    {
        $this->log("Refreshing rates from API provider: {$this->apiProvider}");

        try {
            $data = $this->rateProvider->fetchRates($this->baseCurrency);
            $this->validateRatesData($data);

            $cacheContents = json_encode([
                'provider' => $this->apiProvider,
                'requestedBaseCurrency' => $this->baseCurrency,
                'timestamp' => time(),
                'data' => $data
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            if (@file_put_contents($this->cacheFile, $cacheContents, LOCK_EX) === false) {
                $this->log('Unable to write currency cache; rates are available for this instance only.', 'WARNING');
            }

            $this->ratesData = $data;
            $this->log("Successfully refreshed rates (" . count($data['rates']) . " currencies)");
            
            return $data;
        } catch (\Exception $e) {
            $this->log("Failed to refresh rates: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Validate rates data structure
     * 
     * @param array $data
     * @throws \Exception
     */
    private function validateRatesData(array $data): void
    {
        if (empty($data['rates']) || !is_array($data['rates'])) {
            throw new \RuntimeException("Invalid rates data: missing or invalid 'rates' array.");
        }

        if (!is_string($data['base'] ?? null) || !preg_match('/^[A-Z]{3}$/', $data['base'])) {
            throw new \RuntimeException("Invalid rates data: missing or invalid 'base' currency.");
        }

        if (!is_string($data['date'] ?? null) || $data['date'] === '') {
            throw new \RuntimeException("Invalid rates data: missing or invalid 'date'.");
        }

        foreach ($data['rates'] as $currency => $rate) {
            if (
                !is_string($currency)
                || !preg_match('/^[A-Z]{3}$/', $currency)
                || !is_numeric($rate)
                || !is_finite((float) $rate)
                || (float) $rate <= 0
            ) {
                throw new \RuntimeException("Invalid rates data: invalid exchange rate for {$currency}.");
            }
        }
    }

    /**
     * Convert amount from one currency to another
     * 
     * @param string $from Source currency code
     * @param string $to Target currency code
     * @param float $amount Amount to convert
     * @param int $precision Decimal precision (default: 4)
     * @return float Converted amount
     * @throws \Exception If conversion fails
     */
    public function convertRates(string $from, string $to, float $amount, int $precision = 4): float
    {
        $rates = $this->ratesData['rates'] ?? [];
        $base  = $this->ratesData['base'] ?? $this->baseCurrency;
        $from  = strtoupper($from);
        $to    = strtoupper($to);

        if ($amount < 0) {
            throw new \Exception("Amount cannot be negative");
        }

        if ($from === $to) {
            return round($amount, $precision);
        }

        // Direct conversion from base currency
        if ($from === $base) {
            $rate = $rates[$to] ?? throw new \Exception("Rate not available for {$to}");
            return round($amount * $rate, $precision);
        }

        // Direct conversion to base currency
        if ($to === $base) {
            $rate = $rates[$from] ?? throw new \Exception("Rate not available for {$from}");
            return round($amount / $rate, $precision);
        }

        // Cross-currency conversion via base currency
        $rateFrom = $rates[$from] ?? throw new \Exception("Rate not available for {$from}");
        $rateTo   = $rates[$to] ?? throw new \Exception("Rate not available for {$to}");

        return round($amount / $rateFrom * $rateTo, $precision);
    }

    /**
     * Get exchange rate between two currencies
     * 
     * @param string $from Source currency
     * @param string $to Target currency
     * @return float Exchange rate
     */
    public function getExchangeRate(string $from, string $to): float
    {
        return $this->convertRates($from, $to, 1.0);
    }

    /**
     * Check if a currency is supported
     * 
     * @param string $currency Currency code
     * @return bool
     */
    public function isCurrencySupported(string $currency): bool
    {
        $currency = strtoupper($currency);
        $rates = $this->ratesData['rates'] ?? [];
        $base = $this->ratesData['base'] ?? $this->baseCurrency;
        
        return $currency === $base || isset($rates[$currency]);
    }

    /**
     * Get list of all supported currencies
     * 
     * @return array Array of currency codes
     */
    public function getSupportedCurrencies(): array
    {
        $rates = $this->ratesData['rates'] ?? [];
        $base = $this->ratesData['base'] ?? $this->baseCurrency;
        
        $currencies = array_keys($rates);
        if (!in_array($base, $currencies)) {
            array_unshift($currencies, $base);
        }
        
        sort($currencies);
        return $currencies;
    }

    /**
     * Get current rates data
     * 
     * @return array Full rates data structure
     */
    public function getRates(): array
    {
        return $this->ratesData;
    }

    /**
     * Get cache age in seconds
     * 
     * @return int|null Cache age or null if no cache
     */
    public function getCacheAge(): ?int
    {
        $cache = $this->readCache();
        if ($cache === null) {
            return null;
        }

        $age = time() - $cache['timestamp'];
        return $age >= 0 ? $age : null;
    }

    /**
     * Check if cache is valid
     * 
     * @return bool
     */
    public function isCacheValid(): bool
    {
        $age = $this->getCacheAge();
        return $age !== null && $age < $this->cacheExpiryTime;
    }
}