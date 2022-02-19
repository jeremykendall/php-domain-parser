<?php

declare(strict_types=1);

namespace Pdp;

use function array_filter;
use const ARRAY_FILTER_USE_KEY;

/**
 * @internal
 * @see https://unicode-org.github.io/icu-docs/apidoc/released/icu4c/uidna_8h.html
 */
final class IdnaInfo
{
    private const ERRORS = [
        Idna::ERROR_EMPTY_LABEL => 'a non-final domain name label (or the whole domain name) is empty',
        Idna::ERROR_LABEL_TOO_LONG => 'a domain name label is longer than 63 bytes',
        Idna::ERROR_DOMAIN_NAME_TOO_LONG => 'a domain name is longer than 255 bytes in its storage form',
        Idna::ERROR_LEADING_HYPHEN => 'a label starts with a hyphen-minus ("-")',
        Idna::ERROR_TRAILING_HYPHEN => 'a label ends with a hyphen-minus ("-")',
        Idna::ERROR_HYPHEN_3_4 => 'a label contains hyphen-minus ("-") in the third and fourth positions',
        Idna::ERROR_LEADING_COMBINING_MARK => 'a label starts with a combining mark',
        Idna::ERROR_DISALLOWED => 'a label or domain name contains disallowed characters',
        Idna::ERROR_PUNYCODE => 'a label starts with "xn--" but does not contain valid Punycode',
        Idna::ERROR_LABEL_HAS_DOT => 'a label contains a dot=full stop',
        Idna::ERROR_INVALID_ACE_LABEL => 'An ACE label does not contain a valid label string',
        Idna::ERROR_BIDI => 'a label does not meet the IDNA BiDi requirements (for right-to-left characters)',
        Idna::ERROR_CONTEXTJ => 'a label does not meet the IDNA CONTEXTJ requirements',
        Idna::ERROR_CONTEXTO_DIGITS => 'a label does not meet the IDNA CONTEXTO requirements for digits',
        Idna::ERROR_CONTEXTO_PUNCTUATION => 'a label does not meet the IDNA CONTEXTO requirements for punctuation characters. Some punctuation characters "Would otherwise have been DISALLOWED" but are allowed in certain contexts',
    ];

    /** @var array<int, string> */
    private array $errorList;

    private function __construct(
        private string $result,
        private bool $isTransitionalDifferent,
        private int $errors
    ) {
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

    public function error(int $error): string|null
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
