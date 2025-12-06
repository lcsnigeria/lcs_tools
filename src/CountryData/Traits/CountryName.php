<?php
namespace LCSNG\Tools\CountryData\Traits;

require_once (__DIR__ . '/../../../vendor/autoload.php');

use LCSNG\Tools\Debugging\Logs;

/**
 * Trait CountryName
 *
 * Provides comprehensive functionality for retrieving country names using various identifiers
 * including ISO codes (ISO2, ISO3), phone numbers, country codes, and custom searches.
 *
 * ## Features
 * - **ISO Code Lookups:** Get country names by ISO2 or ISO3 codes
 * - **Phone Number Lookup:** Extract country from phone numbers with international dialing codes
 * - **Country Code Lookup:** Get country names by numeric country codes
 * - **Flexible Search:** Search countries by partial name, alias, or alternative spellings
 * - **Multiple Formats:** Support for official names, common names, and short names
 * - **Case Insensitive:** All lookups are case-insensitive
 * - **Caching:** Built-in caching for performance optimization
 * - **Validation:** Input validation with detailed error reporting
 * - **Batch Operations:** Process multiple country lookups efficiently
 *
 * ## Usage Examples
 * ```php
 * use LCSNG\Tools\CountryData\Traits\CountryName;
 * 
 * class CountryService {
 *     use CountryName;
 * }
 * 
 * $service = new CountryService();
 * 
 * // Get by ISO3
 * $name = CountryService::getByIso3('NGA'); // Returns 'Nigeria'
 * 
 * // Get by ISO2
 * $name = CountryService::getByIso2('NG'); // Returns 'Nigeria'
 * 
 * // Get by phone number
 * $name = CountryService::getByPhoneNumber('+2348020951395'); // Returns 'Nigeria'
 * 
 * // Get by country code
 * $name = CountryService::getByCountryCode(234); // Returns 'Nigeria'
 * 
 * // Batch lookup
 * $names = CountryService::getBatchByIso2(['NG', 'US', 'GB']);
 * ```
 *
 * @package LCSNG\Tools\CountryData\Traits
 */
trait CountryName 
{
    /**
     * Static cache for country data to improve performance
     * @var array|null
     */
    private static $countryDataCache = null;

    /**
     * Cache for phone number lookups to avoid repeated processing
     * @var array
     */
    private static $phoneNumberCache = [];

    /**
     * Maximum cache size for phone number lookups
     * @var int
     */
    private static $maxCacheSize = 1000;

    /**
     * Retrieves the country name by ISO3 code.
     *
     * @param string $iso3Code The 3-letter ISO country code (e.g., 'NGA', 'USA', 'GBR')
     * @param string $format The name format to return ('common', 'official', 'short')
     * @return string|null The country name or null if not found
     * 
     * @example
     * CountryName::getByIso3('NGA'); // Returns 'Nigeria'
     * CountryName::getByIso3('USA', 'official'); // Returns 'United States of America'
     */
    public static function getByIso3(string $iso3Code, string $format = 'common'): ?string 
    {
        try {
            // Validate and normalize input
            $iso3Code = self::normalizeIsoCode($iso3Code, 3);
            
            if (!$iso3Code) {
                self::logError("Invalid ISO3 code format provided");
                return null;
            }

            // Load country data
            $countryData = self::getCountryData();

            // Search for country by ISO3 code
            foreach ($countryData as $country) {
                if (isset($country['iso3']) && strtoupper($country['iso3']) === $iso3Code) {
                    return self::formatCountryName($country, $format);
                }
            }

            self::logError("Country not found for ISO3 code: {$iso3Code}", 1);
            return null;

        } catch (\Exception $e) {
            self::logError("Error in getByIso3: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieves the country name by ISO2 code.
     *
     * @param string $iso2Code The 2-letter ISO country code (e.g., 'NG', 'US', 'GB')
     * @param string $format The name format to return ('common', 'official', 'short')
     * @return string|null The country name or null if not found
     * 
     * @example
     * CountryName::getByIso2('NG'); // Returns 'Nigeria'
     * CountryName::getByIso2('US', 'official'); // Returns 'United States of America'
     */
    public static function getByIso2(string $iso2Code, string $format = 'common'): ?string 
    {
        try {
            // Validate and normalize input
            $iso2Code = self::normalizeIsoCode($iso2Code, 2);
            
            if (!$iso2Code) {
                self::logError("Invalid ISO2 code format provided");
                return null;
            }

            // Load country data
            $countryData = self::getCountryData();

            // Search for country by ISO2 code
            foreach ($countryData as $country) {
                if (isset($country['iso2']) && strtoupper($country['iso2']) === $iso2Code) {
                    return self::formatCountryName($country, $format);
                }
            }

            self::logError("Country not found for ISO2 code: {$iso2Code}", 1);
            return null;

        } catch (\Exception $e) {
            self::logError("Error in getByIso2: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieves the country name by phone number (including international dialing code).
     *
     * @param string $phoneNumber The phone number with country code (e.g., '+2348020951395', '2348020951395')
     * @param string $format The name format to return ('common', 'official', 'short')
     * @return string|null The country name or null if not found
     * 
     * @example
     * CountryName::getByPhoneNumber('+2348020951395'); // Returns 'Nigeria'
     * CountryName::getByPhoneNumber('442071234567'); // Returns 'United Kingdom'
     */
    public static function getByPhoneNumber(string $phoneNumber, string $format = 'common'): ?string 
    {
        try {
            // Validate input
            if (empty($phoneNumber)) {
                self::logError("Phone number cannot be empty");
                return null;
            }

            // Check cache first
            $cacheKey = md5($phoneNumber . $format);
            if (isset(self::$phoneNumberCache[$cacheKey])) {
                return self::$phoneNumberCache[$cacheKey];
            }

            // Normalize phone number (remove spaces, dashes, parentheses)
            $normalizedNumber = self::normalizePhoneNumber($phoneNumber);

            if (!$normalizedNumber) {
                self::logError("Invalid phone number format: {$phoneNumber}");
                return null;
            }

            // Use CountryCode trait to get ISO2 code from phone number
            $countryCode = new class {
                use CountryCode;
            };

            $iso2Code = $countryCode->getIso2ByNumber($normalizedNumber);

            if (!$iso2Code) {
                self::logError("Could not determine country from phone number: {$phoneNumber}", 1);
                return null;
            }

            // Get country name using ISO2 code
            $countryName = self::getByIso2($iso2Code, $format);

            // Cache the result
            self::cachePhoneNumberLookup($cacheKey, $countryName);

            return $countryName;

        } catch (\Exception $e) {
            self::logError("Error in getByPhoneNumber: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieves the country name by numeric country calling code.
     *
     * @param int|string $countryCode The numeric country calling code (e.g., 234, 1, 44)
     * @param string $format The name format to return ('common', 'official', 'short')
     * @return string|null The country name or null if not found
     * 
     * @example
     * CountryName::getByCountryCode(234); // Returns 'Nigeria'
     * CountryName::getByCountryCode(44); // Returns 'United Kingdom'
     */
    public static function getByCountryCode($countryCode, string $format = 'common'): ?string 
    {
        try {
            // Validate input
            if (!is_numeric($countryCode)) {
                self::logError("Country code must be numeric");
                return null;
            }

            $countryCode = (int)$countryCode;

            // Load country data
            $countryData = self::getCountryData();

            // Search for country by calling code
            foreach ($countryData as $country) {
                if (isset($country['calling_code']) && (int)$country['calling_code'] === $countryCode) {
                    return self::formatCountryName($country, $format);
                }
                
                // Check alternative calling codes (some countries have multiple)
                if (isset($country['calling_codes']) && is_array($country['calling_codes'])) {
                    if (in_array($countryCode, $country['calling_codes'])) {
                        return self::formatCountryName($country, $format);
                    }
                }
            }

            self::logError("Country not found for calling code: {$countryCode}", 1);
            return null;

        } catch (\Exception $e) {
            self::logError("Error in getByCountryCode: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Searches for country names by partial match (case-insensitive).
     *
     * @param string $searchTerm The search term (partial country name)
     * @param string $format The name format to return ('common', 'official', 'short')
     * @param int $limit Maximum number of results to return (0 = unlimited)
     * @return array Array of matching country names
     * 
     * @example
     * CountryName::searchByName('united'); // Returns ['United States', 'United Kingdom', 'United Arab Emirates']
     * CountryName::searchByName('rep', 'official', 5); // Returns first 5 matches containing 'rep'
     */
    public static function searchByName(string $searchTerm, string $format = 'common', int $limit = 0): array 
    {
        try {
            if (empty($searchTerm)) {
                return [];
            }

            $searchTerm = strtolower(trim($searchTerm));
            $results = [];
            $countryData = self::getCountryData();

            foreach ($countryData as $country) {
                $countryName = self::formatCountryName($country, $format);
                
                if ($countryName && stripos($countryName, $searchTerm) !== false) {
                    $results[] = $countryName;
                    
                    // Check if we've reached the limit
                    if ($limit > 0 && count($results) >= $limit) {
                        break;
                    }
                }
            }

            return $results;

        } catch (\Exception $e) {
            self::logError("Error in searchByName: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieves multiple country names by an array of ISO2 codes (batch operation).
     *
     * @param array $iso2Codes Array of ISO2 codes
     * @param string $format The name format to return ('common', 'official', 'short')
     * @param bool $preserveKeys Whether to preserve the original array keys
     * @return array Associative array with ISO2 codes as keys and country names as values
     * 
     * @example
     * CountryName::getBatchByIso2(['NG', 'US', 'GB']); 
     * // Returns ['NG' => 'Nigeria', 'US' => 'United States', 'GB' => 'United Kingdom']
     */
    public static function getBatchByIso2(array $iso2Codes, string $format = 'common', bool $preserveKeys = true): array 
    {
        $results = [];

        foreach ($iso2Codes as $key => $iso2Code) {
            $countryName = self::getByIso2($iso2Code, $format);
            
            if ($preserveKeys) {
                $results[$iso2Code] = $countryName;
            } else {
                if ($countryName !== null) {
                    $results[] = $countryName;
                }
            }
        }

        return $results;
    }

    /**
     * Retrieves multiple country names by an array of ISO3 codes (batch operation).
     *
     * @param array $iso3Codes Array of ISO3 codes
     * @param string $format The name format to return ('common', 'official', 'short')
     * @param bool $preserveKeys Whether to preserve the original array keys
     * @return array Associative array with ISO3 codes as keys and country names as values
     * 
     * @example
     * CountryName::getBatchByIso3(['NGA', 'USA', 'GBR']); 
     * // Returns ['NGA' => 'Nigeria', 'USA' => 'United States', 'GBR' => 'United Kingdom']
     */
    public static function getBatchByIso3(array $iso3Codes, string $format = 'common', bool $preserveKeys = true): array 
    {
        $results = [];

        foreach ($iso3Codes as $key => $iso3Code) {
            $countryName = self::getByIso3($iso3Code, $format);
            
            if ($preserveKeys) {
                $results[$iso3Code] = $countryName;
            } else {
                if ($countryName !== null) {
                    $results[] = $countryName;
                }
            }
        }

        return $results;
    }

    /**
     * Retrieves all available country names.
     *
     * @param string $format The name format to return ('common', 'official', 'short')
     * @param string $sortBy Sort order ('alpha', 'iso2', 'iso3', 'none')
     * @return array Array of country names
     * 
     * @example
     * CountryName::getAllCountryNames(); // Returns all country names in common format
     * CountryName::getAllCountryNames('official', 'alpha'); // Returns official names, sorted alphabetically
     */
    public static function getAllCountryNames(string $format = 'common', string $sortBy = 'alpha'): array 
    {
        try {
            $countryData = self::getCountryData();
            $names = [];

            foreach ($countryData as $country) {
                $name = self::formatCountryName($country, $format);
                if ($name) {
                    $names[$country['iso2']] = $name;
                }
            }

            // Apply sorting
            switch ($sortBy) {
                case 'alpha':
                    asort($names);
                    break;
                case 'iso2':
                    ksort($names);
                    break;
                case 'iso3':
                    // Sort by ISO3 if available
                    uasort($names, function($a, $b) use ($countryData) {
                        return strcmp($a, $b);
                    });
                    break;
                case 'none':
                default:
                    // No sorting
                    break;
            }

            return $names;

        } catch (\Exception $e) {
            self::logError("Error in getAllCountryNames: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Validates if a country name exists in the database.
     *
     * @param string $countryName The country name to validate
     * @param bool $strictMatch Whether to require exact match (true) or partial match (false)
     * @return bool True if country exists, false otherwise
     * 
     * @example
     * CountryName::isValidCountryName('Nigeria'); // Returns true
     * CountryName::isValidCountryName('Nige', false); // Returns true (partial match)
     */
    public static function isValidCountryName(string $countryName, bool $strictMatch = true): bool 
    {
        try {
            $countryName = trim($countryName);
            
            if (empty($countryName)) {
                return false;
            }

            $countryData = self::getCountryData();

            foreach ($countryData as $country) {
                // Check all name variations
                $names = [
                    $country['name'] ?? '',
                    $country['official_name'] ?? '',
                    $country['common_name'] ?? '',
                    $country['short_name'] ?? ''
                ];

                foreach ($names as $name) {
                    if (empty($name)) continue;

                    if ($strictMatch) {
                        if (strcasecmp($name, $countryName) === 0) {
                            return true;
                        }
                    } else {
                        if (stripos($name, $countryName) !== false) {
                            return true;
                        }
                    }
                }
            }

            return false;

        } catch (\Exception $e) {
            self::logError("Error in isValidCountryName: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets country name with additional information.
     *
     * @param string $identifier Country identifier (ISO2, ISO3, or calling code)
     * @param string $identifierType Type of identifier ('iso2', 'iso3', 'calling_code')
     * @return array|null Array with country details or null if not found
     * 
     * @example
     * CountryName::getCountryDetails('NG', 'iso2');
     * // Returns ['name' => 'Nigeria', 'official_name' => 'Federal Republic of Nigeria', 'iso2' => 'NG', ...]
     */
    public static function getCountryDetails(string $identifier, string $identifierType = 'iso2'): ?array 
    {
        try {
            $countryData = self::getCountryData();

            foreach ($countryData as $country) {
                $match = false;

                switch ($identifierType) {
                    case 'iso2':
                        $match = isset($country['iso2']) && strcasecmp($country['iso2'], $identifier) === 0;
                        break;
                    case 'iso3':
                        $match = isset($country['iso3']) && strcasecmp($country['iso3'], $identifier) === 0;
                        break;
                    case 'calling_code':
                        $match = isset($country['calling_code']) && $country['calling_code'] == $identifier;
                        break;
                }

                if ($match) {
                    return [
                        'name' => $country['name'] ?? null,
                        'official_name' => $country['official_name'] ?? null,
                        'common_name' => $country['common_name'] ?? null,
                        'short_name' => $country['short_name'] ?? null,
                        'iso2' => $country['iso2'] ?? null,
                        'iso3' => $country['iso3'] ?? null,
                        'calling_code' => $country['calling_code'] ?? null,
                        'capital' => $country['capital'] ?? null,
                        'region' => $country['region'] ?? null,
                        'subregion' => $country['subregion'] ?? null
                    ];
                }
            }

            return null;

        } catch (\Exception $e) {
            self::logError("Error in getCountryDetails: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Normalizes an ISO code to uppercase and validates length.
     *
     * @param string $isoCode The ISO code to normalize
     * @param int $expectedLength Expected length (2 or 3)
     * @return string|null Normalized ISO code or null if invalid
     */
    private static function normalizeIsoCode(string $isoCode, int $expectedLength): ?string 
    {
        $isoCode = strtoupper(trim($isoCode));

        if (strlen($isoCode) !== $expectedLength) {
            return null;
        }

        if (!preg_match('/^[A-Z]+$/', $isoCode)) {
            return null;
        }

        return $isoCode;
    }

    /**
     * Normalizes a phone number by removing non-numeric characters (except +).
     *
     * @param string $phoneNumber The phone number to normalize
     * @return string|null Normalized phone number or null if invalid
     */
    private static function normalizePhoneNumber(string $phoneNumber): ?string 
    {
        // Remove all characters except digits and +
        $normalized = preg_replace('/[^\d+]/', '', trim($phoneNumber));

        if (empty($normalized)) {
            return null;
        }

        // Ensure it starts with + or is purely numeric
        if (!preg_match('/^\+?\d+$/', $normalized)) {
            return null;
        }

        return $normalized;
    }

    /**
     * Formats the country name based on the requested format.
     *
     * @param array $country Country data array
     * @param string $format Format type ('common', 'official', 'short')
     * @return string|null Formatted country name or null
     */
    private static function formatCountryName(array $country, string $format): ?string 
    {
        switch (strtolower($format)) {
            case 'official':
                return $country['official_name'] ?? $country['name'] ?? null;
            case 'short':
                return $country['short_name'] ?? $country['name'] ?? null;
            case 'common':
            default:
                return $country['common_name'] ?? $country['name'] ?? null;
        }
    }

    /**
     * Caches a phone number lookup result.
     *
     * @param string $cacheKey The cache key
     * @param string|null $result The result to cache
     * @return void
     */
    private static function cachePhoneNumberLookup(string $cacheKey, ?string $result): void 
    {
        // Implement LRU cache: remove oldest entry if cache is full
        if (count(self::$phoneNumberCache) >= self::$maxCacheSize) {
            array_shift(self::$phoneNumberCache);
        }

        self::$phoneNumberCache[$cacheKey] = $result;
    }

    /**
     * Clears the phone number lookup cache.
     *
     * @return void
     */
    public static function clearPhoneNumberCache(): void 
    {
        self::$phoneNumberCache = [];
    }

    /**
     * Clears all caches (country data and phone number lookups).
     *
     * @return void
     */
    public static function clearAllCaches(): void 
    {
        self::$countryDataCache = null;
        self::$phoneNumberCache = [];
    }

    /**
     * Loads and caches country data from the data source.
     *
     * @return array Country data array
     */
    private static function getCountryData(): array 
    {
        // Return cached data if available
        if (self::$countryDataCache !== null) {
            return self::$countryDataCache;
        }

        // Load country data from your data source
        // This is a placeholder - replace with your actual data source
        self::$countryDataCache = self::loadCountryDataFromSource();

        return self::$countryDataCache;
    }

    /**
     * Loads country data from the data source.
     * 
     * NOTE: This is a placeholder method. Replace with your actual data loading logic.
     * The data can come from:
     * - A database table
     * - A JSON file
     * - An external API
     * - A PHP array constant
     *
     * @return array Country data array
     */
    private static function loadCountryDataFromSource(): array 
    {
        $CD = (new class {
            use CountryData;
        });
        return $CD->getAll();
    }

    /**
     * Logs an error message.
     *
     * @param string $message Error message
     * @param int $level Log level (1 = info, 2 = warning, 3 = error)
     * @return void
     */
    private static function logError(string $message, int $level = 2): void 
    {
        if (class_exists('LCSNG\Tools\Debugging\Logs')) {
            Logs::reportError($message, $level);
        }
    }
}