<?php

declare(strict_types=1);

namespace Pdp\Storage;

interface RemoteStorageURL
{
    public const URL_PSL = 'https://publicsuffix.org/list/public_suffix_list.dat';

    public const URL_RZD = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';
}
