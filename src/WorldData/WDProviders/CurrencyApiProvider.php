<?php
namespace LCSNG\Tools\WorldData\WDProviders;

use LCSNG\Tools\WorldData\WDAbstracts\CurrencyRateProviderAbstract;

final class CurrencyApiProvider extends CurrencyRateProviderAbstract
{
    public function fetchRates(string $baseCurrency): array
    {
        $apiKey = $this->requireApiKey('CurrencyAPI');
        $baseCurrency = strtoupper($baseCurrency);
        $query = http_build_query([
            'apikey' => $apiKey,
            'base_currency' => $baseCurrency,
        ], '', '&', PHP_QUERY_RFC3986);
        $data = $this->requestJson('https://api.currencyapi.com/v3/latest?' . $query, 'CurrencyAPI');

        if (!is_array($data['data'] ?? null) || $data['data'] === []) {
            throw new \RuntimeException('Invalid response from CurrencyAPI.');
        }

        $rates = [];
        foreach ($data['data'] as $currency => $info) {
            if (!is_array($info) || !array_key_exists('value', $info)) {
                throw new \RuntimeException("Invalid rate data from CurrencyAPI for {$currency}.");
            }
            $rates[$currency] = $info['value'];
        }

        return $this->normalizeRates($rates, $baseCurrency);
    }
}
