<?php 
namespace LCSNG\Tools\CountryData\Traits;

trait CountryCurrency 
{
    /**
     * Gets the currency information for a given ISO 3166-1 alpha-2 country code.
     *
     * @param string|null $iso2 The two-letter country code (e.g., 'US', 'GB', 'NG')
     * @param string $returnType The type of data to return: 'name', 'symbol', or 'code'
     * @return string|null The requested currency data, or null if country code not found or invalid return type
     * @throws InvalidArgumentException If return type is invalid
     */
    public static function getCurrency(?string $iso2, string $returnType = 'name'): ?string
    {
        // Validate input
        if ($iso2 === null || $iso2 === '') {
            return null;
        }

        // Normalize inputs
        $iso2 = strtoupper(trim($iso2));
        $returnType = strtolower(trim($returnType));

        // Validate return type
        $validReturnTypes = ['name', 'symbol', 'code'];
        if (!in_array($returnType, $validReturnTypes, true)) {
            throw new \InvalidArgumentException(
                "Invalid return type '{$returnType}'. Valid types are: " . implode(', ', $validReturnTypes)
            );
        }

        // Get currency data
        $currencies = self::getData();

        if (!isset($currencies[$iso2])) {
            return null;
        }

        $currencyData = $currencies[$iso2];

        return $currencyData[$returnType];
    }


    /**
     * Returns a comprehensive map of ISO2 country codes to currency data.
     *
     * @return array<string, array{name: string, symbol: string, code: string}>
     */
    public static function getData(): array
    {
        static $data = null;
        if ($data !== null) {
            return $data;
        }
        $data = [
            // A
            'AD' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'AE' => ['name' => 'UAE Dirham', 'symbol' => 'د.إ', 'code' => 'AED'],
            'AF' => ['name' => 'Afghan Afghani', 'symbol' => '؋', 'code' => 'AFN'],
            'AG' => ['name' => 'East Caribbean Dollar', 'symbol' => 'EC$', 'code' => 'XCD'],
            'AI' => ['name' => 'East Caribbean Dollar', 'symbol' => 'EC$', 'code' => 'XCD'],
            'AL' => ['name' => 'Albanian Lek', 'symbol' => 'L', 'code' => 'ALL'],
            'AM' => ['name' => 'Armenian Dram', 'symbol' => '֏', 'code' => 'AMD'],
            'AO' => ['name' => 'Angolan Kwanza', 'symbol' => 'Kz', 'code' => 'AOA'],
            'AQ' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'AR' => ['name' => 'Argentine Peso', 'symbol' => '$', 'code' => 'ARS'],
            'AS' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'AT' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'AU' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'code' => 'AUD'],
            'AW' => ['name' => 'Aruban Florin', 'symbol' => 'ƒ', 'code' => 'AWG'],
            'AX' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'AZ' => ['name' => 'Azerbaijani Manat', 'symbol' => '₼', 'code' => 'AZN'],

            // B
            'BA' => ['name' => 'Bosnia-Herzegovina Convertible Mark', 'symbol' => 'KM', 'code' => 'BAM'],
            'BB' => ['name' => 'Barbadian Dollar', 'symbol' => 'Bds$', 'code' => 'BBD'],
            'BD' => ['name' => 'Bangladeshi Taka', 'symbol' => '৳', 'code' => 'BDT'],
            'BE' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'BF' => ['name' => 'West African CFA Franc', 'symbol' => 'Fr', 'code' => 'XOF'],
            'BG' => ['name' => 'Bulgarian Lev', 'symbol' => 'лв', 'code' => 'BGN'],
            'BH' => ['name' => 'Bahraini Dinar', 'symbol' => 'ب.د', 'code' => 'BHD'],
            'BI' => ['name' => 'Burundian Franc', 'symbol' => 'Fr', 'code' => 'BIF'],
            'BJ' => ['name' => 'West African CFA Franc', 'symbol' => 'Fr', 'code' => 'XOF'],
            'BL' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'BM' => ['name' => 'Bermudian Dollar', 'symbol' => '$', 'code' => 'BMD'],
            'BN' => ['name' => 'Brunei Dollar', 'symbol' => 'B$', 'code' => 'BND'],
            'BO' => ['name' => 'Bolivian Boliviano', 'symbol' => 'Bs.', 'code' => 'BOB'],
            'BQ' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'BR' => ['name' => 'Brazilian Real', 'symbol' => 'R$', 'code' => 'BRL'],
            'BS' => ['name' => 'Bahamian Dollar', 'symbol' => 'B$', 'code' => 'BSD'],
            'BT' => ['name' => 'Bhutanese Ngultrum', 'symbol' => 'Nu.', 'code' => 'BTN'],
            'BV' => ['name' => 'Norwegian Krone', 'symbol' => 'kr', 'code' => 'NOK'],
            'BW' => ['name' => 'Botswana Pula', 'symbol' => 'P', 'code' => 'BWP'],
            'BY' => ['name' => 'Belarusian Ruble', 'symbol' => 'Br', 'code' => 'BYN'],
            'BZ' => ['name' => 'Belize Dollar', 'symbol' => 'BZ$', 'code' => 'BZD'],

            // C
            'CA' => ['name' => 'Canadian Dollar', 'symbol' => 'C$', 'code' => 'CAD'],
            'CC' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'code' => 'AUD'],
            'CD' => ['name' => 'Congolese Franc', 'symbol' => 'Fr', 'code' => 'CDF'],
            'CF' => ['name' => 'Central African CFA Franc', 'symbol' => 'Fr', 'code' => 'XAF'],
            'CG' => ['name' => 'Central African CFA Franc', 'symbol' => 'Fr', 'code' => 'XAF'],
            'CH' => ['name' => 'Swiss Franc', 'symbol' => 'Fr', 'code' => 'CHF'],
            'CI' => ['name' => 'West African CFA Franc', 'symbol' => 'Fr', 'code' => 'XOF'],
            'CK' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'code' => 'NZD'],
            'CL' => ['name' => 'Chilean Peso', 'symbol' => '$', 'code' => 'CLP'],
            'CM' => ['name' => 'Central African CFA Franc', 'symbol' => 'Fr', 'code' => 'XAF'],
            'CN' => ['name' => 'Chinese Yuan', 'symbol' => '¥', 'code' => 'CNY'],
            'CO' => ['name' => 'Colombian Peso', 'symbol' => '$', 'code' => 'COP'],
            'CR' => ['name' => 'Costa Rican Colón', 'symbol' => '₡', 'code' => 'CRC'],
            'CU' => ['name' => 'Cuban Peso', 'symbol' => '$', 'code' => 'CUP'],
            'CV' => ['name' => 'Cape Verdean Escudo', 'symbol' => 'Esc', 'code' => 'CVE'],
            'CW' => ['name' => 'Netherlands Antillean Guilder', 'symbol' => 'ƒ', 'code' => 'ANG'],
            'CY' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'CZ' => ['name' => 'Czech Koruna', 'symbol' => 'Kč', 'code' => 'CZK'],

            // D
            'DE' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'DJ' => ['name' => 'Djiboutian Franc', 'symbol' => 'Fr', 'code' => 'DJF'],
            'DK' => ['name' => 'Danish Krone', 'symbol' => 'kr', 'code' => 'DKK'],
            'DM' => ['name' => 'East Caribbean Dollar', 'symbol' => 'EC$', 'code' => 'XCD'],
            'DO' => ['name' => 'Dominican Peso', 'symbol' => 'RD$', 'code' => 'DOP'],
            'DZ' => ['name' => 'Algerian Dinar', 'symbol' => 'د.ج', 'code' => 'DZD'],

            // E
            'EC' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'EE' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'EG' => ['name' => 'Egyptian Pound', 'symbol' => 'E£', 'code' => 'EGP'],
            'EH' => ['name' => 'Moroccan Dirham', 'symbol' => 'د.م.', 'code' => 'MAD'],
            'ER' => ['name' => 'Eritrean Nakfa', 'symbol' => 'Nfk', 'code' => 'ERN'],
            'ES' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'ET' => ['name' => 'Ethiopian Birr', 'symbol' => 'Br', 'code' => 'ETB'],

            // F
            'FI' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'FJ' => ['name' => 'Fijian Dollar', 'symbol' => 'FJ$', 'code' => 'FJD'],
            'FK' => ['name' => 'Falkland Islands Pound', 'symbol' => '£', 'code' => 'FKP'],
            'FM' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'FO' => ['name' => 'Danish Krone', 'symbol' => 'kr', 'code' => 'DKK'],
            'FR' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],

            // G
            'GA' => ['name' => 'Central African CFA Franc', 'symbol' => 'Fr', 'code' => 'XAF'],
            'GB' => ['name' => 'British Pound Sterling', 'symbol' => '£', 'code' => 'GBP'],
            'GD' => ['name' => 'East Caribbean Dollar', 'symbol' => 'EC$', 'code' => 'XCD'],
            'GE' => ['name' => 'Georgian Lari', 'symbol' => '₾', 'code' => 'GEL'],
            'GF' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'GG' => ['name' => 'British Pound Sterling', 'symbol' => '£', 'code' => 'GBP'],
            'GH' => ['name' => 'Ghanaian Cedi', 'symbol' => '₵', 'code' => 'GHS'],
            'GI' => ['name' => 'Gibraltar Pound', 'symbol' => '£', 'code' => 'GIP'],
            'GL' => ['name' => 'Danish Krone', 'symbol' => 'kr', 'code' => 'DKK'],
            'GM' => ['name' => 'Gambian Dalasi', 'symbol' => 'D', 'code' => 'GMD'],
            'GN' => ['name' => 'Guinean Franc', 'symbol' => 'Fr', 'code' => 'GNF'],
            'GP' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'GQ' => ['name' => 'Central African CFA Franc', 'symbol' => 'Fr', 'code' => 'XAF'],
            'GR' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'GS' => ['name' => 'British Pound Sterling', 'symbol' => '£', 'code' => 'GBP'],
            'GT' => ['name' => 'Guatemalan Quetzal', 'symbol' => 'Q', 'code' => 'GTQ'],
            'GU' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'GW' => ['name' => 'West African CFA Franc', 'symbol' => 'Fr', 'code' => 'XOF'],
            'GY' => ['name' => 'Guyanese Dollar', 'symbol' => 'G$', 'code' => 'GYD'],

            // H
            'HK' => ['name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'code' => 'HKD'],
            'HM' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'code' => 'AUD'],
            'HN' => ['name' => 'Honduran Lempira', 'symbol' => 'L', 'code' => 'HNL'],
            'HR' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'HT' => ['name' => 'Haitian Gourde', 'symbol' => 'G', 'code' => 'HTG'],
            'HU' => ['name' => 'Hungarian Forint', 'symbol' => 'Ft', 'code' => 'HUF'],

            // I
            'ID' => ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'code' => 'IDR'],
            'IE' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'IL' => ['name' => 'Israeli New Shekel', 'symbol' => '₪', 'code' => 'ILS'],
            'IM' => ['name' => 'British Pound Sterling', 'symbol' => '£', 'code' => 'GBP'],
            'IN' => ['name' => 'Indian Rupee', 'symbol' => '₹', 'code' => 'INR'],
            'IO' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'IQ' => ['name' => 'Iraqi Dinar', 'symbol' => 'ع.د', 'code' => 'IQD'],
            'IR' => ['name' => 'Iranian Rial', 'symbol' => '﷼', 'code' => 'IRR'],
            'IS' => ['name' => 'Icelandic Króna', 'symbol' => 'kr', 'code' => 'ISK'],
            'IT' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],

            // J
            'JE' => ['name' => 'British Pound Sterling', 'symbol' => '£', 'code' => 'GBP'],
            'JM' => ['name' => 'Jamaican Dollar', 'symbol' => 'J$', 'code' => 'JMD'],
            'JO' => ['name' => 'Jordanian Dinar', 'symbol' => 'د.ا', 'code' => 'JOD'],
            'JP' => ['name' => 'Japanese Yen', 'symbol' => '¥', 'code' => 'JPY'],

            // K
            'KE' => ['name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'code' => 'KES'],
            'KG' => ['name' => 'Kyrgyzstani Som', 'symbol' => 'с', 'code' => 'KGS'],
            'KH' => ['name' => 'Cambodian Riel', 'symbol' => '៛', 'code' => 'KHR'],
            'KI' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'code' => 'AUD'],
            'KM' => ['name' => 'Comorian Franc', 'symbol' => 'Fr', 'code' => 'KMF'],
            'KN' => ['name' => 'East Caribbean Dollar', 'symbol' => 'EC$', 'code' => 'XCD'],
            'KP' => ['name' => 'North Korean Won', 'symbol' => '₩', 'code' => 'KPW'],
            'KR' => ['name' => 'South Korean Won', 'symbol' => '₩', 'code' => 'KRW'],
            'KW' => ['name' => 'Kuwaiti Dinar', 'symbol' => 'د.ك', 'code' => 'KWD'],
            'KY' => ['name' => 'Cayman Islands Dollar', 'symbol' => 'CI$', 'code' => 'KYD'],
            'KZ' => ['name' => 'Kazakhstani Tenge', 'symbol' => '₸', 'code' => 'KZT'],

            // L
            'LA' => ['name' => 'Lao Kip', 'symbol' => '₭', 'code' => 'LAK'],
            'LB' => ['name' => 'Lebanese Pound', 'symbol' => 'ل.ل', 'code' => 'LBP'],
            'LC' => ['name' => 'East Caribbean Dollar', 'symbol' => 'EC$', 'code' => 'XCD'],
            'LI' => ['name' => 'Swiss Franc', 'symbol' => 'Fr', 'code' => 'CHF'],
            'LK' => ['name' => 'Sri Lankan Rupee', 'symbol' => 'Rs', 'code' => 'LKR'],
            'LR' => ['name' => 'Liberian Dollar', 'symbol' => 'L$', 'code' => 'LRD'],
            'LS' => ['name' => 'Lesotho Loti', 'symbol' => 'L', 'code' => 'LSL'],
            'LT' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'LU' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'LV' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'LY' => ['name' => 'Libyan Dinar', 'symbol' => 'ل.د', 'code' => 'LYD'],

            // M
            'MA' => ['name' => 'Moroccan Dirham', 'symbol' => 'د.م.', 'code' => 'MAD'],
            'MC' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'MD' => ['name' => 'Moldovan Leu', 'symbol' => 'L', 'code' => 'MDL'],
            'ME' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'MF' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'MG' => ['name' => 'Malagasy Ariary', 'symbol' => 'Ar', 'code' => 'MGA'],
            'MH' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'MK' => ['name' => 'Macedonian Denar', 'symbol' => 'ден', 'code' => 'MKD'],
            'ML' => ['name' => 'West African CFA Franc', 'symbol' => 'Fr', 'code' => 'XOF'],
            'MM' => ['name' => 'Myanmar Kyat', 'symbol' => 'K', 'code' => 'MMK'],
            'MN' => ['name' => 'Mongolian Tögrög', 'symbol' => '₮', 'code' => 'MNT'],
            'MO' => ['name' => 'Macanese Pataca', 'symbol' => 'P', 'code' => 'MOP'],
            'MP' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'MQ' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'MR' => ['name' => 'Mauritanian Ouguiya', 'symbol' => 'UM', 'code' => 'MRU'],
            'MS' => ['name' => 'East Caribbean Dollar', 'symbol' => 'EC$', 'code' => 'XCD'],
            'MT' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'MU' => ['name' => 'Mauritian Rupee', 'symbol' => '₨', 'code' => 'MUR'],
            'MV' => ['name' => 'Maldivian Rufiyaa', 'symbol' => 'Rf', 'code' => 'MVR'],
            'MW' => ['name' => 'Malawian Kwacha', 'symbol' => 'MK', 'code' => 'MWK'],
            'MX' => ['name' => 'Mexican Peso', 'symbol' => '$', 'code' => 'MXN'],
            'MY' => ['name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'code' => 'MYR'],
            'MZ' => ['name' => 'Mozambican Metical', 'symbol' => 'MT', 'code' => 'MZN'],

            // N
            'NA' => ['name' => 'Namibian Dollar', 'symbol' => 'N$', 'code' => 'NAD'],
            'NC' => ['name' => 'CFP Franc', 'symbol' => '₣', 'code' => 'XPF'],
            'NE' => ['name' => 'West African CFA Franc', 'symbol' => 'Fr', 'code' => 'XOF'],
            'NF' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'code' => 'AUD'],
            'NG' => ['name' => 'Nigerian Naira', 'symbol' => '₦', 'code' => 'NGN'],
            'NI' => ['name' => 'Nicaraguan Córdoba', 'symbol' => 'C$', 'code' => 'NIO'],
            'NL' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'NO' => ['name' => 'Norwegian Krone', 'symbol' => 'kr', 'code' => 'NOK'],
            'NP' => ['name' => 'Nepalese Rupee', 'symbol' => '₨', 'code' => 'NPR'],
            'NR' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'code' => 'AUD'],
            'NU' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'code' => 'NZD'],
            'NZ' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'code' => 'NZD'],

            // O
            'OM' => ['name' => 'Omani Rial', 'symbol' => 'ر.ع.', 'code' => 'OMR'],

            // P
            'PA' => ['name' => 'Panamanian Balboa', 'symbol' => 'B/.', 'code' => 'PAB'],
            'PE' => ['name' => 'Peruvian Sol', 'symbol' => 'S/', 'code' => 'PEN'],
            'PF' => ['name' => 'CFP Franc', 'symbol' => '₣', 'code' => 'XPF'],
            'PG' => ['name' => 'Papua New Guinean Kina', 'symbol' => 'K', 'code' => 'PGK'],
            'PH' => ['name' => 'Philippine Peso', 'symbol' => '₱', 'code' => 'PHP'],
            'PK' => ['name' => 'Pakistani Rupee', 'symbol' => '₨', 'code' => 'PKR'],
            'PL' => ['name' => 'Polish Złoty', 'symbol' => 'zł', 'code' => 'PLN'],
            'PM' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'PN' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'code' => 'NZD'],
            'PR' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'PS' => ['name' => 'Israeli New Shekel', 'symbol' => '₪', 'code' => 'ILS'],
            'PT' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'PW' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'PY' => ['name' => 'Paraguayan Guaraní', 'symbol' => '₲', 'code' => 'PYG'],

            // Q
            'QA' => ['name' => 'Qatari Riyal', 'symbol' => 'ر.ق', 'code' => 'QAR'],

            // R
            'RE' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'RO' => ['name' => 'Romanian Leu', 'symbol' => 'lei', 'code' => 'RON'],
            'RS' => ['name' => 'Serbian Dinar', 'symbol' => 'дин', 'code' => 'RSD'],
            'RU' => ['name' => 'Russian Ruble', 'symbol' => '₽', 'code' => 'RUB'],
            'RW' => ['name' => 'Rwandan Franc', 'symbol' => 'Fr', 'code' => 'RWF'],

            // S
            'SA' => ['name' => 'Saudi Riyal', 'symbol' => 'ر.س', 'code' => 'SAR'],
            'SB' => ['name' => 'Solomon Islands Dollar', 'symbol' => 'SI$', 'code' => 'SBD'],
            'SC' => ['name' => 'Seychellois Rupee', 'symbol' => '₨', 'code' => 'SCR'],
            'SD' => ['name' => 'Sudanese Pound', 'symbol' => 'ج.س.', 'code' => 'SDG'],
            'SE' => ['name' => 'Swedish Krona', 'symbol' => 'kr', 'code' => 'SEK'],
            'SG' => ['name' => 'Singapore Dollar', 'symbol' => 'S$', 'code' => 'SGD'],
            'SH' => ['name' => 'Saint Helena Pound', 'symbol' => '£', 'code' => 'SHP'],
            'SI' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'SJ' => ['name' => 'Norwegian Krone', 'symbol' => 'kr', 'code' => 'NOK'],
            'SK' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'SL' => ['name' => 'Sierra Leonean Leone', 'symbol' => 'Le', 'code' => 'SLL'],
            'SM' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'SN' => ['name' => 'West African CFA Franc', 'symbol' => 'Fr', 'code' => 'XOF'],
            'SO' => ['name' => 'Somali Shilling', 'symbol' => 'Sh', 'code' => 'SOS'],
            'SR' => ['name' => 'Surinamese Dollar', 'symbol' => '$', 'code' => 'SRD'],
            'SS' => ['name' => 'South Sudanese Pound', 'symbol' => '£', 'code' => 'SSP'],
            'ST' => ['name' => 'São Tomé and Príncipe Dobra', 'symbol' => 'Db', 'code' => 'STN'],
            'SV' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'SX' => ['name' => 'Netherlands Antillean Guilder', 'symbol' => 'ƒ', 'code' => 'ANG'],
            'SY' => ['name' => 'Syrian Pound', 'symbol' => '£S', 'code' => 'SYP'],
            'SZ' => ['name' => 'Swazi Lilangeni', 'symbol' => 'L', 'code' => 'SZL'],

            // T
            'TC' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'TD' => ['name' => 'Central African CFA Franc', 'symbol' => 'Fr', 'code' => 'XAF'],
            'TG' => ['name' => 'West African CFA Franc', 'symbol' => 'Fr', 'code' => 'XOF'],
            'TH' => ['name' => 'Thai Baht', 'symbol' => '฿', 'code' => 'THB'],
            'TJ' => ['name' => 'Tajikistani Somoni', 'symbol' => 'ЅМ', 'code' => 'TJS'],
            'TK' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'code' => 'NZD'],
            'TL' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'TM' => ['name' => 'Turkmenistan Manat', 'symbol' => 'm', 'code' => 'TMT'],
            'TN' => ['name' => 'Tunisian Dinar', 'symbol' => 'د.ت', 'code' => 'TND'],
            'TO' => ['name' => 'Tongan Paʻanga', 'symbol' => 'T$', 'code' => 'TOP'],
            'TR' => ['name' => 'Turkish Lira', 'symbol' => '₺', 'code' => 'TRY'],
            'TT' => ['name' => 'Trinidad and Tobago Dollar', 'symbol' => 'TT$', 'code' => 'TTD'],
            'TV' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'code' => 'AUD'],
            'TW' => ['name' => 'New Taiwan Dollar', 'symbol' => 'NT$', 'code' => 'TWD'],
            'TZ' => ['name' => 'Tanzanian Shilling', 'symbol' => 'TSh', 'code' => 'TZS'],

            // U
            'UA' => ['name' => 'Ukrainian Hryvnia', 'symbol' => '₴', 'code' => 'UAH'],
            'UG' => ['name' => 'Ugandan Shilling', 'symbol' => 'USh', 'code' => 'UGX'],
            'UM' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'US' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'UY' => ['name' => 'Uruguayan Peso', 'symbol' => '$U', 'code' => 'UYU'],
            'UZ' => ['name' => 'Uzbekistani Som', 'symbol' => "so'm", 'code' => 'UZS'],

            // V
            'VA' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
            'VC' => ['name' => 'East Caribbean Dollar', 'symbol' => 'EC$', 'code' => 'XCD'],
            'VE' => ['name' => 'Venezuelan Bolívar', 'symbol' => 'Bs.', 'code' => 'VES'],
            'VG' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'VI' => ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
            'VN' => ['name' => 'Vietnamese Đồng', 'symbol' => '₫', 'code' => 'VND'],
            'VU' => ['name' => 'Vanuatu Vatu', 'symbol' => 'Vt', 'code' => 'VUV'],

            // W
            'WF' => ['name' => 'CFP Franc', 'symbol' => '₣', 'code' => 'XPF'],
            'WS' => ['name' => 'Samoan Tālā', 'symbol' => 'T', 'code' => 'WST'],

            // X
            'XK' => ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],

            // Y
            'YE' => ['name' => 'Yemeni Rial', 'symbol' => '﷼', 'code' => 'YER'],

            // Z
            'ZA' => ['name' => 'South African Rand', 'symbol' => 'R', 'code' => 'ZAR'],
            'ZM' => ['name' => 'Zambian Kwacha', 'symbol' => 'ZK', 'code' => 'ZMW'],
            'ZW' => ['name' => 'Zimbabwean Dollar', 'symbol' => 'Z$', 'code' => 'ZWL'],
        ];

        return $data;
    }

}