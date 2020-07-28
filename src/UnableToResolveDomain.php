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

namespace Pdp;

use InvalidArgumentException;

class UnableToResolveDomain extends InvalidArgumentException implements ExceptionInterface
{
    private ?HostInterface $domain = null;

    public static function dueToUnresolvableDomain(?HostInterface $domain): self
    {
        $content = $domain;
        if (null !== $content) {
            $content = $content->getContent();
        }

        $exception = new self('The domain "'.$content.'" can not contain a public suffix.');
        $exception->domain = $domain;

        return $exception;
    }

    public static function dueToMissingRegistrableDomain(HostInterface $domain = null): self
    {
        $content = $domain;
        if (null !== $content) {
            $content = $content->getContent();
        }

        $exception = new self('A subdomain can not be added to a domain "'.$content.'" without a registrable domain part.');
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

    public function getDomain(): ?HostInterface
    {
        return $this->domain;
    }
}
