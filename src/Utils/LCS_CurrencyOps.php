<?php
namespace LCSNG\Tools\Utils;

/**
 * Class LCS_CurrencyOps
 *
 * Provides operations and utilities related to currency handling within the LCS system.
 *
 * This class may include methods for currency conversion, formatting, validation,
 * and other currency-related functionalities.
 *
 * @package lcsTools
 */
class LCS_CurrencyOps 
{
    /**
     * Get the currency symbol for a given currency code.
     *
     * @param string $currencyCode The ISO 4217 currency code (e.g., 'NGN').
     * @return string The symbol associated with the currency code.
     * @throws \Exception If the currency code is not supported.
     */
    public static function getCurrencySymbol(string $currencyCode): string {
        // Normalize $currencyCode to uppercase
        $currencyCode = strtoupper($currencyCode);

        $currencySymbols = [
            'NGN' => '₦', // Nigerian Naira
            'USD' => '$', // US Dollar
            'ZAR' => 'R', // South African Rand
            'GHS' => 'GH₵', // Ghanaian Cedi
            'KES' => 'KSh', // Kenyan Shilling
        ];

        if (!array_key_exists($currencyCode, $currencySymbols)) {
            throw new \Exception("Unsupported currency code: $currencyCode");
        }

        return $currencySymbols[$currencyCode];
    }

}