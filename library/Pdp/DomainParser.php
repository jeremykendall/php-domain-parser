<?php

namespace Pdp;

class DomainParser
{
    public function parsePublicSuffixList()
    {
        $dir = __DIR__ . '/../../tests/_files';

        if (!file_exists($dir . '/public_suffix_list.php')) {
            $data = file(
                $dir . '/public_suffix_list.txt',
                FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
            );

            $publicSuffixList = array_filter($data, function($line) {
                return strstr($line, '//') === false;
            });

            $psl = '<?php' . PHP_EOL . 'return ' . var_export($publicSuffixList, true) . ';';

            file_put_contents($dir . '/public_suffix_list.php', $psl);
        }

        $psl = include $dir . '/public_suffix_list.php';
        return $psl;
    }

    public function getDomainSuffix($domain)
    {
        $publicSuffixList = $this->parsePublicSuffixList();
        $components = array_reverse(explode('.', $domain));

        $d = '';
        $E = '';

        foreach ($components as $c) {
            $a = "*.{$d}";
            $d = "{$c}.{$d}";
            $A = trim($a, '.');
            $D = trim($d, '.');

            /*
            d($domain);
            d($a);
            d($d);
            d($A);
            d($D);
            d('------------------------------');
             */

            if (in_array("!{$D}", $publicSuffixList)) {
                $E = $D;
                $E = trim($E, '.');
                break;
            } elseif (in_array($D, $publicSuffixList)) {
                $E = $D;
            } elseif (in_array($A, $publicSuffixList)) {
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
