<?php

declare(strict_types=1);

namespace Pdp;

interface IDNConversion
{
    /**
     * Gets conversion options for idn_to_ascii.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     */
    public function getAsciiIDNAOption(): int;

    /**
     * Gets conversion options for idn_to_utf8.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     */
    public function getUnicodeIDNAOption(): int;

    /**
     * Sets the host value with its IDNA options.
     *
     * combination of IDNA_* constants (except IDNA_ERROR_* constants).
     *
     * @see https://www.php.net/manual/en/intl.constants.php
     *
     * This method MUST retain the state of the current instance, and return
     * an instance with its content converted to its IDNA ASCII form
     *
     * @param  ?string           $value
     * @throws CannotProcessHost if the domain can not be converted to ASCII using IDN UTS46 algorithm
     * @return static
     */
    public function withValue(?string $value, int $asciiIDNAOption, int $unicodIDNAOption): self;
}
