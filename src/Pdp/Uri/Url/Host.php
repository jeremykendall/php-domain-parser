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
    private $subdomain;

    /**
     * @var string Registerable domain
     */
    private $registerableDomain;

    /**
     * @var string Public suffix
     */
    private $publicSuffix;

    /**
     * @var string host Entire host part
     */
    private $host;

    /**
     * Public constructor.
     *
     * @param string|null $subdomain          Subdomain portion of host
     * @param string|null $registerableDomain Registerable domain portion of host
     * @param string|null $publicSuffix       Public suffix portion of host
     * @param string      $host               OPTIONAL Entire host part
     */
    public function __construct($subdomain, $registerableDomain, $publicSuffix, $host = null)
    {
        $this->subdomain = $subdomain;
        $this->registerableDomain = $registerableDomain;
        $this->publicSuffix = $publicSuffix;
        $this->host = $host;
    }

    /**
     * Get string representation of host.
     *
     * @return string String representation of host
     */
    public function __toString()
    {
        if ($this->host !== null) {
            return $this->host;
        }

        // retain only the elements that are not empty
        $str = array_filter(
            array($this->subdomain, $this->registerableDomain),
            'strlen'
        );

        return implode('.', $str);
    }

    /**
     * Get array representation of host.
     *
     * @return array Array representation of host
     */
    public function toArray()
    {
        return array(
            'subdomain' => $this->subdomain,
            'registerableDomain' => $this->registerableDomain,
            'publicSuffix' => $this->publicSuffix,
            'host' => $this->host,
        );
    }

    /**
     * Get property.
     *
     * @param string $name Property to get
     *
     * @return string|null Property requested if exists and is set, null otherwise
     */
    public function __get($name)
    {
        return $this->$name;
    }
}
