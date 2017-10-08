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

use League\Flysystem\FilesystemInterface;
use Pdp\Http\HttpAdapter;

/**
 * Public Suffix List Manager.
 *
 * This class obtains, writes, caches, and returns text and PHP representations
 * of the Public Suffix List
 */
class PublicSuffixListManager
{
    const PUBLIC_SUFFIX_LIST_URL = 'https://raw.githubusercontent.com/publicsuffix/list/master/public_suffix_list.dat';

    const ALL_DOMAINS = 'ALL';
    const PUBLIC_SUFFIX_LIST_RAW = 'public-suffix-list.txt';
    const PUBLIC_SUFFIX_LIST_JSON = 'public-suffix-list.json';

    const ICANN_DOMAINS = 'ICANN';
    const ICANN_DOMAINS_JSON = 'icann-domains.json';

    const PRIVATE_DOMAINS = 'PRIVATE';
    const PRIVATE_DOMAINS_JSON = 'private-domains.json';

    /**
     * @var array Public Suffix List Type
     */
    private static $domainList = [
        self::ALL_DOMAINS => self::PUBLIC_SUFFIX_LIST_JSON,
        self::ICANN_DOMAINS => self::ICANN_DOMAINS_JSON,
        self::PRIVATE_DOMAINS => self::PRIVATE_DOMAINS_JSON,
    ];

    /**
     * @var FilesystemInterface
     */
    private $flysystem;

    /**
     * @var HttpAdapter Http adapter
     */
    private $httpAdapter;

    /**
     * Public constructor.
     *
     * @param HttpAdapter         $httpAdapter
     * @param FilesystemInterface $flysystem
     */
    public function __construct(HttpAdapter $httpAdapter, FileSystemInterface $flysystem)
    {
        $this->flysystem = $flysystem;
        $this->httpAdapter = $httpAdapter;
    }

    /**
     * Gets Public Suffix List.
     *
     * @param string $list the Public Suffix List type
     *
     * @return PublicSuffixList
     */
    public function getList($list = self::ALL_DOMAINS): PublicSuffixList
    {
        $cacheBasename = isset(self::$domainList[$list]) ? self::$domainList[$list] : self::PUBLIC_SUFFIX_LIST_JSON;

        if (!$this->flysystem->has($cacheBasename)) {
            $this->refreshPublicSuffixList();
        }

        return new PublicSuffixList(json_decode($this->flysystem->read($cacheBasename), true));
    }

    /**
     * Downloads Public Suffix List and writes text cache and PHP cache. If these files
     * already exist, they will be overwritten.
     */
    public function refreshPublicSuffixList()
    {
        $publicSuffixList = $this->httpAdapter->getContent(self::PUBLIC_SUFFIX_LIST_URL);
        $this->write(self::PUBLIC_SUFFIX_LIST_RAW, $publicSuffixList);
        $parsed = $this->parseListToArray();

        foreach ($parsed as $type => $list) {
            $content = json_encode($list);
            $this->write(self::$domainList[$type], $content);
        }
    }

    /**
     * Write file to disk.
     *
     * @param string $path     Path to file
     * @param string $contents data to write
     *
     * @return bool True on success, false on failure
     */
    private function write(string $path, string $contents): bool
    {
        return $this->flysystem->put($path, $contents);
    }

    /**
     * Parses text representation of list to associative, multidimensional array.
     *
     * @return array Associative, multidimensional array representation of the
     *               public suffx list
     */
    private function parseListToArray(): array
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

        $data = $this->flysystem->read(self::PUBLIC_SUFFIX_LIST_RAW);
        $data = array_filter(explode("\n", $data), 'strlen');

        foreach ($data as $line) {
            $addDomain = $this->validateDomainAddition($line, $addDomain);
            if (false !== strpos($line, '//')) {
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
     *
     * @return array
     */
    private function validateDomainAddition(string $line, array $addDomain): array
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
    private function isValidSection(bool $previousStatus, string $line, string $section): bool
    {
        if (!$previousStatus && strpos($line, '// ===BEGIN ' . $section . ' DOMAINS===') === 0) {
            return true;
        }

        if ($previousStatus && strpos($line, '// ===END ' . $section . ' DOMAINS===') === 0) {
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
    private function convertLineToArray(string $textLine, array $publicSuffixListArray, array $addDomain): array
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
    private function buildArray(array &$publicSuffixList, array $ruleParts)
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
}
