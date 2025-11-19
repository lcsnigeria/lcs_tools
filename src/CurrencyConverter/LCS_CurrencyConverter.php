<?php
namespace LCSNG\Tools\CurrencyConverter;

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
    private string $apiProvider;
    private ?string $apiKey;
    private string $cacheFile;
    private int $cacheExpiryTime;
    private array $ratesData = [];
    private string $baseCurrency;
    private bool $enableLogging;
    private ?string $logFile;

    /**
     * Constructor
     *
     * @param array $config Configuration options:
     *     - apiProvider (string): API provider name ('exchangerate-api', 'exchangerates-io', 'fasttools', 'currencyapi', 'fixer')
     *     - apiKey (string|null): API key for providers that require it
     *     - cacheFile (string|null): Path to cache file (default: ./currency_cache.json)
     *     - cacheExpiryTime (int): Cache validity in seconds (default: 3600)
     *     - baseCurrency (string): Base currency code (default: 'USD')
     *     - enableLogging (bool): Enable error/info logging (default: false)
     *     - logFile (string|null): Path to log file (default: ./currency_converter.log)
     * 
     * @throws \Exception If configuration is invalid
     */
    public function __construct(array $config)
    {
        $this->validateConfig($config);
        
        $this->apiProvider     = $config['apiProvider'] ?? 'exchangerate-api';
        $this->apiKey          = $config['apiKey'] ?? null;
        $this->cacheFile       = $config['cacheFile'] ?? __DIR__ . '/currency_cache.json';
        $this->cacheExpiryTime = $config['cacheExpiryTime'] ?? 3600;
        $this->baseCurrency    = strtoupper($config['baseCurrency'] ?? 'USD');
        $this->enableLogging   = $config['enableLogging'] ?? false;
        $this->logFile         = $config['logFile'] ?? __DIR__ . '/currency_converter.log';

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
        $supportedProviders = [
            'exchangerate-api',
            'exchangerates-io',
            'fasttools',
            'currencyapi',
            'fixer'
        ];

        if (isset($config['apiProvider']) && !in_array($config['apiProvider'], $supportedProviders)) {
            throw new \Exception("Unsupported API provider. Supported: " . implode(', ', $supportedProviders));
        }

        if (isset($config['cacheExpiryTime']) && (!is_int($config['cacheExpiryTime']) || $config['cacheExpiryTime'] < 0)) {
            throw new \Exception("cacheExpiryTime must be a positive integer");
        }

        if (isset($config['baseCurrency']) && !preg_match('/^[A-Z]{3}$/', strtoupper($config['baseCurrency']))) {
            throw new \Exception("baseCurrency must be a valid 3-letter ISO currency code");
        }
    }

    /**
     * Ensure cache directory exists
     */
    private function ensureCacheDirectory(): void
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
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
        if (file_exists($this->cacheFile)) {
            try {
                $cache = json_decode(file_get_contents($this->cacheFile), true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->log("Cache file corrupted, fetching fresh data", 'WARNING');
                    return $this->refreshRates();
                }

                if (!empty($cache['timestamp']) && (time() - $cache['timestamp']) < $this->cacheExpiryTime) {
                    $this->log("Using cached rates (age: " . (time() - $cache['timestamp']) . "s)");
                    return $cache['data'];
                }
                
                $this->log("Cache expired, fetching fresh data");
            } catch (\Exception $e) {
                $this->log("Error reading cache: " . $e->getMessage(), 'ERROR');
            }
        }

        return $this->refreshRates();
    }

    /**
     * Force refresh rates from API and update cache
     * 
     * @return array Updated rates data
     * @throws \Exception If API request fails
     */
    public function refreshRates(): array
    {
        $this->log("Refreshing rates from API provider: {$this->apiProvider}");

        try {
            $data = match ($this->apiProvider) {
                'exchangerate-api' => $this->fetchExchangeRateApi(),
                'exchangerates-io' => $this->fetchExchangeRatesIo(),
                'fasttools'        => $this->fetchFastToolsCurrency(),
                'currencyapi'      => $this->fetchCurrencyApi(),
                'fixer'            => $this->fetchFixer(),
                default            => throw new \Exception("Unsupported API provider: {$this->apiProvider}")
            };

            $this->validateRatesData($data);

            file_put_contents($this->cacheFile, json_encode([
                'timestamp' => time(),
                'data' => $data
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

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
            throw new \Exception("Invalid rates data: missing or invalid 'rates' array");
        }

        if (empty($data['base'])) {
            throw new \Exception("Invalid rates data: missing 'base' currency");
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
        if (!file_exists($this->cacheFile)) {
            return null;
        }

        $cache = json_decode(file_get_contents($this->cacheFile), true);
        return isset($cache['timestamp']) ? time() - $cache['timestamp'] : null;
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

    // ==================== Provider-Specific Fetch Methods ====================

    /**
     * Fetch rates from ExchangeRate-API
     * @link https://www.exchangerate-api.com/
     */
    private function fetchExchangeRateApi(): array
    {
        $key = $this->apiKey ?? throw new \Exception("API key required for ExchangeRate-API");
        $url = "https://v6.exchangerate-api.com/v6/{$key}/latest/{$this->baseCurrency}";

        $response = $this->makeHttpRequest($url);
        $data = json_decode($response, true);

        if (empty($data['conversion_rates'])) {
            throw new \Exception("Invalid response from ExchangeRate-API");
        }

        return [
            'base'  => $data['base_code'] ?? $this->baseCurrency,
            'date'  => $data['time_last_update_utc'] ?? date('Y-m-d'),
            'rates' => $data['conversion_rates']
        ];
    }

    /**
     * Fetch rates from ExchangeRates.io
     * @link https://exchangerates.io/
     */
    private function fetchExchangeRatesIo(): array
    {
        $key = $this->apiKey ?? throw new \Exception("API key required for ExchangeRates.io");
        $url = "https://api.exchangerates.io/latest?base={$this->baseCurrency}&access_key={$key}";

        $response = $this->makeHttpRequest($url);
        $data = json_decode($response, true);

        if (!isset($data['rates'])) {
            throw new \Exception("Invalid response from ExchangeRates.io");
        }

        return [
            'base'  => $data['base'] ?? $this->baseCurrency,
            'date'  => $data['date'] ?? date('Y-m-d'),
            'rates' => $data['rates']
        ];
    }

    /**
     * Fetch rates from FastTools Currency API
     * @link https://api.fasttools.io/
     */
    private function fetchFastToolsCurrency(): array
    {
        $url = "https://api.fasttools.io/currency/latest?base={$this->baseCurrency}";
        
        $response = $this->makeHttpRequest($url);
        $data = json_decode($response, true);

        if (!isset($data['rates'])) {
            throw new \Exception("Invalid response from FastTools");
        }

        return [
            'base'  => $data['base'] ?? $this->baseCurrency,
            'date'  => $data['date'] ?? date('Y-m-d'),
            'rates' => $data['rates']
        ];
    }

    /**
     * Fetch rates from CurrencyAPI
     * @link https://currencyapi.com/
     */
    private function fetchCurrencyApi(): array
    {
        $key = $this->apiKey ?? throw new \Exception("API key required for CurrencyAPI");
        $url = "https://api.currencyapi.com/v3/latest?apikey={$key}&base_currency={$this->baseCurrency}";

        $response = $this->makeHttpRequest($url);
        $data = json_decode($response, true);

        if (empty($data['data'])) {
            throw new \Exception("Invalid response from CurrencyAPI");
        }

        $rates = [];
        foreach ($data['data'] as $code => $info) {
            $rates[$code] = $info['value'] ?? 0;
        }

        return [
            'base'  => $this->baseCurrency,
            'date'  => date('Y-m-d'),
            'rates' => $rates
        ];
    }

    /**
     * Fetch rates from Fixer.io
     * @link https://fixer.io/
     */
    private function fetchFixer(): array
    {
        $key = $this->apiKey ?? throw new \Exception("API key required for Fixer");
        $url = "http://data.fixer.io/api/latest?access_key={$key}&base={$this->baseCurrency}";

        $response = $this->makeHttpRequest($url);
        $data = json_decode($response, true);

        if (empty($data['rates'])) {
            throw new \Exception("Invalid response from Fixer");
        }

        return [
            'base'  => $data['base'] ?? $this->baseCurrency,
            'date'  => $data['date'] ?? date('Y-m-d'),
            'rates' => $data['rates']
        ];
    }

    /**
     * Make HTTP request with error handling
     * 
     * @param string $url
     * @return string Response body
     * @throws \Exception
     */
    private function makeHttpRequest(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'LCS_CurrencyConverter/1.0'
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            throw new \Exception("HTTP request failed: " . ($error['message'] ?? 'Unknown error'));
        }

        return $response;
    }
}