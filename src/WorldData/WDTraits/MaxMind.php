<?php
namespace LCSNG\Tools\WorldData\WDTraits;

use GeoIp2\Database\Reader;

/**
 * Provides convenient access to MaxMind GeoIP databases.
 *
 * This trait provides factory methods for creating MaxMind database readers
 * for country, city, and Autonomous System Number (ASN) lookups.
 *
 * Supported databases:
 * - GeoLite2-Country.mmdb — Country-level IP geolocation.
 * - GeoLite2-City.mmdb    — Detailed geographic IP information.
 * - GeoLite2-ASN.mmdb     — ASN and network information.
 *
 * The returned Reader instances are provided by the geoip2/geoip2 Composer
 * package and can be used with compatible MaxMind GeoLite2 or GeoIP2 MMDB
 * databases.
 *
 * The MaxMind MMDB files are expected to be located in the
 * `MaxMindConfigs` directory relative to this trait.
 *
 * Example:
 * ```php
 * class WorldData
 * {
 *     use MaxMind;
 * }
 *
 * $reader = WorldData::maxMindCity();
 * $record = $reader->city('8.8.8.8');
 *
 * echo $record->country->name;
 * echo $record->city->name;
 * ```
 *
 * @see \GeoIp2\Database\Reader
 * @see https://www.maxmind.com/
 */
trait MaxMind
{
    /**
     * Create a MaxMind GeoLite2 Country database reader.
     *
     * The Country database provides country-level information for an IP
     * address, including country name and ISO country code.
     *
     * Example:
     * $reader = YourClass::maxMindCountry();
     * $record = $reader->country('8.8.8.8');
     * echo $record->country->name;
     *
     * @return Reader MaxMind database reader configured for Country lookups.
     */
    public static function maxMindCountry(): Reader
    {
        return new Reader(
            __DIR__ . '/../MaxMindConfigs/GeoLite2-Country.mmdb'
        );
    }

    /**
     * Create a MaxMind GeoLite2 City database reader.
     *
     * The City database provides geographic information such as country,
     * region, city, postal code, latitude, longitude, and timezone.
     *
     * Example:
     * $reader = YourClass::maxMindCity();
     * $record = $reader->city('8.8.8.8');
     * echo $record->city->name;
     *
     * @return Reader MaxMind database reader configured for City lookups.
     */
    public static function maxMindCity(): Reader
    {
        return new Reader(
            __DIR__ . '/../MaxMindConfigs/GeoLite2-City.mmdb'
        );
    }

    /**
     * Create a MaxMind GeoLite2 ASN database reader.
     *
     * The ASN database provides autonomous system and network information
     * associated with an IP address.
     *
     * Example:
     * $reader = YourClass::maxMindAsn();
     * $record = $reader->asn('8.8.8.8');
     * echo $record->autonomousSystemOrganization;
     *
     * @return Reader MaxMind database reader configured for ASN lookups.
     */
    public static function maxMindAsn(): Reader
    {
        return new Reader(
            __DIR__ . '/../MaxMindConfigs/GeoLite2-ASN.mmdb'
        );
    }
}