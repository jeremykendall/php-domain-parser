<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing
 *
 * @link      http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 * @copyright Copyright (c) 2013 Jeremy Kendall (http://about.me/jeremykendall)
 * @license   http://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE MIT License
 */

namespace Pdp;

/**
 * Represents a domain
 */
class Domain
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
     * @param array $parts Url parts
     */
    public function __construct(array $parts)
    {
        $this->subdomain = @$parts['subdomain'];
        $this->registerableDomain = @$parts['registerableDomain'];
        $this->publicSuffix = @$parts['publicSuffix'];
    }

    /**
     * Gets subdomain
     *
     * @return string Subdomain
     */
    public function getSubdomain()
    {
        return $this->subdomain;
    }

    /**
     * Gets registerable domain
     *
     * @return string Registerable domain
     */
    public function getRegisterableDomain()
    {
        return $this->registerableDomain;
    }

    /**
     * Gets public suffix
     *
     * @return string Public suffix
     */
    public function getPublicSuffix()
    {
        return $this->publicSuffix;
    }

}
