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
     * @var string Url
     */
    private $url;

    /**
     * @var string Url scheme
     */
    private $scheme;

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
     * @var string Url path
     */
    private $path;

    /**
     * Public constructor
     *
     * @param array $parts Url parts
     */
    public function __construct(array $parts)
    {
        $this->url = @$parts['url'];
        $this->scheme = @$parts['scheme'];
        $this->subdomain = @$parts['subdomain'];
        $this->registerableDomain = @$parts['registerableDomain'];
        $this->publicSuffix = @$parts['publicSuffix'];
        $this->path = @$parts['path'];
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getScheme()
    {
        return $this->scheme;
    }

    public function getSubdomain()
    {
        return $this->subdomain;
    }

    public function getRegisterableDomain()
    {
        return $this->registerableDomain;
    }

    public function getPublicSuffix()
    {
        return $this->publicSuffix;
    }

    public function getPath()
    {
        return $this->path;
    }

}
