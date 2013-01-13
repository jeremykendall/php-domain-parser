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
 * Public Suffix List
 */
class PublicSuffixList
{
    /**
     * @var array Public Suffix List
     */
    protected $list;

    /**
     * Public constructor
     *
     * @param array $list Public Suffix List
     */
    public function __construct(array $list)
    {
        $this->list = $list;
    }

    /**
     * Counts element in list
     *
     * @return int The number of elements in $list
     */
    public function count()
    {
        return count($this->list);
    }

    /**
     * Searches list for value
     *
     * @param mixed Value to search for
     * @return int|false Returns key for value if found, false otherwise
     */
    public function search($value)
    {
        return array_search($value, $this->list);
    }

    /**
     * Gets list
     *
     * @return array Public Suffix List
     */
    public function getList()
    {
        return $this->list;
    }
}
