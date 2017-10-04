<?php

declare(strict_types=1);

/**
 * Public Suffix List PHP: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/publicsuffixlist-php for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 * @license   http://github.com/jeremykendall/publicsuffixlist-php/blob/master/LICENSE MIT License
 */
namespace Pdp;

trait LabelsTrait
{
    private function getLabels(string $input): array
    {
        return explode('.', $input);
    }

    private function getLabelsReverse(string $input): array
    {
        return array_reverse($this->getLabels($input));
    }

    private function hasLabels(string $input): bool
    {
        return strpos($input, '.') !== false;
    }

    private function isSingleLabelDomain(string $domain): bool
    {
        return !$this->hasLabels($domain);
    }
}
