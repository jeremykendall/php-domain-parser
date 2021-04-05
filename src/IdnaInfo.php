<?php

declare(strict_types=1);

namespace Pdp;

use function array_filter;
use const ARRAY_FILTER_USE_KEY;

/**
 * @see https://unicode-org.github.io/icu-docs/apidoc/released/icu4c/uidna_8h.html
 */
final class IdnaInfo
{
    /**
     * IDNA errors.
     */
    public const ERROR_EMPTY_LABEL            = 1;
    public const ERROR_LABEL_TOO_LONG         = 2;
    public const ERROR_DOMAIN_NAME_TOO_LONG   = 4;
    public const ERROR_LEADING_HYPHEN         = 8;
    public const ERROR_TRAILING_HYPHEN        = 0x10;
    public const ERROR_HYPHEN_3_4             = 0x20;
    public const ERROR_LEADING_COMBINING_MARK = 0x40;
    public const ERROR_DISALLOWED             = 0x80;
    public const ERROR_PUNYCODE               = 0x100;
    public const ERROR_LABEL_HAS_DOT          = 0x200;
    public const ERROR_INVALID_ACE_LABEL      = 0x400;
    public const ERROR_BIDI                   = 0x800;
    public const ERROR_CONTEXTJ               = 0x1000;
    public const ERROR_CONTEXTO_PUNCTUATION   = 0x2000;
    public const ERROR_CONTEXTO_DIGITS        = 0x4000;
    private const ERRORS = [
        self::ERROR_EMPTY_LABEL => 'a non-final domain name label (or the whole domain name) is empty',
        self::ERROR_LABEL_TOO_LONG => 'a domain name label is longer than 63 bytes',
        self::ERROR_DOMAIN_NAME_TOO_LONG => 'a domain name is longer than 255 bytes in its storage form',
        self::ERROR_LEADING_HYPHEN => 'a label starts with a hyphen-minus ("-")',
        self::ERROR_TRAILING_HYPHEN => 'a label ends with a hyphen-minus ("-")',
        self::ERROR_HYPHEN_3_4 => 'a label contains hyphen-minus ("-") in the third and fourth positions',
        self::ERROR_LEADING_COMBINING_MARK => 'a label starts with a combining mark',
        self::ERROR_DISALLOWED => 'a label or domain name contains disallowed characters',
        self::ERROR_PUNYCODE => 'a label starts with "xn--" but does not contain valid Punycode',
        self::ERROR_LABEL_HAS_DOT => 'a label contains a dot=full stop',
        self::ERROR_INVALID_ACE_LABEL => 'An ACE label does not contain a valid label string',
        self::ERROR_BIDI => 'a label does not meet the IDNA BiDi requirements (for right-to-left characters)',
        self::ERROR_CONTEXTJ => 'a label does not meet the IDNA CONTEXTJ requirements',
        self::ERROR_CONTEXTO_DIGITS => 'a label does not meet the IDNA CONTEXTO requirements for digits',
        self::ERROR_CONTEXTO_PUNCTUATION => 'a label does not meet the IDNA CONTEXTO requirements for punctuation characters. Some punctuation characters "Would otherwise have been DISALLOWED" but are allowed in certain contexts',
    ];

    private string $result;

    private bool $isTransitionalDifferent;

    private int $errors;

    /**
     * @var array<int, string>
     */
    private array $errorList;

    private function __construct(string $result, bool $isTransitionalDifferent, int $errors)
    {
        $this->result = $result;
        $this->errors = $errors;
        $this->isTransitionalDifferent = $isTransitionalDifferent;
        $this->errorList = array_filter(
            self::ERRORS,
            fn (int $error): bool => 0 !== ($error & $this->errors),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @param array{result:string, isTransitionalDifferent:bool, errors:int} $infos
     */
    public static function fromIntl(array $infos): self
    {
        return new self($infos['result'], $infos['isTransitionalDifferent'], $infos['errors']);
    }

    /**
     * @param array{result:string, isTransitionalDifferent:bool, errors:int} $properties
     */
    public static function __set_state(array $properties): self
    {
        return self::fromIntl($properties);
    }

    public function result(): string
    {
        return $this->result;
    }

    public function isTransitionalDifferent(): bool
    {
        return $this->isTransitionalDifferent;
    }

    public function errors(): int
    {
        return $this->errors;
    }

    public function error(int $error): ?string
    {
        return $this->errorList[$error] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function errorList(): array
    {
        return $this->errorList;
    }
}
