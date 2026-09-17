<?php

namespace LCSNG\Tools\WorldData\WDTraits;

use GeoIp2\Database\Reader;

/**
 * Trait MaxMind
 *
 * This trait provides functionality for working with MaxMind databases.
 * The reader uses the GeoLite2-ASN MMDB or other MaxMind database to perform Autonomous System Number (ASN)
 * and network lookups for IP addresses.
 *
 * @package LCSNG\Tools\WorldData\WDTraits
 */
trait MaxMind
{
    /**
     * Create and return a MaxMind GeoIP2 database reader.
     *
     * The reader uses the GeoLite2-ASN MMDB database to perform
     * Autonomous System Number (ASN) and network lookups for IP addresses.
     *
     * Example:
     * ```php
     * $reader = YourClass::maxMindGeoIp2();
     *
     * $record = $reader->asn('8.8.8.8');
     *
     * echo $record->autonomousSystemNumber;
     * echo $record->autonomousSystemOrganization;
     * ```
     *
     * @return Reader MaxMind GeoIP2 database reader instance.
     */
    public static function maxMindGeoIp2(): Reader
    {
        return new Reader(
            __DIR__ . '/../MaxMindConfigs/GeoLite2-ASN.mmdb'
        );
    }
}