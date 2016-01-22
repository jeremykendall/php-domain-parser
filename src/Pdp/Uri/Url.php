<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @link      http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2014 Jeremy Kendall (http://about.me/jeremykendall)
 * @license   http://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE MIT License
 */

namespace Pdp\Uri;

use Pdp\Parser;
use Pdp\Uri\Url\Host;

/**
 * An object representation of a Url.
 */
class Url
{
    /**
     * @var string scheme
     */
    protected $scheme;

    /**
     * @var Host Host object
     */
    protected $host;

    /**
     * @var int port
     */
    protected $port;

    /**
     * @var string user
     */
    protected $user;

    /**
     * @var string pass
     */
    protected $pass;

    /**
     * @var string path
     */
    protected $path;

    /**
     * @var string query
     */
    protected $query;

    /**
     * @var string fragment
     */
    protected $fragment;

    /**
     * @return string
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * @param string $scheme
     */
    public function setScheme( $scheme )
    {
        $this->scheme = $scheme;
    }

    /**
     * @return Host
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param Host $host
     */
    public function setHost( $host )
    {
        $this->host = $host;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param int $port
     */
    public function setPort( $port )
    {
        $this->port = $port;
    }

    /**
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param string $user
     */
    public function setUser( $user )
    {
        $this->user = $user;
    }

    /**
     * @return string
     */
    public function getPass()
    {
        return $this->pass;
    }

    /**
     * @param string $pass
     */
    public function setPass( $pass )
    {
        $this->pass = $pass;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath( $path )
    {
        $this->path = $path;
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param string $query
     */
    public function setQuery( $query )
    {
        $this->query = $query;
    }

    /**
     * @return string
     */
    public function getFragment()
    {
        return $this->fragment;
    }

    /**
     * @param string $fragment
     */
    public function setFragment( $fragment )
    {
        $this->fragment = $fragment;
    }


    /**
     * Public constructor.
     *
     * @param string $scheme   The URL scheme (e.g. `http`).
     * @param string $user     The username.
     * @param string $pass     The password.
     * @param Host   $host     The host elements.
     * @param int    $port     The port number.
     * @param string $path     The path elements, including format.
     * @param string $query    The query elements.
     * @param string $fragment The fragment.
     */
    public function __construct(
        $scheme,
        $user,
        $pass,
        Host $host,
        $port,
        $path,
        $query,
        $fragment
    ) {
        $this->scheme   = mb_strtolower($scheme, 'UTF-8');
        $this->user     = $user;
        $this->pass     = $pass;
        $this->host     = $host;
        $this->port     = $port;
        $this->path     = $path;
        $this->query    = $query;
        $this->fragment = $fragment;
    }


    /**
     * Gets schemeless url.
     *
     * @return string Url without scheme
     */
    public function getSchemeless()
    {
        return preg_replace(Parser::SCHEME_PATTERN, '//', $this->__toString(), 1);
    }

    /**
     * Converts the URI object to a string and returns it.
     *
     * @return string The full URI this object represents.
     */
    public function __toString()
    {
        $url = null;

        if ($this->scheme) {
            $url .= $this->scheme . '://';
        }

        if ($this->user) {
            $url .= urlencode($this->user);
            if ($this->pass) {
                $url .= ':' . urlencode($this->pass);
            }
            $url .= '@';
        }

        $host = $this->host->__toString();

        if ($host) {
            $url .= $host;
        }

        if ($this->port) {
            $url .= ':' . (int) $this->port;
        }

        if ($this->path) {
            $url .= $this->path;
        }

        if ($this->query) {
            $url .= '?' . $this->query;
        }

        if ($this->fragment) {
            $url .= '#' . urlencode($this->fragment);
        }

        return $url;
    }

    /**
     * Converts the URI object to an array and returns it.
     *
     * @return array Array of URI component parts
     */
    public function toArray()
    {
        return array(
            'scheme' => $this->scheme,
            'user' => $this->user,
            'pass' => $this->pass,
            'host' => $this->host->__toString(),
            'subdomain' => $this->host->getSubdomain(),
            'registrableDomain' => $this->host->getRegistrableDomain(),
            'publicSuffix' => $this->host->getPublicSuffix(),
            'port' => $this->port,
            'path' => $this->path,
            'query' => $this->query,
            'fragment' => $this->fragment,
        );
    }
}
