<?php
namespace LCSNG\Tools\WorldData\WDAbstracts;

use LCSNG\Tools\WorldData\WDInterfaces\CurrencyRateProviderInterface;

abstract class CurrencyRateProviderAbstract implements CurrencyRateProviderInterface
{
    protected ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey;
    }

    protected function requireApiKey(string $providerName): string
    {
        $apiKey = trim((string) $this->apiKey);
        if ($apiKey === '') {
            throw new \InvalidArgumentException("API key required for {$providerName}.");
        }

        return $apiKey;
    }

    protected function requestJson(string $url, string $providerName): array
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'LCS_CurrencyConverter/1.0',
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $error = error_get_last();
            throw new \RuntimeException(
                "HTTP request to {$providerName} failed: " . ($error['message'] ?? 'Unknown error')
            );
        }

        $httpCode = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                $httpCode = (int) $matches[1];
            }
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("HTTP request to {$providerName} failed with status {$httpCode}.");
        }

        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException("Invalid JSON response from {$providerName}.", 0, $e);
        }

        if (!is_array($data)) {
            throw new \RuntimeException("Invalid response structure from {$providerName}.");
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $rates
     * @return array{base: string, date: string, rates: array<string, float>}
     */
    protected function normalizeRates(array $rates, string $baseCurrency, ?string $date = null): array
    {
        $baseCurrency = strtoupper(trim($baseCurrency));
        if (!preg_match('/^[A-Z]{3}$/', $baseCurrency) || $rates === []) {
            throw new \RuntimeException('Invalid rates data: missing or invalid base currency or rates.');
        }

        $normalizedRates = [];
        foreach ($rates as $currency => $rate) {
            $currency = strtoupper((string) $currency);
            if (
                !preg_match('/^[A-Z]{3}$/', $currency)
                || !is_numeric($rate)
                || !is_finite((float) $rate)
                || (float) $rate <= 0
            ) {
                throw new \RuntimeException("Invalid exchange rate for {$currency}.");
            }

            $normalizedRates[$currency] = (float) $rate;
        }

        return [
            'base' => $baseCurrency,
            'date' => $date !== null && $date !== '' ? $date : date('Y-m-d'),
            'rates' => $normalizedRates,
        ];
    }
}
