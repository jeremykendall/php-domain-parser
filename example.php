<?php

require_once __DIR__ . '/vendor/autoload.php';

use Pdp\PublicSuffixListManager;
use Pdp\Parser;

// Obtain an instance of the parser
$pslManager = new PublicSuffixListManager();
$parser = new Parser($pslManager->getList());

// Parse a URL
$url = $parser->parseUrl('http://user:pass@www.pref.okinawa.jp:8080/path/to/page.html?query=string#fragment');

// Accessing elements of the URL
var_dump($url);
var_dump($url->__toString());
var_dump($url->path);
var_dump($url->fragment);

// Getting the Host object from the URL
$host = $url->host;

// Accessing elements of the Host
var_dump($host);
var_dump($host->__toString());
var_dump($host->subdomain);
var_dump($host->registerableDomain);
var_dump($host->publicSuffix);

// It's possible to parse a host only, if you prefer
$host = $parser->parseHost('a.b.c.cy');

// Accessing elements of the Host
var_dump($host);
var_dump($host->__toString());
var_dump($host->subdomain);
var_dump($host->registerableDomain);
var_dump($host->publicSuffix);

// If you just need to know subdomain/registerable domain/public suffix info 
// about a host, there are public methods available for that in the Parser
var_dump($parser->getSubdomain('www.scottwills.co.uk'));
var_dump($parser->getRegisterableDomain('www.scottwills.co.uk'));
var_dump($parser->getPublicSuffix('www.scottwills.co.uk'));
