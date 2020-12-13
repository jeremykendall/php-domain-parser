<?php

declare(strict_types=1);

namespace Pdp;

interface ResourceUri
{
    public const PUBLIC_SUFFIX_LIST_URI = 'https://publicsuffix.org/list/public_suffix_list.dat';

    public const TOP_LEVEL_DOMAIN_LIST_URI = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';
}
