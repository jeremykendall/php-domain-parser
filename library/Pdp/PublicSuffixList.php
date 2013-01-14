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
class PublicSuffixList extends \ArrayObject
{
    /**
     * Public constructor
     *
     * @param mixed $list Array representing Public Suffix List or PHP Public
     * Suffix List file
     * @throws \InvalidArgumentException If $list is not array, file did not
     * contain an array, or $list is not an object
     */
    public function __construct($list)
    {
        if (!is_array($list)) {
            $list = include $list;
        }

        parent::__construct($list);
    }
}
