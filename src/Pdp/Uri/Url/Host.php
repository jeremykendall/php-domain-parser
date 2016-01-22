<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @link      http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2014 Jeremy Kendall (http://about.me/jeremykendall)
 * @license   http://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE MIT License
 */

namespace Pdp\Uri\Url;

/**
 * Represents the host portion of a Url.
 */
class Host
{
    /**
     * @var string Subdomain
     */
    protected $subdomain;

    /**
     * @var string Registrable domain
     */
    protected $registrableDomain;

    /**
     * @var string Public suffix
     */
    protected $publicSuffix;

    /**
     * @var string host Entire host part
     */
    protected $host;

    /**
     * Public constructor.
     *
     * @param string|null $subdomain Subdomain portion of host
     * @param string|null $registrableDomain Registrable domain portion of host
     * @param string|null $publicSuffix Public suffix portion of host
     * @param string $host OPTIONAL Entire host part
     */
    public function __construct( $subdomain, $registrableDomain, $publicSuffix, $host = null )
    {
        $this->subdomain          = $subdomain;
        $this->registrableDomain  = $registrableDomain;
        $this->publicSuffix       = $publicSuffix;
        $this->host               = $host;
    }


    /**
     * @return string
     */
    public function getSubdomain()
    {
        return $this->subdomain;
    }

    /**
     * @param string $subdomain
     */
    public function setSubdomain( $subdomain )
    {
        $this->subdomain = $subdomain;
    }

    /**
     * @return string
     */
    public function getRegistrableDomain()
    {
        return $this->registrableDomain;
    }

    /**
     * @param string $registrableDomain
     */
    public function setRegistrableDomain( $registrableDomain )
    {
        $this->registrableDomain = $registrableDomain;
    }

    /**
     * @return string
     */
    public function getPublicSuffix()
    {
        return $this->publicSuffix;
    }

    /**
     * @param string $publicSuffix
     */
    public function setPublicSuffix( $publicSuffix )
    {
        $this->publicSuffix = $publicSuffix;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param string $host
     */
    public function setHost( $host )
    {
        $this->host = $host;
    }


    /**
     * Get string representation of host.
     *
     * @return string String representation of host
     */
    public function __toString()
    {
        if( $this->host !== null )
        {
            return $this->host;
        }

        // retain only the elements that are not empty
        $str = array_filter(
            array( $this->subdomain, $this->registrableDomain ),
            'strlen'
        );

        return implode( '.', $str );
    }

    /**
     * Get array representation of host.
     *
     * @return array Array representation of host
     */
    public function toArray()
    {
        return array(
            'subdomain'          => $this->subdomain,
            'registrableDomain'  => $this->registrableDomain,
            'publicSuffix'       => $this->publicSuffix,
            'host'               => $this->host,
        );
    }
}
