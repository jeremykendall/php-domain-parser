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
    const PDP_PSL_TEXT_FILE = 'public-suffix-list.txt';
    const PDP_PSL_PHP_FILE = 'public-suffix-list.php';
    /**
     * @var string Public Suffix List URL
     */
    protected $publicSuffixListUrl = 'http://mxr.mozilla.org/mozilla-central/source/netwerk/dns/effective_tld_names.dat?raw=1';

    /**
     * @var string Directory where text and php versions of list will be cached
     */
    protected $cacheDir;

    /**
     * @var PublicSuffixList Public suffix list
     */
    protected $list;

    /**
     * Public constructor
     *
     * @param string $cacheDir Optional cache dir. Will use default cache dir if
     * not provided
     */
    public function __construct($cacheDir = null)
    {
        if ($cacheDir === null) {
            $cacheDir = realpath(__DIR__ . '/../../data');
        }

        $this->cacheDir = $cacheDir;
    }

    /**
     * Downloads Public Suffix List and writes text cache and PHP cache. If these files
     * already exist, they will be overwritten
     *
     * @param HttpAdapterInterface $httpAdapter Http adapter
     */
    public function refreshPublicSuffixList(HttpAdapterInterface $httpAdapter)
    {
        $this->fetchListFromSource($httpAdapter);
        $publicSuffixListArray = $this->parseListToArray($this->cacheDir . '/' . self::PDP_PSL_TEXT_FILE);
        $this->writePhpCache($publicSuffixListArray);
    }

    /**
     * Obtain public suffix list from its online source and write to cache dir
     *
     * @param  HttpAdapterInterface $httpAdapter Http adapter
     * @return int                  Number of bytes that were written to the file
     */
    public function fetchListFromSource(HttpAdapterInterface $httpAdapter)
    {
        $publicSuffixList = $httpAdapter->getContent($this->publicSuffixListUrl);

        return $this->write(self::PDP_PSL_TEXT_FILE, $publicSuffixList);
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
     * @return int Number of bytes that were written to the file
     */
    public function writePhpCache(array $publicSuffixList)
    {
        $data = '<?php' . PHP_EOL . 'return ' . var_export($publicSuffixList, true) . ';';

        return $this->write(self::PDP_PSL_PHP_FILE, $data);
    }

    /**
     * Gets Public Suffix List
     *
     * @return PublicSuffixList Instance of Public Suffix List
     */
    public function getList()
    {
        if ($this->list == null) {
            $list = include $this->cacheDir . '/' . self::PDP_PSL_PHP_FILE;
            $this->list = new PublicSuffixList($list);
        }

        return $this->list;
    }

    /**
     * Writes to file
     *
     * @param  string     $filename Filename in cache dir where data will be written
     * @param  mixed      $data     Data to write
     * @return int        Number of bytes that were written to the file
     * @throws \Exception Throws \Exception if unable to write file
     */
    protected function write($filename, $data)
    {
        $result = @file_put_contents($this->cacheDir . '/' . $filename, $data);

        if ($result === false) {
            throw new \Exception("Cannot write '$filename'");
        }

        return $result;
    }

    /**
     * Returns cache directory
     *
     * @return string Cache directory
     */
    public function getCacheDir()
    {
        return $this->cacheDir;
    }
}
