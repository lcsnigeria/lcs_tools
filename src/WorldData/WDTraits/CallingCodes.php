<?php
namespace LCSNG\Tools\WorldData\WDTraits;

/**
 * Trait CallingCodes
 *
 * Provides static methods for retrieving and querying international telephone
 * calling-code data from a local JSON data file.
 *
 * Each JSON record carries the following fields:
 *
 *   countryName          – Common English country name
 *   iso2                 – ISO 3166-1 alpha-2 code          (e.g. "NG")
 *   iso3                 – ISO 3166-1 alpha-3 code          (e.g. "NGA")
 *   callingCode          – ITU-T E.164 country calling code (e.g. "+234")
 *   trunkPrefix          – National trunk prefix, or null   (e.g. "0")
 *   internationalPrefix  – International dialing prefix     (e.g. "009")
 *   nanpAreaCodes        – (NANP countries only) array of NANP area codes
 *                          assigned to this country         (e.g. [242])
 *
 * NANP note: Canada, the United States, and a number of Caribbean and Pacific
 * nations all share the country code "+1" (the North American Numbering Plan).
 * Their entries include a `nanpAreaCodes` array so callers can identify the
 * exact country from an area code when needed.
 *
 * @package LCSNG\Tools\WorldData\WDTraits
 */
trait CallingCodes
{
    /**
     * Absolute path to the calling codes JSON data file.
     *
     * @var string
     */
    private static string $callingCodeDataFile = __DIR__ . '/../WDJson/CallingCodes.json';

    // -------------------------------------------------------------------------
    //  Primary look-up methods
    // -------------------------------------------------------------------------

    /**
     * Retrieve calling-code data for a given ISO 3166-1 country code.
     *
     * Accepts either a 2-character alpha-2 code (e.g. "NG") or a 3-character
     * alpha-3 code (e.g. "NGA"). The lookup is case-insensitive.
     *
     * @param  string|null $isoCode        ISO 3166-1 alpha-2 or alpha-3 country code.
     * @param  bool        $objectifyResult When true, returns a stdClass object
     *                                      instead of an associative array.
     *
     * @return array|object                 Calling-code record.
     *
     * @throws \InvalidArgumentException    If $isoCode is not 2 or 3 characters long.
     * @throws \RuntimeException            If no record is found for the given code.
     */
    public static function getCallingCode(?string $isoCode, bool $objectifyResult = false): array|object
    {
        $isIso2 = strlen($isoCode) === 2;
        $isIso3 = strlen($isoCode) === 3;

        if (!$isIso2 && !$isIso3) {
            throw new \InvalidArgumentException(
                "Invalid ISO code: '$isoCode'. Must be 2 (alpha-2) or 3 (alpha-3) characters."
            );
        }

        $isoCode = strtoupper($isoCode);
        $records = self::loadCallingCodeData();

        foreach ($records as $record) {
            $matched = $isIso2
                ? $record['iso2'] === $isoCode
                : $record['iso3'] === $isoCode;

            if ($matched) {
                return $objectifyResult ? (object) $record : $record;
            }
        }

        throw new \RuntimeException(
            "Calling-code data not found for ISO code: '$isoCode'."
        );
    }

    /**
     * Return all countries that share a given calling code.
     *
     * Useful for codes shared by multiple nations, most notably "+1" (NANP),
     * "+7" (Russia & Kazakhstan), and "+61" (Australia & Cocos/Christmas Islands).
     *
     * The leading "+" is optional — both "+234" and "234" are accepted.
     *
     * @param  string $callingCode          ITU-T calling code, with or without leading "+".
     * @param  bool   $objectifyResult      When true, each result is cast to stdClass.
     *
     * @return array                        Indexed array of matching records
     *                                      (empty array when none match).
     */
    public static function getCountriesByCallingCode(string $callingCode, bool $objectifyResult = false): array
    {
        // Normalise: ensure the code always starts with "+"
        $callingCode = '+' . ltrim(trim($callingCode), '+');
        $records     = self::loadCallingCodeData();

        $matches = array_values(
            array_filter($records, fn($r) => $r['callingCode'] === $callingCode)
        );

        if ($objectifyResult) {
            return array_map(fn($r) => (object) $r, $matches);
        }

        return $matches;
    }

    // -------------------------------------------------------------------------
    //  NANP helpers
    // -------------------------------------------------------------------------

    /**
     * Identify a NANP (+1) country from a specific area code.
     *
     * Within the North American Numbering Plan every country has one or more
     * unique 3-digit area codes. This method searches the `nanpAreaCodes`
     * arrays in all "+1" entries and returns the matching country record.
     *
     * @param  int  $areaCode        3-digit NANP area code (e.g. 234 for Nigeria's
     *                               US overlay, 876 for Jamaica).
     * @param  bool $objectifyResult When true, returns a stdClass object.
     *
     * @return array|object          Matching country record.
     *
     * @throws \InvalidArgumentException If $areaCode is not a 3-digit integer.
     * @throws \RuntimeException         If the area code is not found in any NANP entry.
     */
    public static function getCountryByNanpAreaCode(int $areaCode, bool $objectifyResult = false): array|object
    {
        if ($areaCode < 200 || $areaCode > 999) {
            throw new \InvalidArgumentException(
                "Invalid NANP area code: '$areaCode'. Must be a 3-digit integer between 200 and 999."
            );
        }

        $records = self::loadCallingCodeData();

        foreach ($records as $record) {
            if (
                $record['callingCode'] === '+1'
                && isset($record['nanpAreaCodes'])
                && in_array($areaCode, $record['nanpAreaCodes'], true)
            ) {
                return $objectifyResult ? (object) $record : $record;
            }
        }

        throw new \RuntimeException(
            "No NANP country found for area code: '$areaCode'."
        );
    }

    /**
     * Return all NANP (+1) country records from the dataset.
     *
     * @param  bool $objectifyResult When true, each result is cast to stdClass.
     *
     * @return array                 Indexed array of all NANP country records.
     */
    public static function getAllNanpCountries(bool $objectifyResult = false): array
    {
        $matches = array_values(
            array_filter(
                self::loadCallingCodeData(),
                fn($r) => $r['callingCode'] === '+1' && isset($r['nanpAreaCodes'])
            )
        );

        if ($objectifyResult) {
            return array_map(fn($r) => (object) $r, $matches);
        }

        return $matches;
    }

    // -------------------------------------------------------------------------
    //  Listing / utility methods
    // -------------------------------------------------------------------------

    /**
     * Return every calling-code record in the dataset.
     *
     * @param  bool $objectifyResult When true, each record is cast to stdClass.
     *
     * @return array                 Full indexed array of all records.
     */
    public static function getAllCallingCodes(bool $objectifyResult = false): array
    {
        $records = self::loadCallingCodeData();

        if ($objectifyResult) {
            return array_map(fn($r) => (object) $r, $records);
        }

        return $records;
    }

    /**
     * Return a deduplicated, sorted list of every unique calling code
     * present in the dataset (e.g. ["+1", "+7", "+20", …]).
     *
     * @return string[] Sorted array of unique calling-code strings.
     */
    public static function getUniqueCallingCodes(): array
    {
        $codes = array_unique(
            array_column(self::loadCallingCodeData(), 'callingCode')
        );

        // Sort numerically by stripping the leading "+" for comparison
        usort($codes, fn($a, $b) => (int) ltrim($a, '+') <=> (int) ltrim($b, '+'));

        return array_values($codes);
    }

    // -------------------------------------------------------------------------
    //  Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Load, parse, and return the full calling-code dataset.
     *
     * Acts as a single entry-point so the file path and decode logic stay DRY
     * across all public methods.
     *
     * @return array[]  Indexed array of associative calling-code records.
     *
     * @throws \RuntimeException On file-read or JSON-decode failure.
     */
    private static function loadCallingCodeData(): array
    {
        return self::decodeCallingCodeFile(
            self::readCallingCodeFile(self::$callingCodeDataFile)
        );
    }

    /**
     * Read and return the raw string contents of a file.
     *
     * @param  string $filePath Absolute path to the target file.
     *
     * @return string           Raw file contents.
     *
     * @throws \RuntimeException If the file does not exist or cannot be read.
     */
    private static function readCallingCodeFile(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException(
                "Calling-code data file not found: '$filePath'."
            );
        }

        $contents = file_get_contents($filePath);

        if ($contents === false) {
            throw new \RuntimeException(
                "Failed to read calling-code data file: '$filePath'."
            );
        }

        return $contents;
    }

    /**
     * Decode a JSON string into a PHP array.
     *
     * @param  string $json  Raw JSON string.
     *
     * @return array         Decoded associative array.
     *
     * @throws \RuntimeException If the JSON is malformed or decoding fails.
     */
    private static function decodeCallingCodeFile(string $json): array
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                "Failed to decode calling-code JSON: " . json_last_error_msg()
            );
        }

        return $data;
    }
}