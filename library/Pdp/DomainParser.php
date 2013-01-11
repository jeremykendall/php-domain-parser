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

    public function getDomainSuffixFromArray(array $domainParts, array $publicSuffixList)
    {
        $sub = array_pop($domainParts);

        $result = null;

        if (isset($publicSuffixList['!'])) {
            return '#';
        }

        if (is_array($publicSuffixList) && array_key_exists($sub, $publicSuffixList)) {
            $result = $this->getDomainSuffixFromArray($domainParts, $publicSuffixList[$sub]);
        } elseif (is_array($publicSuffixList) && array_key_exists('*', $publicSuffixList)) {
            $result = $this->getDomainSuffixFromArray($domainParts, $publicSuffixList['*']);
        } else {
            return $sub;
        }

        // this is a hack 'cause PHP interpretes '' as null
        if ($result == '#') {
            return $sub;
        } elseif (strlen($result) > 0) {
            return $result.'.'.$sub;
        }

        return null;
    }
}
