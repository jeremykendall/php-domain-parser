PHP Domain Parser
=================

**PHP Domain Parser** is a [Public Suffix List](http://publicsuffix.org/) based 
domain parser implemented in PHP.

[![Build Status](https://travis-ci.org/jeremykendall/php-domain-parser.png?branch=master)](https://travis-ci.org/jeremykendall/php-domain-parser)

Motivation
----------

While there are plenty of excellent URL parsers and builders available, there
are very few projects that can accurately parse a domain into its component
subomain, registerable domain, and public suffix parts.

Consider the domain www.pref.okinawa.jp.  In this domain, the
_public suffix_ portion is *okinawa.jp*, the _registerable domain_ is
*pref.okinawa.jp*, and the _subdomain_ is *www*. You can't regex that.

With that in mind, this library is intended to parse domains into their
component parts and to do nothing more.  This library might then be used in
concert with other URL tools, and not be in direct competition with them.

Installation
------------

The only (currently) supported method of installation is via
[Composer](http://getcomposer.org).

Create a `composer.json` file in the root of your project:

``` json
{
    "require": {
        "jeremykendall/php-domain-parser": "0.0.*"
    }
}
```

And then run: `composer install`

Add the autoloader to your project:

``` php
<?php

require_once 'vendor/autoload.php'
```

You're now ready to begin using the PHP Domain Parser.

Usage
-----

### Obtain a local copy of the Public Suffix List ###

First, you need to get a copy of the Public Suffix List and cache it as an array. 
Use the provided command line command `pdp-psl` to do so.

``` bash
$ ./vendor/bin/pdp-psl <web-readable-directory>
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
  private 'subdomain' => null
  private 'registerableDomain' => string 'github.com' (length=10)
  private 'publicSuffix' => string 'com' (length=3)

object(Pdp\Domain)[4]
  private 'subdomain' => null
  private 'registerableDomain' => string 'waxaudio.com.au' (length=15)
  private 'publicSuffix' => string 'com.au' (length=6)

object(Pdp\Domain)[4]
  private 'subdomain' => string 'www' (length=3)
  private 'registerableDomain' => string 'scottwills.co.uk' (length=16)
  private 'publicSuffix' => string 'co.uk' (length=5)

object(Pdp\Domain)[4]
  private 'subdomain' => string 'www' (length=3)
  private 'registerableDomain' => string 'pref.okinawa.jp' (length=15)
  private 'publicSuffix' => string 'okinawa.jp' (length=10)
```

Getters are provided for the above private properties.  Obtaining the
public suffix for a parsed domain is as simple as:

``` php
<?php

$domain = $parser->parse('waxaudio.com.au');
$publicSuffix = $domain->getPublicSuffix();

// $publicSuffix = 'com.au'
```

Attribution
-----------

The HTTP adapter interface and the cURL HTTP adapter were inspired by (er,
lifted from) Will Durand's excellent
[Geocoder](https://github.com/willdurand/Geocoder) project.  His MIT license and
copyright notice are below.

```
Copyright (c) 2011-2013 William Durand <william.durand1@gmail.com>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is furnished
to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

Portions of the PublicSuffixListManager and the DomainParser are derivative
works of the PHP
[registered-domain-libs](https://github.com/usrflo/registered-domain-libs).
Those parts of this codebase are heavily commented, and I've included a copy of
the Apache Software Foundation License 2.0 in this project.
