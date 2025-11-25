<?php 
namespace LCSNG\Tools\CountryData;

use LCSNG\Tools\CountryData\Traits\CountryCode;
use LCSNG\Tools\CountryData\Traits\CountryCurrency;

/**
 * LCS Country Data
 * 
 * A comprehensive utility class for working with country data including country codes,
 * currencies, and phone number analysis.
 * 
 * @package LCSNG\Tools\CountryData
 * @author LCS
 * @version 1.0.0
 * 
 * @example
 * ```php
 * $countryData = new LCS_CountryData();
 * 
 * // Get ISO2 country code from phone number
 * $iso2 = $countryData->countryCode::getIso2ByNumber('+234 803 123 4567');
 * // Returns: 'NG'
 * 
 * // Get ISO3 country code from phone number
 * $iso3 = $countryData->countryCode::getIso3ByNumber('+1 234 567 8900');
 * // Returns: 'USA'
 * 
 * // Convert ISO2 to ISO3
 * $iso3 = $countryData->countryCode::getIso3FromIso2('GB');
 * // Returns: 'GBR'
 * 
 * // Get calling codes map
 * $codes = $countryData->countryCode::getCallingCodes();
 * 
 * // Work with country currencies
 * // (methods available via $countryData->countryCurrency)
 * ```
 */
class LCS_CountryData 
{
    /**
     * Country code utilities
     * 
     * Provides methods for extracting and converting country codes from phone numbers.
     * Supports ISO 3166-1 alpha-2 and alpha-3 country codes.
     * 
     * @var object Instance with CountryCode trait
     */
    public $countryCode;

    /**
     * Country currency utilities
     * 
     * Provides methods for working with country currencies and related data.
     * 
     * @var object Instance with CountryCurrency trait
     */
    public $countryCurrency;

    /**
     * Initialize the LCS Country Data instance
     * 
     * Creates anonymous class instances that use the CountryCode and CountryCurrency traits,
     * making their static methods accessible through instance properties.
     * 
     * @return self Returns the instance for potential method chaining
     */
    public function __construct()
    {
        $this->countryCode = (new class {
            use CountryCode;
        });

        $this->countryCurrency = (new class {
            use CountryCurrency;
        });

        return $this;
    }
}