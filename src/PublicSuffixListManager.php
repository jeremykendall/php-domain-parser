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
use SplFileObject;

/**
 * Public Suffix List Manager.
 *
 * This class obtains, writes, caches, and returns text and PHP representations
 * of the Public Suffix List
 */
class PublicSuffixListManager
{
    const ALL_DOMAINS = 'ALL';
    const PDP_PSL_TEXT_FILE = 'public-suffix-list.txt';
    const PDP_PSL_PHP_FILE = 'public-suffix-list.php';

    const ICANN_DOMAINS = 'ICANN';
    const ICANN_PSL_PHP_FILE = 'icann-public-suffix-list.php';

    const PRIVATE_DOMAINS = 'PRIVATE';
    const PRIVATE_PSL_PHP_FILE = 'private-public-suffix-list.php';

    /**
     * @var string Public Suffix List URL
     */
    protected $publicSuffixListUrl = 'https://publicsuffix.org/list/effective_tld_names.dat';

    /**
     * @var string Directory where text and php versions of list will be cached
     */
    protected $cacheDir;

    /**
     * @var PublicSuffixList Public Suffix List
     */
    protected static $domainList = [
        self::ALL_DOMAINS => self::PDP_PSL_PHP_FILE,
        self::ICANN_DOMAINS => self::ICANN_PSL_PHP_FILE,
        self::PRIVATE_DOMAINS => self::PRIVATE_PSL_PHP_FILE,
    ];

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
        $this->cacheDir = $cacheDir ?? realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data');
    }

    /**
     * Downloads Public Suffix List and writes text cache and PHP cache. If these files
     * already exist, they will be overwritten.
     */
    public function refreshPublicSuffixList()
    {
        $this->fetchListFromSource();
        $cacheFile = $this->cacheDir . '/' . self::PDP_PSL_TEXT_FILE;
        $publicSuffixListArray = $this->convertListToArray($cacheFile);
        foreach ($publicSuffixListArray as $domain => $data) {
            $this->varExportToFile(self::$domainList[$domain], $data);
        }
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
     * @param string $textFile Public Suffix List text filename
     *
     * @return array Associative, multidimensional array representation of the
     *               public suffx list
     */
    protected function convertListToArray($textFile)
    {
        $addDomain = [
            self::ICANN_DOMAINS => false,
            self::PRIVATE_DOMAINS => false,
        ];

        $publicSuffixListArray = [
            self::ALL_DOMAINS => [],
            self::ICANN_DOMAINS => [],
            self::PRIVATE_DOMAINS => [],
        ];

        $data = new SplFileObject($textFile);
        $data->setFlags(SplFileObject::DROP_NEW_LINE | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
        foreach ($data as $line) {
            $addDomain = $this->validateDomainAddition($line, $addDomain);
            if (strstr($line, '//') !== false) {
                continue;
            }
            $publicSuffixListArray = $this->convertLineToArray($line, $publicSuffixListArray, $addDomain);
        }

        return $publicSuffixListArray;
    }

    /**
     * Update the addition status for a given line against the domain list (ICANN and PRIVATE).
     *
     * @param string $line      the current file line
     * @param array  $addDomain the domain addition status
     */
    protected function validateDomainAddition($line, array $addDomain)
    {
        foreach ($addDomain as $section => $status) {
            $addDomain[$section] = $this->isValidSection($status, $line, $section);
        }

        return $addDomain;
    }

    /**
     * Tell whether the line can be converted for a given domain.
     *
     * @param bool   $previousStatus the previous status
     * @param string $line           the current file line
     * @param string $section        the section to be considered
     *
     * @return bool
     */
    protected function isValidSection($previousStatus, $line, $section)
    {
        if (!$previousStatus && 0 === strpos($line, '// ===BEGIN ' . $section . ' DOMAINS===')) {
            return true;
        }

        if ($previousStatus && 0 === strpos($line, '// ===END ' . $section . ' DOMAINS===')) {
            return false;
        }

        return $previousStatus;
    }

    /**
     * Convert a line from the Public Suffix list.
     *
     * @param string $textLine              Public Suffix List text line
     * @param array  $publicSuffixListArray Associative, multidimensional array representation of the
     *                                      public suffx list
     * @param array  $addDomain             Tell which section should be converted
     *
     * @return array Associative, multidimensional array representation of the
     *               public suffx list
     */
    protected function convertLineToArray($textLine, array $publicSuffixListArray, array $addDomain)
    {
        $ruleParts = explode('.', $textLine);
        $this->buildArray($publicSuffixListArray[self::ALL_DOMAINS], $ruleParts);
        $domainNames = array_keys(array_filter($addDomain));
        foreach ($domainNames as $domainName) {
            $this->buildArray($publicSuffixListArray[$domainName], $ruleParts);
        }

        return $publicSuffixListArray;
    }

    /**
     * Parses text representation of list to associative, multidimensional array.
     *
     * This method is based heavily on the code found in generateEffectiveTLDs.php
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 3.1.0
     * @see https://github.com/usrflo/registered-domain-libs/blob/master/generateEffectiveTLDs.php
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
        $filter = function ($line) {
            return strstr($line, '//') === false;
        };

        $publicSuffixListArray = [];
        foreach (array_filter($data, $filter) as $line) {
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
     * @see https://github.com/usrflo/registered-domain-libs/blob/master/generateEffectiveTLDs.php
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
        return $this->varExportToFile(self::PDP_PSL_PHP_FILE, $publicSuffixList);
    }

    /**
     * Writes php array representation to disk.
     *
     * @param string $basename file path
     * @param array  $input    input data
     *
     * @return int Number of bytes that were written to the file
     */
    protected function varExportToFile($basename, array $input)
    {
        $data = '<?php' . PHP_EOL . 'return ' . var_export($input, true) . ';';

        return $this->write($basename, $data);
    }

    /**
     * Gets Public Suffix List.
     *
     * @param string $list the Public Suffix List type
     *
     * @return PublicSuffixList Instance of Public Suffix List
     */
    public function getList($list = self::ALL_DOMAINS)
    {
        $cacheBasename = isset(self::$domainList[$list]) ? self::$domainList[$list] : self::PDP_PSL_PHP_FILE;
        $cacheFile = $this->cacheDir . '/' . $cacheBasename;
        if (!file_exists($cacheFile)) {
            $this->refreshPublicSuffixList();
        }

        return new PublicSuffixList($cacheFile);
    }

    /**
     * Writes to file.
     *
     * @param string $filename Filename in cache dir where data will be written
     * @param mixed  $data     Data to write
     *
     * @throws Exception if unable to write file
     *
     * @return int Number of bytes that were written to the file
     */
    protected function write($filename, $data): int
    {
        $path = $this->cacheDir . '/' . $filename;
        $result = @file_put_contents($path, $data);
        if ($result !== false) {
            return $result;
        }

        throw new \Exception(sprintf("Cannot write '%s'", $path));
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
