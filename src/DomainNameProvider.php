<?php

declare(strict_types=1);

namespace Pdp;

interface DomainNameProvider
{
    public function domain(): DomainName;
}
