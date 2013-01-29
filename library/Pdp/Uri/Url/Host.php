<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing
 *
 * @link      http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 * @copyright Copyright (c) 2013 Jeremy Kendall (http://about.me/jeremykendall)
 * @license   http://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE MIT License
 */

namespace Pdp\Uri\Url;

/**
 * Represents the host portion of a Url
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
     * Public constructor
     *
     * @param string|null $subdomain          Subdomain portion of host
     * @param string      $registerableDomain Registerable domain portion of host
     * @param string      $publicSuffix       Public suffix portion of host
     */
    public function __construct($subdomain, $registerableDomain, $publicSuffix)
    {
        $this->subdomain = $subdomain;
        $this->registerableDomain = $registerableDomain;
        $this->publicSuffix = $publicSuffix;
    }

    /**
     * Get string representation of host
     *
     * @return string String representation of host
     */
    public function __toString()
    {
        $host = $this->registerableDomain;

        if ($this->subdomain) {
            $host = $this->subdomain . '.' . $host;
        }

        return $host;
    }

    /**
     * Get property
     *
     * @param  string      $name Property to get
     * @return string|null Property requested if exists and is set, null otherwise
     */
    public function __get($name)
    {
        return $this->$name;
    }

}
