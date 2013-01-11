<?php

namespace Pdp;

class PublicSuffixListManager
{
    protected $publicSuffixListUrl = 'http://mxr.mozilla.org/mozilla-central/source/netwerk/dns/effective_tld_names.dat?raw=1';

    protected $cacheDir;

    protected $list;

    public function __construct($cacheDir)
    {
        $this->cacheDir = $cacheDir;
    }

    public function fetchListFromSource()
    {
        $fp = fopen($this->cacheDir . '/public_suffix_list.txt', 'w');

        $ch = curl_init($this->publicSuffixListUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        $data = curl_exec($ch);
        curl_close($ch);

        fclose($fp);

        return file_get_contents($this->cacheDir . '/public_suffix_list.txt');
    }

    public function parseListToArray($textFile)
    {
        $data = file(
            $textFile,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );

        return array_filter($data, function($line) {
            return strstr($line, '//') === false;
        });
    }

    public function writePhpCache($textFile)
    {
        $publicSuffixList = $this->parseListToArray($textFile);
        $contents = '<?php' . PHP_EOL . 'return ' . var_export($publicSuffixList, true) . ';';
        file_put_contents($this->cacheDir . '/public_suffix_list.php', $contents);

        if (file_exists($this->cacheDir . '/public_suffix_list.php')) {
            return true;
        }

        return false;
    }

    public function getList()
    {
        if ($this->list == null) {
            $this->list = include $this->cacheDir . '/public_suffix_list.php';
        }

        return $this->list;
    }

}
