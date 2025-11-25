<?php 
namespace LCSNG\Tools\CountryData\Traits;

trait CountryCode 
{
    /**
     * Extracts the ISO 3166-1 alpha-2 country code from a phone number.
     *
     * @param string|null $number The phone number (may include '+', spaces, dashes, etc.)
     * @return string|null The two-letter country code (e.g., 'US', 'GB') or null if not found
     */
    public static function getIso2ByNumber(?string $number): ?string
    {
        // Validate input
        if ($number === null || $number === '') {
            return null;
        }

        // Clean the number: remove all non-digit characters
        $cleanedNumber = preg_replace('/\D/', '', $number);
        
        if ($cleanedNumber === '' || $cleanedNumber === null) {
            return null;
        }

        // Get calling codes (sorted by length descending for longest-match-first)
        $callingCodes = self::getCallingCodeToIso2Map();

        // Find the longest matching prefix
        foreach ($callingCodes as $code => $iso2) {
            if (str_starts_with($cleanedNumber, $code)) {
                return $iso2;
            }
        }

        return null;
    }

    /**
     * Extracts the ISO 3166-1 alpha-3 country code from a phone number.
     *
     * @param string|null $number The phone number (may include '+', spaces, dashes, etc.)
     * @return string|null The three-letter country code (e.g., 'USA', 'GBR') or null if not found
     */
    public static function getIso3ByNumber(?string $number): ?string
    {
        // Get ISO2 code first
        $iso2 = self::getIso2ByNumber($number);
        
        if ($iso2 === null) {
            return null;
        }

        // Convert ISO2 to ISO3
        return self::getIso3ByIso2($iso2);
    }

    /**
     * Converts ISO 3166-1 alpha-2 code to alpha-3 code.
     *
     * @param string $iso2 The two-letter country code
     * @return string|null The three-letter country code or null if not found
     */
    public static function getIso3ByIso2(string $iso2): ?string
    {
        $iso2to3Map = self::getIso2ToIso3Map();
        return $iso2to3Map[$iso2] ?? null;
    }

    /**
     * Converts ISO 3166-1 alpha-3 code to alpha-2 code.
     *
     * @param string $iso3 The three-letter country code
     * @return string|null The two-letter country code or null if not found
     */
    public static function getIso2ByIso3(string $iso3): ?string
    {
        $iso3to2Map = self::getIso3ToIso2Map();
        return $iso3to2Map[$iso3] ?? null;
    }

    /**
     * Gets the international calling code for a given country.
     *
     * @param string $iso2orIso3 The two-letter (ISO2) or three-letter (ISO3) country code
     * @param bool $addPlusPrefix Whether to add '+' prefix to the calling code (default: false)
     * @return string|null The calling code (e.g., '234', '+234') or null if not found
     */
    public static function getCallingCode(string $iso2orIso3, bool $addPlusPrefix = false): ?string
    {
        // Normalize input to uppercase
        $countryCode = strtoupper(trim($iso2orIso3));
        
        if ($countryCode === '') {
            return null;
        }

        // Determine if input is ISO2 or ISO3 based on length
        $iso2 = null;
        if (strlen($countryCode) === 2) {
            $iso2 = $countryCode;
        } elseif (strlen($countryCode) === 3) {
            $iso2 = self::getIso2ByIso3($countryCode);
        }

        if ($iso2 === null) {
            return null;
        }

        // Search through calling codes to find matching ISO2
        $callingCodes = self::getCallingCodeToIso2Map();
        
        foreach ($callingCodes as $code => $countryIso2) {
            if ($countryIso2 === $iso2) {
                return $addPlusPrefix ? '+' . $code : $code;
            }
        }

        return null;
    }

    /**
     * Returns a comprehensive map of international calling codes to ISO2 country codes.
     * Sorted by code length (descending) for accurate prefix matching.
     *
     * @return array<string, string>
     */
    public static function getCallingCodeToIso2Map(): array
    {
        static $codes = null;
        
        if ($codes !== null) {
            return $codes;
        }

        $codes = [
            // NANP - 4-digit codes (Caribbean & Pacific territories)
            '1242' => 'BS', // Bahamas
            '1246' => 'BB', // Barbados
            '1264' => 'AI', // Anguilla
            '1268' => 'AG', // Antigua & Barbuda
            '1284' => 'VG', // British Virgin Islands
            '1340' => 'VI', // U.S. Virgin Islands
            '1345' => 'KY', // Cayman Islands
            '1441' => 'BM', // Bermuda
            '1473' => 'GD', // Grenada
            '1649' => 'TC', // Turks & Caicos
            '1664' => 'MS', // Montserrat
            '1670' => 'MP', // Northern Mariana Islands
            '1671' => 'GU', // Guam
            '1684' => 'AS', // American Samoa
            '1721' => 'SX', // Sint Maarten
            '1758' => 'LC', // St. Lucia
            '1767' => 'DM', // Dominica
            '1784' => 'VC', // St. Vincent & Grenadines
            '1809' => 'DO', // Dominican Republic
            '1829' => 'DO', // Dominican Republic
            '1849' => 'DO', // Dominican Republic
            '1868' => 'TT', // Trinidad & Tobago
            '1869' => 'KN', // St. Kitts & Nevis
            '1876' => 'JM', // Jamaica
            
            // 3-digit codes - Africa
            '230' => 'MU', // Mauritius
            '231' => 'LR', // Liberia
            '232' => 'SL', // Sierra Leone
            '233' => 'GH', // Ghana
            '234' => 'NG', // Nigeria
            '235' => 'TD', // Chad
            '236' => 'CF', // Central African Republic
            '237' => 'CM', // Cameroon
            '238' => 'CV', // Cape Verde
            '239' => 'ST', // São Tomé & Príncipe
            '240' => 'GQ', // Equatorial Guinea
            '241' => 'GA', // Gabon
            '242' => 'CG', // Congo
            '243' => 'CD', // DR Congo
            '244' => 'AO', // Angola
            '245' => 'GW', // Guinea-Bissau
            '246' => 'IO', // British Indian Ocean Territory
            '248' => 'SC', // Seychelles
            '249' => 'SD', // Sudan
            '250' => 'RW', // Rwanda
            '251' => 'ET', // Ethiopia
            '252' => 'SO', // Somalia
            '253' => 'DJ', // Djibouti
            '254' => 'KE', // Kenya
            '255' => 'TZ', // Tanzania
            '256' => 'UG', // Uganda
            '257' => 'BI', // Burundi
            '258' => 'MZ', // Mozambique
            '260' => 'ZM', // Zambia
            '261' => 'MG', // Madagascar
            '262' => 'RE', // Réunion
            '263' => 'ZW', // Zimbabwe
            '264' => 'NA', // Namibia
            '265' => 'MW', // Malawi
            '266' => 'LS', // Lesotho
            '267' => 'BW', // Botswana
            '268' => 'SZ', // Eswatini
            '269' => 'KM', // Comoros
            
            // 3-digit codes - Other regions
            '290' => 'SH', // St. Helena
            '291' => 'ER', // Eritrea
            '297' => 'AW', // Aruba
            '298' => 'FO', // Faroe Islands
            '299' => 'GL', // Greenland
            '350' => 'GI', // Gibraltar
            '351' => 'PT', // Portugal
            '352' => 'LU', // Luxembourg
            '353' => 'IE', // Ireland
            '354' => 'IS', // Iceland
            '355' => 'AL', // Albania
            '356' => 'MT', // Malta
            '357' => 'CY', // Cyprus
            '358' => 'FI', // Finland
            '359' => 'BG', // Bulgaria
            '370' => 'LT', // Lithuania
            '371' => 'LV', // Latvia
            '372' => 'EE', // Estonia
            '373' => 'MD', // Moldova
            '374' => 'AM', // Armenia
            '375' => 'BY', // Belarus
            '376' => 'AD', // Andorra
            '377' => 'MC', // Monaco
            '378' => 'SM', // San Marino
            '380' => 'UA', // Ukraine
            '381' => 'RS', // Serbia
            '382' => 'ME', // Montenegro
            '383' => 'XK', // Kosovo
            '385' => 'HR', // Croatia
            '386' => 'SI', // Slovenia
            '387' => 'BA', // Bosnia & Herzegovina
            '389' => 'MK', // North Macedonia
            '420' => 'CZ', // Czech Republic
            '421' => 'SK', // Slovakia
            '423' => 'LI', // Liechtenstein
            '500' => 'FK', // Falkland Islands
            '501' => 'BZ', // Belize
            '502' => 'GT', // Guatemala
            '503' => 'SV', // El Salvador
            '504' => 'HN', // Honduras
            '505' => 'NI', // Nicaragua
            '506' => 'CR', // Costa Rica
            '507' => 'PA', // Panama
            '508' => 'PM', // St. Pierre & Miquelon
            '509' => 'HT', // Haiti
            '590' => 'GP', // Guadeloupe
            '591' => 'BO', // Bolivia
            '592' => 'GY', // Guyana
            '593' => 'EC', // Ecuador
            '594' => 'GF', // French Guiana
            '595' => 'PY', // Paraguay
            '596' => 'MQ', // Martinique
            '597' => 'SR', // Suriname
            '598' => 'UY', // Uruguay
            '599' => 'CW', // Curaçao
            '670' => 'TL', // Timor-Leste
            '672' => 'NF', // Norfolk Island
            '673' => 'BN', // Brunei
            '674' => 'NR', // Nauru
            '675' => 'PG', // Papua New Guinea
            '676' => 'TO', // Tonga
            '677' => 'SB', // Solomon Islands
            '678' => 'VU', // Vanuatu
            '679' => 'FJ', // Fiji
            '680' => 'PW', // Palau
            '681' => 'WF', // Wallis & Futuna
            '682' => 'CK', // Cook Islands
            '683' => 'NU', // Niue
            '685' => 'WS', // Samoa
            '686' => 'KI', // Kiribati
            '687' => 'NC', // New Caledonia
            '688' => 'TV', // Tuvalu
            '689' => 'PF', // French Polynesia
            '690' => 'TK', // Tokelau
            '850' => 'KP', // North Korea
            '852' => 'HK', // Hong Kong
            '853' => 'MO', // Macau
            '855' => 'KH', // Cambodia
            '856' => 'LA', // Laos
            '880' => 'BD', // Bangladesh
            '886' => 'TW', // Taiwan
            '960' => 'MV', // Maldives
            '961' => 'LB', // Lebanon
            '962' => 'JO', // Jordan
            '963' => 'SY', // Syria
            '964' => 'IQ', // Iraq
            '965' => 'KW', // Kuwait
            '966' => 'SA', // Saudi Arabia
            '967' => 'YE', // Yemen
            '968' => 'OM', // Oman
            '970' => 'PS', // Palestine
            '971' => 'AE', // United Arab Emirates
            '972' => 'IL', // Israel
            '973' => 'BH', // Bahrain
            '974' => 'QA', // Qatar
            '975' => 'BT', // Bhutan
            '976' => 'MN', // Mongolia
            '977' => 'NP', // Nepal
            '992' => 'TJ', // Tajikistan
            '993' => 'TM', // Turkmenistan
            '994' => 'AZ', // Azerbaijan
            '995' => 'GE', // Georgia
            '996' => 'KG', // Kyrgyzstan
            '998' => 'UZ', // Uzbekistan
            
            // 2-digit codes
            '20' => 'EG', // Egypt
            '27' => 'ZA', // South Africa
            '30' => 'GR', // Greece
            '31' => 'NL', // Netherlands
            '32' => 'BE', // Belgium
            '33' => 'FR', // France
            '34' => 'ES', // Spain
            '36' => 'HU', // Hungary
            '39' => 'IT', // Italy
            '40' => 'RO', // Romania
            '41' => 'CH', // Switzerland
            '43' => 'AT', // Austria
            '44' => 'GB', // United Kingdom
            '45' => 'DK', // Denmark
            '46' => 'SE', // Sweden
            '47' => 'NO', // Norway
            '48' => 'PL', // Poland
            '49' => 'DE', // Germany
            '51' => 'PE', // Peru
            '52' => 'MX', // Mexico
            '53' => 'CU', // Cuba
            '54' => 'AR', // Argentina
            '55' => 'BR', // Brazil
            '56' => 'CL', // Chile
            '57' => 'CO', // Colombia
            '58' => 'VE', // Venezuela
            '60' => 'MY', // Malaysia
            '61' => 'AU', // Australia
            '62' => 'ID', // Indonesia
            '63' => 'PH', // Philippines
            '64' => 'NZ', // New Zealand
            '65' => 'SG', // Singapore
            '66' => 'TH', // Thailand
            '81' => 'JP', // Japan
            '82' => 'KR', // South Korea
            '84' => 'VN', // Vietnam
            '86' => 'CN', // China
            '90' => 'TR', // Turkey
            '91' => 'IN', // India
            '92' => 'PK', // Pakistan
            '93' => 'AF', // Afghanistan
            '94' => 'LK', // Sri Lanka
            '95' => 'MM', // Myanmar
            '98' => 'IR', // Iran
            
            // 1-digit codes (must be last for proper matching)
            '7' => 'RU',   // Russia/Kazakhstan
            '1' => 'US',   // NANP (US/Canada default)
        ];

        // Sort by key length descending (longest first)
        uksort($codes, fn($a, $b) => strlen($b) <=> strlen($a));
        
        return $codes;
    }

    /**
     * Returns a map of ISO2 to ISO3 country codes.
     *
     * @return array<string, string>
     */
    public static function getIso2ToIso3Map(): array
    {
        static $map = null;
        
        if ($map !== null) {
            return $map;
        }

        $map = [
            'AF' => 'AFG', 'AX' => 'ALA', 'AL' => 'ALB', 'DZ' => 'DZA', 'AS' => 'ASM',
            'AD' => 'AND', 'AO' => 'AGO', 'AI' => 'AIA', 'AQ' => 'ATA', 'AG' => 'ATG',
            'AR' => 'ARG', 'AM' => 'ARM', 'AW' => 'ABW', 'AU' => 'AUS', 'AT' => 'AUT',
            'AZ' => 'AZE', 'BS' => 'BHS', 'BH' => 'BHR', 'BD' => 'BGD', 'BB' => 'BRB',
            'BY' => 'BLR', 'BE' => 'BEL', 'BZ' => 'BLZ', 'BJ' => 'BEN', 'BM' => 'BMU',
            'BT' => 'BTN', 'BO' => 'BOL', 'BA' => 'BIH', 'BW' => 'BWA', 'BV' => 'BVT',
            'BR' => 'BRA', 'IO' => 'IOT', 'BN' => 'BRN', 'BG' => 'BGR', 'BF' => 'BFA',
            'BI' => 'BDI', 'KH' => 'KHM', 'CM' => 'CMR', 'CA' => 'CAN', 'CV' => 'CPV',
            'KY' => 'CYM', 'CF' => 'CAF', 'TD' => 'TCD', 'CL' => 'CHL', 'CN' => 'CHN',
            'CX' => 'CXR', 'CC' => 'CCK', 'CO' => 'COL', 'KM' => 'COM', 'CG' => 'COG',
            'CD' => 'COD', 'CK' => 'COK', 'CR' => 'CRI', 'CI' => 'CIV', 'HR' => 'HRV',
            'CU' => 'CUB', 'CW' => 'CUW', 'CY' => 'CYP', 'CZ' => 'CZE', 'DK' => 'DNK',
            'DJ' => 'DJI', 'DM' => 'DMA', 'DO' => 'DOM', 'EC' => 'ECU', 'EG' => 'EGY',
            'SV' => 'SLV', 'GQ' => 'GNQ', 'ER' => 'ERI', 'EE' => 'EST', 'ET' => 'ETH',
            'FK' => 'FLK', 'FO' => 'FRO', 'FJ' => 'FJI', 'FI' => 'FIN', 'FR' => 'FRA',
            'GF' => 'GUF', 'PF' => 'PYF', 'TF' => 'ATF', 'GA' => 'GAB', 'GM' => 'GMB',
            'GE' => 'GEO', 'DE' => 'DEU', 'GH' => 'GHA', 'GI' => 'GIB', 'GR' => 'GRC',
            'GL' => 'GRL', 'GD' => 'GRD', 'GP' => 'GLP', 'GU' => 'GUM', 'GT' => 'GTM',
            'GG' => 'GGY', 'GN' => 'GIN', 'GW' => 'GNB', 'GY' => 'GUY', 'HT' => 'HTI',
            'HM' => 'HMD', 'VA' => 'VAT', 'HN' => 'HND', 'HK' => 'HKG', 'HU' => 'HUN',
            'IS' => 'ISL', 'IN' => 'IND', 'ID' => 'IDN', 'IR' => 'IRN', 'IQ' => 'IRQ',
            'IE' => 'IRL', 'IM' => 'IMN', 'IL' => 'ISR', 'IT' => 'ITA', 'JM' => 'JAM',
            'JP' => 'JPN', 'JE' => 'JEY', 'JO' => 'JOR', 'KZ' => 'KAZ', 'KE' => 'KEN',
            'KI' => 'KIR', 'KP' => 'PRK', 'KR' => 'KOR', 'KW' => 'KWT', 'KG' => 'KGZ',
            'LA' => 'LAO', 'LV' => 'LVA', 'LB' => 'LBN', 'LS' => 'LSO', 'LR' => 'LBR',
            'LY' => 'LBY', 'LI' => 'LIE', 'LT' => 'LTU', 'LU' => 'LUX', 'MO' => 'MAC',
            'MK' => 'MKD', 'MG' => 'MDG', 'MW' => 'MWI', 'MY' => 'MYS', 'MV' => 'MDV',
            'ML' => 'MLI', 'MT' => 'MLT', 'MH' => 'MHL', 'MQ' => 'MTQ', 'MR' => 'MRT',
            'MU' => 'MUS', 'YT' => 'MYT', 'MX' => 'MEX', 'FM' => 'FSM', 'MD' => 'MDA',
            'MC' => 'MCO', 'MN' => 'MNG', 'ME' => 'MNE', 'MS' => 'MSR', 'MA' => 'MAR',
            'MZ' => 'MOZ', 'MM' => 'MMR', 'NA' => 'NAM', 'NR' => 'NRU', 'NP' => 'NPL',
            'NL' => 'NLD', 'NC' => 'NCL', 'NZ' => 'NZL', 'NI' => 'NIC', 'NE' => 'NER',
            'NG' => 'NGA', 'NU' => 'NIU', 'NF' => 'NFK', 'MP' => 'MNP', 'NO' => 'NOR',
            'OM' => 'OMN', 'PK' => 'PAK', 'PW' => 'PLW', 'PS' => 'PSE', 'PA' => 'PAN',
            'PG' => 'PNG', 'PY' => 'PRY', 'PE' => 'PER', 'PH' => 'PHL', 'PN' => 'PCN',
            'PL' => 'POL', 'PT' => 'PRT', 'PR' => 'PRI', 'QA' => 'QAT', 'RE' => 'REU',
            'RO' => 'ROU', 'RU' => 'RUS', 'RW' => 'RWA', 'BL' => 'BLM', 'SH' => 'SHN',
            'KN' => 'KNA', 'LC' => 'LCA', 'MF' => 'MAF', 'PM' => 'SPM', 'VC' => 'VCT',
            'WS' => 'WSM', 'SM' => 'SMR', 'ST' => 'STP', 'SA' => 'SAU', 'SN' => 'SEN',
            'RS' => 'SRB', 'SC' => 'SYC', 'SL' => 'SLE', 'SG' => 'SGP', 'SX' => 'SXM',
            'SK' => 'SVK', 'SI' => 'SVN', 'SB' => 'SLB', 'SO' => 'SOM', 'ZA' => 'ZAF',
            'GS' => 'SGS', 'SS' => 'SSD', 'ES' => 'ESP', 'LK' => 'LKA', 'SD' => 'SDN',
            'SR' => 'SUR', 'SJ' => 'SJM', 'SZ' => 'SWZ', 'SE' => 'SWE', 'CH' => 'CHE',
            'SY' => 'SYR', 'TW' => 'TWN', 'TJ' => 'TJK', 'TZ' => 'TZA', 'TH' => 'THA',
            'TL' => 'TLS', 'TG' => 'TGO', 'TK' => 'TKL', 'TO' => 'TON', 'TT' => 'TTO',
            'TN' => 'TUN', 'TR' => 'TUR', 'TM' => 'TKM', 'TC' => 'TCA', 'TV' => 'TUV',
            'UG' => 'UGA', 'UA' => 'UKR', 'AE' => 'ARE', 'GB' => 'GBR', 'US' => 'USA',
            'UM' => 'UMI', 'UY' => 'URY', 'UZ' => 'UZB', 'VU' => 'VUT', 'VE' => 'VEN',
            'VN' => 'VNM', 'VG' => 'VGB', 'VI' => 'VIR', 'WF' => 'WLF', 'EH' => 'ESH',
            'YE' => 'YEM', 'ZM' => 'ZMB', 'ZW' => 'ZWE', 'XK' => 'XKX',
        ];
        
        return $map;
    }

    /**
     * Returns a map of ISO3 to ISO2 country codes.
     *
     * @return array<string, string>
     */
    public static function getIso3ToIso2Map(): array
    {
        static $map = null;
        
        if ($map !== null) {
            return $map;
        }

        // Flip the ISO2 to ISO3 map to create ISO3 to ISO2 map
        $map = array_flip(self::getIso2ToIso3Map());
        
        return $map;
    }
}