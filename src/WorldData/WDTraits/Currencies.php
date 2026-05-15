<?php
namespace LCSNG\Tools\WorldData\WDTraits;

/**
 * Trait Currencies
 *
 * Provides static methods for retrieving world currency data from a local
 * JSON data file, keyed by ISO 3166-1 alpha-2 or alpha-3 country codes.
 *
 * @package LCSNG\Tools\WorldData\WDTraits
 */
trait Currencies
{
    /**
     * Absolute path to the currencies JSON data file.
     *
     * @var string
     */
    private static string $currencyDataFile = __DIR__ . '/../WDJson/Currencies.json';

    /**
     * Retrieve currency data for a given ISO 3166-1 country code.
     *
     * Accepts either a 2-character alpha-2 code (e.g. "NG") or a
     * 3-character alpha-3 code (e.g. "NGA"). The lookup is case-insensitive.
     *
     * @param  string|null $isoCode        ISO 3166-1 alpha-2 or alpha-3 country code.
     * @param  bool        $objectifyResult When true, returns the result as a stdClass
     *                                      object instead of an associative array.
     *
     * @return array|object                 Currency data as an array or stdClass object.
     *
     * @throws \InvalidArgumentException    If $isoCode is not 2 or 3 characters long.
     * @throws \RuntimeException            If no currency entry is found for the given code.
     */
    public static function getCurrency(?string $isoCode, bool $objectifyResult = false): array|object
    {
        $isIso2 = strlen($isoCode) === 2;
        $isIso3 = strlen($isoCode) === 3;

        if (!$isIso2 && !$isIso3) {
            throw new \InvalidArgumentException(
                "Invalid ISO code: '$isoCode'. Must be 2 (alpha-2) or 3 (alpha-3) characters."
            );
        }

        $isoCode       = strtoupper($isoCode);
        $currenciesData = self::decodeCurrenciesFileContents(self::readCurrenciesFileContents(self::$currencyDataFile));

        foreach ($currenciesData as $currency) {
            $matched = $isIso2
                ? $currency['iso2'] === $isoCode
                : $currency['iso3'] === $isoCode;

            if ($matched) {
                return $objectifyResult ? (object) $currency : $currency;
            }
        }

        throw new \RuntimeException(
            "Currency data not found for ISO code: '$isoCode'."
        );
    }

    /**
     * Read and return the raw contents of a file.
     *
     * @param  string $filePath Absolute path to the target file.
     *
     * @return string           Raw file contents.
     *
     * @throws \RuntimeException If the file does not exist or cannot be read.
     */
    private static function readCurrenciesFileContents(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Currency data file not found: '$filePath'.");
        }

        $contents = file_get_contents($filePath);

        if ($contents === false) {
            throw new \RuntimeException("Failed to read currency data file: '$filePath'.");
        }

        return $contents;
    }

    /**
     * Decode a JSON string into a PHP array.
     *
     * @param  string $fileContent Raw JSON string.
     *
     * @return array               Decoded associative array.
     *
     * @throws \RuntimeException   If the JSON is malformed or decoding fails.
     */
    private static function decodeCurrenciesFileContents(string $fileContent): array
    {
        $data = json_decode($fileContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                "Failed to decode currencies JSON: " . json_last_error_msg()
            );
        }

        return $data;
    }
}