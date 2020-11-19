<?php

declare(strict_types=1);

namespace Pdp;

interface IDNConversion
{
    /**
     * Tells whether IDNA Conversion is done using IDNA2008 algorithm.
     */
    public function isIDNA2008(): bool;

    /**
     * Tells whether the current domain is in its ascii form.
     */
    public function isAscii(): bool;
}
