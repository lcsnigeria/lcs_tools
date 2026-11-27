<?php
namespace LCSNG\Tools\WorldData\WDProviders;

use LCSNG\Tools\WorldData\WDAbstracts\CurrencyRateProviderAbstract;

final class ExchangeRateApiProvider extends CurrencyRateProviderAbstract
{
    public function fetchRates(string $baseCurrency): array
    {
        $apiKey = $this->requireApiKey('ExchangeRate-API');
        $baseCurrency = strtoupper($baseCurrency);
        $url = sprintf(
            'https://v6.exchangerate-api.com/v6/%s/latest/%s',
            rawurlencode($apiKey),
            rawurlencode($baseCurrency)
        );
        $data = $this->requestJson($url, 'ExchangeRate-API');

        if (!is_array($data['conversion_rates'] ?? null)) {
            throw new \RuntimeException('Invalid response from ExchangeRate-API.');
        }

        $responseBase = is_string($data['base_code'] ?? null) ? $data['base_code'] : $baseCurrency;
        $date = is_string($data['time_last_update_utc'] ?? null) ? $data['time_last_update_utc'] : null;

        return $this->normalizeRates($data['conversion_rates'], $responseBase, $date);
    }
}
