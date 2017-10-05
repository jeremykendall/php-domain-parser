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

use Pdp\Cache\FileCache;
use Pdp\Http\HttpAdapter;
use Psr\SimpleCache\CacheInterface;
use SplFileObject;
use SplTempFileObject;

/**
 * Public Suffix List Manager.
 *
 * This class obtains, writes, caches, and returns text and PHP representations
 * of the Public Suffix List
 */
class PublicSuffixListManager
{
    const PUBLIC_SUFFIX_LIST_URL = 'https://raw.githubusercontent.com/publicsuffix/list/master/public_suffix_list.dat';

    const PUBLIC_SUFFIX_LIST_DATA = 'RAW';
    const ALL_DOMAINS = 'ALL';
    const ICANN_DOMAINS = 'ICANN';
    const PRIVATE_DOMAINS = 'PRIVATE';

    /**
     * @var string Directory where text and php versions of list will be cached
     */
    private $cache;

    /**
     * @var HttpAdapter Http adapter
     */
    private $httpAdapter;

    /**
     * Public constructor.
     *
     * @param HttpAdapter         $httpAdapter
     * @param CacheInterface|null $cache
     */
    public function __construct(HttpAdapter $httpAdapter, CacheInterface $cache = null)
    {
        $this->httpAdapter = $httpAdapter;
        $this->cache = $cache ?? new FileCache(realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data'));
    }

    /**
     * Gets Public Suffix List.
     *
     * @param string $type the Public Suffix List type
     *
     * @return PublicSuffixList
     */
    public function getList($type = self::ALL_DOMAINS): PublicSuffixList
    {
        static $psl_type_lists = [self::ALL_DOMAINS => 1, self::ICANN_DOMAINS => 1, self::PRIVATE_DOMAINS => 1];

        if (!isset($psl_type_lists[$type])) {
            $type = self::ALL_DOMAINS;
        }

        if ($this->cache->has($type)) {
            return new PublicSuffixList($this->cache->get($type));
        }

        if ($this->cache->has(self::PUBLIC_SUFFIX_LIST_DATA)) {
            $this->cachePublicSuffixListTypes();
            return new PublicSuffixList($this->cache->get($type));
        }

        $this->refreshPublicSuffixList();
        return new PublicSuffixList($this->cache->get($type));
    }

    /**
     * Downloads Public Suffix List and writes text cache and PHP cache. If these files
     * already exist, they will be overwritten.
     */
    public function refreshPublicSuffixList()
    {
        $raw = $this->httpAdapter->getContent(self::PUBLIC_SUFFIX_LIST_URL);
        $this->cache->set(self::PUBLIC_SUFFIX_LIST_DATA, $raw);

        $this->cachePublicSuffixListTypes();
    }

    /**
     * Cache the different public suffix list subtypes
     */
    private function cachePublicSuffixListTypes()
    {
        $publicSuffixListArray = $this->convertListToArray();
        $this->cache->setMultiple($publicSuffixListArray);
    }

    /**
     * Parses text representation of list to associative, multidimensional array.
     *
     * @return array Associative, multidimensional array representation of the
     *               public suffx list
     */
    private function convertListToArray(): array
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

        $raw = $this->cache->get(self::PUBLIC_SUFFIX_LIST_DATA);
        $data = new SplTempFileObject();
        $data->fwrite($raw);
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
