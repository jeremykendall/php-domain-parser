PHP Domain Parser
=================

**PHP Domain Parser** is a [Public Suffix List](http://publicsuffix.org/) based 
domain parser implemented in PHP.

[![Build Status](https://travis-ci.org/jeremykendall/php-domain-parser.png?branch=master)](https://travis-ci.org/jeremykendall/php-domain-parser)

Installation
------------

The only (currently) supported method of installation is via
[Composer](http://getcomposer.org).

Create a `composer.json` file in the root of your project:

``` json
{
    "require": {
        "jeremykendall/php-domain-parser": "*"
    }
}
```

And then run: `composer install`

Add the autoloader to your project:

``` php
<?php

require_once 'vendor/autoload.php'
```

Usage
-----

### Obtain a local copy of the Public Suffix List ###

First, you need a copy of the Public Suffix List:

``` bash
$ vendor/bin/pdp-psl <web-readable-directory>
```

This will dowload a copy of the raw [Public Suffix
List](http://mxr.mozilla.org/mozilla-central/source/netwerk/dns/effective_tld_names.dat?raw=1) 
and save it to `<web-readable-directory>/public-suffix-list.txt`.

It will then parse `public-suffix-list.txt` into a PHP array and write that to
`<web-readable-directory>/public-suffix-list.php`.

### Parsing ###

Using the `public-suffix-list.php` you created in the previous step, create an
instance of `\Pdp\PublicSuffixList`, pass that to `\Pdp\DomainParser`, and parse
to your heart's content.

``` php
<?php

$list = new \Pdp\PublicSuffixList('/path/to/public-suffix-list.php');
$parser = new \Pdp\DomainParser($list);

var_dump($parser->parse('https://github.com/jeremykendall/php-domain-parser'));
var_dump($parser->parse('waxaudio.com.au'));
var_dump($parser->parse('http://www.scottwills.co.uk'));
var_dump($parser->parse('http://www.pref.okinawa.jp'));
```

The above will output:

```
object(Pdp\Domain)[4]
  private 'url' => string 'https://github.com/jeremykendall/php-domain-parser' (length=50)
  private 'scheme' => string 'https' (length=5)
  private 'subdomain' => null
  private 'registerableDomain' => string 'github.com' (length=10)
  private 'publicSuffix' => string 'com' (length=3)
  private 'path' => string '/jeremykendall/php-domain-parser' (length=32)

object(Pdp\Domain)[4]
  private 'url' => string 'waxaudio.com.au' (length=15)
  private 'scheme' => null
  private 'subdomain' => null
  private 'registerableDomain' => string 'waxaudio.com.au' (length=15)
  private 'publicSuffix' => string 'com.au' (length=6)
  private 'path' => null

object(Pdp\Domain)[4]
  private 'url' => string 'http://www.scottwills.co.uk' (length=27)
  private 'scheme' => string 'http' (length=4)
  private 'subdomain' => string 'www' (length=3)
  private 'registerableDomain' => string 'scottwills.co.uk' (length=16)
  private 'publicSuffix' => string 'co.uk' (length=5)
  private 'path' => null

object(Pdp\Domain)[4]
  private 'url' => string 'http://www.pref.okinawa.jp' (length=26)
  private 'scheme' => string 'http' (length=4)
  private 'subdomain' => string 'www' (length=3)
  private 'registerableDomain' => string 'pref.okinawa.jp' (length=15)
  private 'publicSuffix' => string 'okinawa.jp' (length=10)
  private 'path' => null
```

Getters are provided for the above private properties.  Obtaining the
public suffix for a parsed domain is as simple as:

``` php
<?php

$domain = $parser->parse('waxaudio.com.au');
$publicSuffix = $domain->getPublicSuffix();

// $publicSuffix = 'com.au'
```
