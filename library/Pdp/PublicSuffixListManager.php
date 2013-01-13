<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing
 *
 * @link      http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 * @copyright Copyright (c) 2013 Jeremy Kendall (http://about.me/jeremykendall)
 * @license   http://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE MIT License
 */

namespace Pdp;

use Pdp\HttpAdapter\HttpAdapterInterface;

/**
 * Public Suffix List Manager
 *
 * This class obtains, writes, caches, and returns text and PHP representations
 * of the public suffix list
 */
class PublicSuffixListManager
{
    /**
     * @var string Public Suffix List URL
     */
    protected $publicSuffixListUrl = 'http://mxr.mozilla.org/mozilla-central/source/netwerk/dns/effective_tld_names.dat?raw=1';

    /**
     * @var string Directory where text and php versions of list will be cached
     */
    protected $cacheDir;

    /**
     * @var string Public suffix list
     */
    protected $list;

    /**
     * @var Pdp\HttpAdapter\HttpAdapterInterface Http adaper interface
     */
    protected $httpAdapter;

    /**
     * Public constructor
     *
     * @param string               $cacheDir    Cache directory
     * @param HttpAdapterInterface $httpAdapter Http adapter interface
     */
    public function __construct($cacheDir, HttpAdapterInterface $httpAdapter)
    {
        $this->cacheDir = $cacheDir;
        $this->httpAdapter = $httpAdapter;
    }

    /**
     * Obtain public suffix list text from its online source
     *
     * @return string Public suffix list
     */
    public function fetchListFromSource()
    {
        $publicSuffixList = $this->httpAdapter->getContent($this->publicSuffixListUrl);

        return $publicSuffixList;
    }

    /**
     * Parses text representation of list to associative, multidimensional array
     *
     * This method is based heavily on the code found in generateEffectiveTLDs.php
     * @link https://github.com/usrflo/registered-domain-libs/blob/master/generateEffectiveTLDs.php
     * A copy of the Apache License, Version 2.0, is provided with this
     * distribution
     *
     * @param  string $textFile Public suffix list text filename
     * @return array  Associative, multidimensional array representation of the
     * public suffx list
     */
    public function parseListToArray($textFile)
    {
        $data = file(
            $textFile,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );

        $data = array_filter($data, function($line) {
            return strstr($line, '//') === false;
        });

        $publicSuffixListArray = array();

        foreach ($data as $line) {
            $parts = explode('.', $line);
            $this->buildArray($publicSuffixListArray, $parts);
        }

       return $publicSuffixListArray;
    }

    /**
     * Recursive method to build the multidimensional portions of the array
     * representation of the public suffix list
     *
     * This method is based heavily on the code found in generateEffectiveTLDs.php
     * @link https://github.com/usrflo/registered-domain-libs/blob/master/generateEffectiveTLDs.php
     * A copy of the Apache License, Version 2.0, is provided with this
     * distribution
     *
     * @param array $node Remaining portion of one dimensional public suffix
     * list array
     * @param array $parts Remaining portion of array of domain parts
     */
    public function buildArray(&$node, $parts)
    {
        $isDomain = true;

        $part = array_pop($parts);

        if (strpos($part, '!') === 0) {
            $part = substr($part, 1);
            $isDomain = false;
        }

        if (!array_key_exists($part, $node)) {
            if ($isDomain) {
                $node[$part] = array();
            } else {
                $node[$part] = array('!' => '');
            }
        }

        if ($isDomain && count($parts) > 0) {
            $this->buildArray($node[$part], $parts);
        }
    }

    /**
     * Writes php array representation of the Public Suffix List to disk
     *
     * @param array Array representation of the Public Suffix List
     * @return int|false Number of bytes that were written to the file, or
     * FALSE on failure
     */
    public function writePhpCache(array $publicSuffixList)
    {
        $data = '<?php' . PHP_EOL . 'return ' . var_export($publicSuffixList, true) . ';';

        return $this->write('public_suffix_list.php', $data);
    }

    /**
     * Gets array representation of the Public Suffix List
     *
     * @return array Array representation of the Public Suffix List
     */
    public function getList()
    {
        if ($this->list == null) {
            $this->list = include $this->cacheDir . '/public_suffix_list.php';
        }

        return $this->list;
    }

    /**
     * Writes to file
     *
     * @param  string    $filename Filename in cache dir where data will be written
     * @param  mixed     $data     Data to write
     * @return int|false Number of bytes that were written to the file, or
     * FALSE on failure
     */
    private function write($filename, $data)
    {
        return file_put_contents($this->cacheDir . '/' . $filename, $data);
    }
}
