<?php

declare(strict_types=1);

namespace Pdp;

use InvalidArgumentException;

class UnableToResolveDomain extends InvalidArgumentException implements CannotProcessHost
{
    private ?Host $domain = null;

    public static function dueToMissingPublicSuffix(Host $domain, string $type): self
    {
        $domainType = (EffectiveTLD::PRIVATE_DOMAINS === $type) ? 'private' : 'ICANN';

        $exception = new self('The domain "'.$domain->value().'" does not contain a "'.$domainType.'" TLD.');
        $exception->domain = $domain;

        return $exception;
    }

    public static function dueToUnresolvableDomain(?Host $domain): self
    {
        $content = $domain;
        if (null !== $content) {
            $content = $content->value();
        }

        $exception = new self('The domain "'.$content.'" can not contain a public suffix.');
        $exception->domain = $domain;

        return $exception;
    }

    public static function dueToMissingRegistrableDomain(Host $domain = null): self
    {
        $content = $domain;
        if (null !== $content) {
            $content = $content->value();
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

    public function getDomain(): ?Host
    {
        return $this->domain;
    }
}
