<?php 
namespace LCSNG\Tools\WorldData;

use LCSNG\Tools\WorldData\WDTraits\Countries;
use LCSNG\Tools\WorldData\WDTraits\CallingCodes;
use LCSNG\Tools\WorldData\WDTraits\Currencies;

/*
 * Class LCS_WorldData
 *
 * A comprehensive class that provides access to various types of world data,
 * including country details, calling codes, and currency information. It
 * utilizes traits to organize related functionalities and offers a clean
 * interface for users to retrieve data based on ISO country codes.
 *
 * @package LCSNG\Tools\WorldData
 */
final class LCS_WorldData
{

    /*
     * Use traits to include methods for handling countries, calling codes, and currencies.
     */
    use Countries, CallingCodes, Currencies;
}