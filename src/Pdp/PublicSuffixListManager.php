<?php
/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 * @license   http://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE MIT License
 */
declare(strict_types=1);

namespace Pdp;

use Exception;
use Pdp\HttpAdapter\CurlHttpAdapter;
use Pdp\HttpAdapter\HttpAdapterInterface;

class PublicSuffixListManager
{
    const PDP_PSL_TEXT_FILE = 'public-suffix-list.txt';
    const PDP_PSL_PHP_FILE = 'public-suffix-list.php';
    /**
     * @var string Public Suffix List URL
     */
    protected $publicSuffixListUrl = 'https://publicsuffix.org/list/effective_tld_names.dat';

    /**
     * @var string Directory where text and php versions of list will be cached
     */
    protected $cacheDir;

    /**
     * @var HttpAdapterInterface Http adapter
     */
    protected $httpAdapter;

    /**
     * Public constructor.
     *
     * @param string $cacheDir Optional cache directory
     */
    public function __construct(string $cacheDir = null)
    {
         $this->cacheDir = $cacheDir ?? realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data');
    }

    /**
     * Downloads Public Suffix List and writes text cache and PHP cache. If these files
     * already exist, they will be overwritten.
     */
    public function refreshPublicSuffixList()
    {
        $this->fetchListFromSource();
        $publicSuffixListArray = $this->parseListToArray($this->cacheDir . '/' . self::PDP_PSL_TEXT_FILE);
        $this->writePhpCache($publicSuffixListArray);
    }

    /**
     * Obtain Public Suffix List from its online source and write to cache dir.
     *
     * @return int Number of bytes that were written to the file
     */
    public function fetchListFromSource(): int
    {
        $publicSuffixList = $this->getHttpAdapter()->getContent($this->publicSuffixListUrl);

        return $this->write(self::PDP_PSL_TEXT_FILE, $publicSuffixList);
    }

    /**
     * Parses text representation of list to associative, multidimensional array.
     *
     * This method is based heavily on the code found in generateEffectiveTLDs.php
     *
     * @link https://github.com/usrflo/registered-domain-libs/blob/master/generateEffectiveTLDs.php
     * A copy of the Apache License, Version 2.0, is provided with this
     * distribution
     *
     * @param string $textFile Public Suffix List text filename
     *
     * @return array Associative, multidimensional array representation of the
     *               public suffx list
     */
    public function parseListToArray(string $textFile): array
    {
        $data = file($textFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $data = array_filter($data, function ($line) {
            return strstr($line, '//') === false;
        });

        $list = [];

        foreach ($data as $line) {
            $ruleParts = explode('.', $line);
            $this->buildArray($list, $ruleParts);
        }

        return $list;
    }

    /**
     * Recursive method to build the array representation of the Public Suffix List.
     *
     * This method is based heavily on the code found in generateEffectiveTLDs.php
     *
     * @link https://github.com/usrflo/registered-domain-libs/blob/master/generateEffectiveTLDs.php
     * A copy of the Apache License, Version 2.0, is provided with this
     * distribution
     *
     * @param array $publicSuffixList Initially an empty array, this eventually
     *                                becomes the array representation of the Public Suffix List
     * @param array $ruleParts        One line (rule) from the Public Suffix List
     *                                exploded on '.', or the remaining portion of that array during recursion
     */
    public function buildArray(array &$publicSuffixList, array $ruleParts)
    {
        $isDomain = true;

        $part = array_pop($ruleParts);

        // Adheres to canonicalization rule from the "Formal Algorithm" section
        // of https://publicsuffix.org/list/
        // "The domain and all rules must be canonicalized in the normal way
        // for hostnames - lower-case, Punycode (RFC 3492)."

        $part = idn_to_ascii($part, 0, INTL_IDNA_VARIANT_UTS46);
        if (strpos($part, '!') === 0) {
            $part = substr($part, 1);
            $isDomain = false;
        }

        if (!array_key_exists($part, $publicSuffixList)) {
            $publicSuffixList[$part] = $isDomain ? [] : ['!' => ''];
        }

        if ($isDomain && !empty($ruleParts)) {
            $this->buildArray($publicSuffixList[$part], $ruleParts);
        }
    }

    /**
     * Writes php array representation of the Public Suffix List to disk.
     *
     * @param array $publicSuffixList Array representation of the Public Suffix List
     *
     * @return int Number of bytes that were written to the file
     */
    public function writePhpCache(array $publicSuffixList): int
    {
        $data = '<?php' . PHP_EOL . 'return ' . var_export($publicSuffixList, true) . ';';

        return $this->write(self::PDP_PSL_PHP_FILE, $data);
    }

    /**
     * Gets Public Suffix List.
     *
     * @return PublicSuffixList Instance of Public Suffix List
     */
    public function getList(): PublicSuffixList
    {
        $cachePath = $this->cacheDir . '/' . self::PDP_PSL_PHP_FILE;
        if (!file_exists($cachePath)) {
            $this->refreshPublicSuffixList();
        }

        return new PublicSuffixList(include $cachePath);
    }

    /**
     * Writes to file.
     *
     * @param string $filename Filename in cache dir where data will be written
     * @param mixed  $data     Data to write
     *
     * @return int Number of bytes that were written to the file
     *
     * @throws Exception if unable to write file
     */
    protected function write($filename, $data): int
    {
        $path = $this->cacheDir . '/' . $filename;
        if (($result = @file_put_contents($path, $data)) === false) {
            throw new Exception(sprintf("Cannot write '%s'", $path));
        }

        return $result;
    }

    /**
     * Returns http adapter. Returns default http adapter if one is not set.
     *
     * @return HttpAdapterInterface
     */
    public function getHttpAdapter(): HttpAdapterInterface
    {
        $this->httpAdapter = $this->httpAdapter ?? new CurlHttpAdapter();

        return $this->httpAdapter;
    }

    /**
     * Sets http adapter.
     *
     * @param HttpAdapterInterface $httpAdapter
     */
    public function setHttpAdapter(HttpAdapterInterface $httpAdapter)
    {
        $this->httpAdapter = $httpAdapter;
    }
}
