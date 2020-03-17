<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Pdp\Exception;

use Pdp\Domain;
use Pdp\Exception as BaseException;
use function sprintf;

class CouldNotResolvePublicSuffix extends BaseException
{
    /**
     * @var Domain|null
     */
    private $domain;

    public static function dueToUnresolvableDomain(?Domain $domain): self
    {
        $content = $domain;
        if (null !== $domain) {
            $content = $domain->getContent();
        }

        $exception = new self(sprintf('The domain `%s` can not contain a public suffix.', $content));
        $exception->domain = $domain;

        return $exception;
    }

    public static function dueToUnSupportedSection(string $section): self
    {
        return new self('`'.$section.'` is an unknown Public Suffix List section.');
    }

    public function hasDomain(): bool
    {
        return null !== $this->domain;
    }

    public function getDomain(): ?Domain
    {
        return $this->domain;
    }
}
