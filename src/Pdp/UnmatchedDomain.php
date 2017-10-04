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

class UnmatchedDomain implements Domain
{
    use LabelsTrait;

    /**
     * @var string
     */
    private $domain;

    /**
     * @var string
     */
    private $publicSuffix;

    /**
     * @var bool
     */
    private $isValid;

    public function __construct(string $domain = null, string $publicSuffix = null, bool $isValid = false)
    {
        $this->domain = $domain;
        $this->publicSuffix = $publicSuffix;
        $this->isValid = $isValid;
    }

    public function getDomain()
    {
        return $this->domain;
    }

    public function getPublicSuffix()
    {
        return $this->publicSuffix;
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getRegistrableDomain()
    {
        if ($this->hasRegistrableDomain($this->publicSuffix) === false) {
            return null;
        }

        $publicSuffixLabels = $this->getLabels($this->publicSuffix);
        $domainLabels = $this->getLabels($this->domain);
        $additionalLabel = $this->getAdditionalLabel($domainLabels, $publicSuffixLabels);

        return implode('.', array_merge($additionalLabel, $publicSuffixLabels));
    }

    private function hasRegistrableDomain($publicSuffix): bool
    {
        return !($publicSuffix === null || $this->domain === $publicSuffix || !$this->hasLabels($this->domain));
    }

    private function getAdditionalLabel($domainLabels, $publicSuffixLabels): array
    {
        $additionalLabel = array_slice(
            $domainLabels,
            count($domainLabels) - count($publicSuffixLabels) - 1,
            1
        );

        return $additionalLabel;
    }
}