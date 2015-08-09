<?php
/**
 * Webiny Htpl (https://github.com/Webiny/VisitorAnalytics)
 *
 * @copyright Copyright Webiny LTD
 */
namespace Webiny\VisitorAnalytics\GeoIpProviders\TelizeApi;

use Webiny\VisitorAnalytics\GeoIpProviders\GeoIpProviderInterface;

/**
 * Class TelizeApi - provides geo data for the given IP address using Telize API (http://www.telize.com).
 *
 * @package Webiny\VisitorAnalytics\GeoIpProviders\TelizeApi
 */
class TelizeApi implements GeoIpProviderInterface
{

    /**
     * Returns an array containing [countryCode, countryName] or false if lookup failed.
     *
     * @param string $ip Ip address to do the lookup
     *
     * @return array|bool
     */
    public function getGeoIpData($ip)
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 3,
                // seconds
            ]
        ]);

        $geoData = @file_get_contents('http://www.telize.com/geoip/' . $ip, false, $ctx);

        if ($geoData == false) {
            return false;
        }

        $geoData = @json_decode($geoData, true);
        if ($geoData == false) {
            return false;
        }

        return [
            'countryCode' => $geoData['country_code'],
            'countryName' => $geoData['country']
        ];
    }
}