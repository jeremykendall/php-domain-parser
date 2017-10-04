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

trait LabelsTrait
{
    /**
     * Returns labels from input
     *
     * @param  string $input
     *
     * @return string[]
     */
    private function getLabels(string $input): array
    {
        return explode('.', $input);
    }

    /**
     * Returns labels from input in reverse
     *
     * @param  string $input
     *
     * @return string[]
     */
    private function getLabelsReverse(string $input): array
    {
        return array_reverse($this->getLabels($input));
    }

    /**
     * Tell whether the domain contains multiple labels
     *
     * @param string $domain
     *
     * @return bool
     */
    private function hasLabels(string $domain): bool
    {
        return strpos($domain, '.') !== false;
    }

    /**
     * Tell whether the domain contains one single label
     *
     * @param string $domain
     *
     * @return bool
     */
    private function isSingleLabelDomain(string $domain): bool
    {
        return !$this->hasLabels($domain);
    }
}
