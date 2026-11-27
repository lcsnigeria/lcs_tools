<?php
namespace LCSNG\Tools\WorldData\WDProviders;

use LCSNG\Tools\WorldData\WDAbstracts\CurrencyRateProviderAbstract;

final class FixerProvider extends CurrencyRateProviderAbstract
{
    public function fetchRates(string $baseCurrency): array
    {
        $apiKey = $this->requireApiKey('Fixer');
        $baseCurrency = strtoupper($baseCurrency);
        $query = http_build_query([
            'access_key' => $apiKey,
            'base' => $baseCurrency,
        ], '', '&', PHP_QUERY_RFC3986);
        $data = $this->requestJson('https://data.fixer.io/api/latest?' . $query, 'Fixer');

        if (!is_array($data['rates'] ?? null)) {
            throw new \RuntimeException('Invalid response from Fixer.');
        }

        $responseBase = is_string($data['base'] ?? null) ? $data['base'] : $baseCurrency;
        $date = is_string($data['date'] ?? null) ? $data['date'] : null;

        return $this->normalizeRates($data['rates'], $responseBase, $date);
    }
}
