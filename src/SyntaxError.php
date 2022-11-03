<?php

declare(strict_types=1);

namespace Pdp;

use InvalidArgumentException;

final class SyntaxError extends InvalidArgumentException implements CannotProcessHost
{
    private function __construct(string $message, private readonly ?IdnaInfo $idnaInfo = null)
    {
        parent::__construct($message);
    }

    public static function dueToInvalidCharacters(string $domain): self
    {
        return new self('The host `'.$domain.'` is invalid: it contains invalid characters.');
    }

    public static function dueToMalformedValue(string $domain): self
    {
        return new self('The host `'.$domain.'` is malformed; Verify its length and/or characters.');
    }

    public static function dueToIDNAError(string $domain, IdnaInfo $idnaInfo): self
    {
        return new self('The host `'.$domain.'` is invalid for IDN conversion.', $idnaInfo);
    }

    public static function dueToInvalidSuffix(Host $publicSuffix, string $type = ''): self
    {
        if ('' === $type) {
            return new self('The suffix `"'.($publicSuffix->value() ?? 'NULL').'"` is invalid.');
        }

        return new self('The suffix `"'.($publicSuffix->value() ?? 'NULL').'"` is an invalid `'.$type.'` suffix.');
    }

    public static function dueToUnsupportedType(string $domain): self
    {
        return new self('The domain `'.$domain.'` is invalid: this is an IPv4 host.');
    }

    public static function dueToInvalidLabelKey(Host $domain, int $key): self
    {
        return new self('the given key `'.$key.'` is invalid for the domain `'.($domain->value() ?? 'NULL').'`.');
    }

    public function idnaInfo(): ?IdnaInfo
    {
        return $this->idnaInfo;
    }
}
