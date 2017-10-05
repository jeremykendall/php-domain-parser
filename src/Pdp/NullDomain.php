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

final class NullDomain implements Domain
{
    /**
     * @inheritdoc
     */
    public function getDomain()
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getPublicSuffix()
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getRegistrableDomain()
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function isValid(): bool
    {
        return false;
    }
}