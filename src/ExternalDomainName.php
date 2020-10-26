<?php

declare(strict_types=1);

namespace Pdp;

interface ExternalDomainName
{
    public function getDomain(): DomainName;
}
