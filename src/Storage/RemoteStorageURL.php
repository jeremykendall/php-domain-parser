<?php

declare(strict_types=1);

namespace Pdp\Storage;

interface RemoteStorageURL
{
    public const PUBLIC_SUFFIX_LIST_URI = 'https://publicsuffix.org/list/public_suffix_list.dat';

    public const ROOT_ZONE_DATABASE_URI = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';
}
