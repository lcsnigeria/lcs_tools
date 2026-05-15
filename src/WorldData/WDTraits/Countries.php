<?php
namespace LCSNG\Tools\WorldData\WDTraits;

/*
 * Trait Countries
 *
 * Provides static methods for retrieving world country data from a local
 * JSON data file, keyed by ISO 3166-1 alpha-2 or alpha-3 country codes.
 *
 * @package LCSNG\Tools\WorldData\WDTraits
 */
trait Countries  
{
    /**
     * Absolute path to the countries JSON data file.
     *
     * @var string
     */
    private static string $countriesDataFile = __DIR__ . '/../WDJson/Countries.json';

    /**
     * Retrieve an array of all countries with their details.
     *
     * @return array Array of country data, where each element is an associative array
     *               containing details such as name, ISO codes, capital, population, etc.
     *
     * @throws \RuntimeException If the countries data file cannot be read or decoded.
     */
    public static function getAllCountries(): array
    {
        return self::decodeCountryFileContents(self::readCountryFileContents(self::$countriesDataFile));
    }

    public static function getCountry(?string $isoCode, bool $objectifyResult = false): array|object
    {
        $isIso2 = strlen($isoCode) === 2;
        $isIso3 = strlen($isoCode) === 3;

        if (!$isIso2 && !$isIso3) {
            throw new \InvalidArgumentException(
                "Invalid ISO code: '$isoCode'. Must be 2 (alpha-2) or 3 (alpha-3) characters."
            );
        }

        $isoCode       = strtoupper($isoCode);
        $countriesData = self::decodeCountryFileContents(self::readCountryFileContents(self::$countriesDataFile));

        foreach ($countriesData as $country) {
            $matched = $isIso2
                ? $country['iso2'] === $isoCode
                : $country['iso3'] === $isoCode;

            if ($matched) {
                return $objectifyResult ? (object) $country : $country;
            }
        }

        throw new \RuntimeException("No country found for ISO code: '$isoCode'.");
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
    private static function readCountryFileContents(string $filePath): string
    {   
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Countries data file not found: '$filePath'.");
        }

        $contents = file_get_contents($filePath);

        if ($contents === false) {
            throw new \RuntimeException("Failed to read countries data file: '$filePath'.");
        }

        return $contents;
    }

    /**
     * Decode JSON file contents into an array.
     *
     * @param  string $jsonString Raw JSON string to decode.
     *
     * @return array              Decoded data as an associative array.
     *
     * @throws \RuntimeException   If the JSON cannot be decoded or is invalid.
     */
    private static function decodeCountryFileContents(string $jsonString): array
    {
        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Failed to decode JSON: " . json_last_error_msg());
        }

        return $data;
    }
}