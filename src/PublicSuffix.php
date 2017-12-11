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

use Countable;

/**
 * Public Suffix Value Object
 *
 * WARNING: "Some people use the PSL to determine what is a valid domain name
 * and what isn't. This is dangerous, particularly in these days where new
 * gTLDs are arriving at a rapid pace, if your software does not regularly
 * receive PSL updates, it will erroneously think new gTLDs are not
 * valid. The DNS is the proper source for this innormalizeion. If you must use
 * it for this purpose, please do not bake static copies of the PSL into your
 * software with no update mechanism."
 *
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * @internal
 */
final class PublicSuffix implements Countable
{
    /**
     * @var string|null
     */
    private $publicSuffix;

    /**
     * @var string
     */
    private $type;

    /**
     * New instance.
     *
     * @param string|null $publicSuffix
     * @param string      $type
     */
    public function __construct(string $publicSuffix = null, string $type = '')
    {
        $this->publicSuffix = $publicSuffix;
        $this->type = $type;
    }

    /**
     * Returns the public suffix content.
     *
     * @return string|null
     */
    public function getContent()
    {
        return $this->publicSuffix;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        if (null === $this->publicSuffix) {
            return 0;
        }

        return count(explode('.', $this->publicSuffix));
    }

    /**
     * Tells whether the public suffix has a matching rule in a Public Suffix List.
     *
     * @see https://publicsuffix.org/learn/
     *
     * @return bool
     */
    public function isKnown(): bool
    {
        return '' !== $this->type;
    }

    /**
     * Tells whether the public suffix has a matching rule in a Public Suffix List ICANN Section.
     *
     * @see https://publicsuffix.org/learn/
     *
     * @return bool
     */
    public function isICANN(): bool
    {
        return Rules::ICANN_DOMAINS === $this->type;
    }

    /**
     * Tells whether the public suffix has a matching rule in a Public Suffix List Private Section.
     *
     * @see https://publicsuffix.org/learn/
     *
     * @return bool
     */
    public function isPrivate(): bool
    {
        return Rules::PRIVATE_DOMAINS === $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function __debugInfo()
    {
        return [
            'publicSuffix' => $this->getContent(),
            'isKnown' => $this->isKnown(),
            'isICANN' => $this->isICANN(),
            'isPrivate' => $this->isPrivate(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function __set_state(array $properties)
    {
        return new self($properties['publicSuffix'], $properties['type']);
    }
}
