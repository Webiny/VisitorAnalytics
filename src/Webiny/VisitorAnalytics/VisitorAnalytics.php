<?php
/**
 * Webiny Htpl (https://github.com/Webiny/VisitorAnalytics)
 *
 * @copyright Copyright Webiny LTD
 */
namespace Webiny\VisitorAnalytics;

use Webiny\VisitorAnalytics\GeoIpProviders\GeoIpProviderInterface;

class VisitorAnalytics
{
    const TOTAL_TIME_ON_SITE = 'totalTimeOnSite';
    const TIME_ON_PAGE = 'timeOnPage';
    const DEVICE_PROP = 'deviceProperties';
    const BROWSER_NAME = 'browserName';
    const BROWSER_MAJOR_VERSION = 'browserMajorVersion';
    const OS_NAME = 'os';
    const IS_EXIT = 'isExit';
    const COUNTRY_CODE = 'countryCode';
    const COUNTRY_NAME = 'countryName';
    const DEVICE_TYPE = 'deviceType';
    const PAGE_STATE = 'pageState'; // enter or exit

    private $geoIpProvider = 'Webiny\VisitorAnalytics\GeoIpProviders\TelizeApi\TelizeApi';
    private $geoIpLookupCache = [];

    private $beacon;
    private $vId;

    static $cookieName = '_wva'; // cookie name
    static $firstVisit;
    static $sessionDuration = 1800; // in seconds (30 min default)

    static function getInstance($beacon)
    {
        // get the current visitor cookie
        if (!isset($_COOKIE[self::$cookieName])) {
            $vId = uniqid(self::$cookieName . '.');
            self::$firstVisit = true;
        } else {
            $vId = $_COOKIE[self::$cookieName];
            self::$firstVisit = false;
        }

        $beacon = json_decode($beacon, true);

        $instance = new self($beacon, $vId);

        if (self::$firstVisit) {
            // save the user cookie id
            setcookie(self::$cookieName, $vId, time() + self::$sessionDuration, '/', $instance->getDomainName());
        }

        return $instance;
    }

    /**
     * Base constructor.
     *
     * @param array  $beacon Beacon data.
     * @param string $vId    Visitor id.
     *
     */
    private function __construct($beacon, $vId)
    {
        $this->beacon = $beacon;
        $this->vId = $vId;
    }

    public function getVisitorId()
    {
        return $this->vId;
    }

    /**
     * Sets a GeoIp provider.
     *
     * @param GeoIpProviderInterface $geoIpProvider GeoIp provider instance that will be used to do a geo ip lookup.
     */
    public function setGeoIpProvider(GeoIpProviderInterface $geoIpProvider)
    {
        $this->geoIpProvider = $geoIpProvider;
    }

    /**
     * Returns the browser name (e.g. Chrome, Safari), or false if beacon didn't provide the info.
     *
     * @return bool
     */
    public function getBrowserName()
    {
        if (isset($this->beacon[self::DEVICE_PROP][self::BROWSER_NAME])) {
            return $this->beacon[self::DEVICE_PROP][self::BROWSER_NAME];
        }

        return false;
    }

    /**
     * Returns the operating system name (e.g. iOS, Windows..), or false if beacon didn't provide the info.
     *
     * @return bool
     */
    public function getOsName()
    {
        if (isset($this->beacon[self::DEVICE_PROP][self::OS_NAME])) {
            return $this->beacon[self::DEVICE_PROP][self::OS_NAME];
        }

        return false;
    }

    /**
     * Returns the browser major version, or false if beacon didn't provide the info.
     *
     * @return bool
     */
    public function getBrowserMajorVersion()
    {
        if (isset($this->beacon[self::DEVICE_PROP][self::BROWSER_MAJOR_VERSION])) {
            return $this->beacon[self::DEVICE_PROP][self::BROWSER_MAJOR_VERSION];
        }

        return false;
    }

    /**
     * Returns the domain (host) name.
     *
     * @return string
     */
    public function getDomainName()
    {
        // get host name
        if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
            $elements = explode(',', $host);
            $host = trim(end($elements));
        } else if (isset($_SERVER['SERVER_NAME'])) {
            $host = $_SERVER['SERVER_NAME'];
        } else {
            $host = $_SERVER['HTTP_HOST'];
        }

        // Remove port number from host
        $host = preg_replace('/:\d+$/', '', $host);

        return trim($host);
    }

    /**
     * Returns users ip address.
     *
     * @return string
     */
    public function getVisitorIpAddress()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    /**
     * Returns the current URL path.
     *
     * @return string
     */
    public function getCurrentPath()
    {
        // sanitize the path and return it
        return trim(htmlentities(strip_tags(urldecode($_SERVER['REQUEST_URI'])), ENT_QUOTES, 'utf-8'));
    }

    /**
     * Returns the total time, in seconds, that the visitor spend on the current page.
     *
     * @return bool
     */
    public function getTimeOnPage()
    {
        if (isset($this->beacon[self::TIME_ON_PAGE])) {
            return $this->beacon[self::TIME_ON_PAGE];
        }

        return false;
    }

    /**
     * Returns true if this is users first visit, within the active session.
     *
     * @return bool
     */
    public function isFirstVisit()
    {
        return self::$firstVisit;
    }

    /**
     * Page enter is recorded in the moment when the user enters page 'onLoad'.
     *
     * @return bool
     */
    public function isPageEnter()
    {
        if (isset($this->beacon[self::PAGE_STATE]) && $this->beacon[self::PAGE_STATE] == 'enter') {
            return true;
        }

        return false;
    }

    /**
     * Page exist is recorded in the moment when user navigates away from the current page ('beforeUnload' event).
     * Note: Use this check to get the TimeOnPage.
     *
     * @return bool
     */
    public function isPageExit()
    {
        if (isset($this->beacon[self::PAGE_STATE]) && $this->beacon[self::PAGE_STATE] == 'exit') {
            return true;
        }

        return false;
    }

    /**
     * Returns 2 char country code id, or false if unable to get the geo ip data.
     *
     * @return bool|string
     *
     * @throws VisitorAnalyticsException
     */
    public function getCountryCode()
    {
        $geo = $this->getGeoIpInfo();

        if (!$geo) {
            return false;
        }

        return $geo['countryCode'];
    }

    /**
     * Returns country code name, or false if unable to get the geo ip data.
     *
     * @return bool|string
     *
     * @throws VisitorAnalyticsException
     */
    public function getCountryName()
    {
        $geo = $this->getGeoIpInfo();

        if (!$geo) {
            return false;
        }

        return $geo['countryName'];
    }

    /**
     * Returns the type of a device that the user is using: desktop, mobile or tablet.
     * False is returned if the data is not provided within the beacon.
     *
     * @return bool|string
     */
    public function getUserDeviceType()
    {
        if (isset($this->beacon[self::DEVICE_PROP][self::DEVICE_TYPE])) {
            return $this->beacon[self::DEVICE_PROP][self::DEVICE_TYPE];
        }

        return false;
    }

    /**
     * Returns the referrer domain, or false if data is not available.
     *
     * @return bool|string
     */
    public function getReferrerDomain()
    {
        if (!isset($_SERVER['HTTP_REFERER'])) {
            return false;
        }

        $url = parse_url($_SERVER['HTTP_REFERER']);
        if (!$url) {
            return false;
        }

        $host = isset($url['host']) ? $url['host'] : false;
        if (!$host) {
            return false;
        }

        if ($host == $this->getDomainName()) {
            return false;
        }

        return $host;
    }

    /**
     * Returns the referrer path, or false if data is not available.
     *
     * @return bool|string
     */
    public function getReferrerPath()
    {
        if (!isset($_SERVER['HTTP_REFERER'])) {
            return false;
        }

        $url = parse_url($_SERVER['HTTP_REFERER']);
        if (!$url) {
            return false;
        }

        $path = isset($url['path']) ? $url['path'] : false;
        if (!$path) {
            return false;
        }
        $query = isset($url['query']) ? $url['query'] : false;
        if ($query) {
            $path .= '?' . $query;
        }
        $fragment = isset($url['fragment']) ? $url['fragment'] : false;
        if ($fragment) {
            $path .= '#' . $fragment;
        }

        return $path;
    }

    /**
     * Performs the geo ip lookup.
     *
     * @return bool|array
     *
     * @throws VisitorAnalyticsException
     */
    private function getGeoIpInfo()
    {
        $ip = $this->getVisitorIpAddress();

        if (isset($this->geoIpLookupCache[$ip])) {
            return $this->geoIpLookupCache[$ip];
        }

        $geoProviderInstance = $this->geoIpProvider;
        if (!is_object($geoProviderInstance)) {
            $geoProviderInstance = new $geoProviderInstance;
        }

        try {
            $result = $geoProviderInstance->getGeoIpData($ip);
        } catch (\Exception $e) {
            return false;
        }

        if ($result === false) {
            return $result;
        }

        if (!is_array($result) || !isset($result['countryCode']) || !isset($result['countryName'])) {
            throw new VisitorAnalyticsException('Invalid return value on the geo IP provider.');
        }

        $this->geoIpLookupCache[$ip] = $result;

        return $result;
    }
}