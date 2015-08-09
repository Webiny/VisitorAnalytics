<?php
/**
 * Webiny Htpl (https://github.com/Webiny/VisitorAnalytics)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Webiny\VisitorAnalytics\GeoIpProviders;

interface GeoIpProviderInterface
{
    /**
     * Returns an array containing [countryCode, countryName] or false if lookup failed.
     *
     * @param string $ip Ip address to do the lookup
     *
     * @return array|bool
     */
    public function getGeoIpData($ip);
}