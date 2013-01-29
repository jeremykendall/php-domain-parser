<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing
 *
 * @link      http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 * @copyright Copyright (c) 2013 Jeremy Kendall (http://about.me/jeremykendall)
 * @license   http://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE MIT License
 */

namespace Pdp\Uri;

use Pdp\Uri\Url\Host;

/**
 * An object representation of a Url
 */
class Url
{

    /**
     * @var string scheme
     */
    private $scheme;

    /**
     * @var Host Host object
     */
    private $host;

    /**
     * @var int port
     */
    private $port;

    /**
     * @var string user
     */
    private $user;

    /**
     * @var string pass
     */
    private $pass;

    /**
     * @var string path
     */
    private $path;

    /**
     * @var string query
     */
    private $query;

    /**
     * @var string fragment
     */
    private $fragment;

    /**
     * Public constructor
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
        $this->scheme   = $scheme;
        $this->user     = $user;
        $this->pass     = $pass;
        $this->host     = $host;
        $this->port     = $port;
        $this->path     = $path;
        $this->query    = $query;
        $this->fragment = $fragment;
    }

    /**
     * Magic getter
     *
     * @param mixed $name Property name to get
     */
    public function __get($name)
    {
        return $this->$name;
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
            $url .= urlencode($this->scheme) . '://';
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
            $url .= urlencode($host);
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

}
