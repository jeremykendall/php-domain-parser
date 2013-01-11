<?php

namespace Pdp;

class DomainParser
{

    protected $publicSuffixList;

    public function __construct(PublicSuffixList $publicSuffixList)
    {
        $this->publicSuffixList = $publicSuffixList;
    }

    public function getDomainSuffix($domain)
    {
        $publicSuffixList = $this->publicSuffixList;
        $components = array_reverse(explode('.', $domain));

        $d = '';
        $E = '';

        foreach ($components as $c) {
            $a = "*.{$d}";
            $d = "{$c}.{$d}";
            $A = trim($a, '.');
            $D = trim($d, '.');

            if ($this->publicSuffixList->search("!{$D}") !== false) {
                $E = $D;
                $E = trim($E, '.');
                break;
            } elseif ($this->publicSuffixList->search($D) !== false) {
                $E = $D;
            } elseif ($this->publicSuffixList->search($A) !== false) {
                $E = $D;
            }
        }
        $etld = "{$E}";

        return $etld;
    }

}
