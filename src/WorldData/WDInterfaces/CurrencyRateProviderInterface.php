<?php
namespace LCSNG\Tools\WorldData\WDInterfaces;

interface CurrencyRateProviderInterface
{
    /**
     * Fetch and normalize exchange rates for the requested base currency.
     *
     * @return array{base: string, date: string, rates: array<string, float>}
     */
    public function fetchRates(string $baseCurrency): array;
}
