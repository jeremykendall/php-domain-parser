PHP Domain Parser
=================

**PHP Domain Parser** is a [Public Suffix List](http://publicsuffix.org/) based 
domain parser implemented in PHP.

[![Build Status](https://travis-ci.org/jeremykendall/php-domain-parser.png?branch=master)](https://travis-ci.org/jeremykendall/php-domain-parser)

Motivation
----------

While there are plenty of excellent URL parsers and builders available, there
are very few projects that can accurately parse a domain into its component
subdomain, registerable domain, and public suffix parts.

Consider the domain www.pref.okinawa.jp.  In this domain, the
**public suffix** portion is *okinawa.jp*, the **registerable domain** is
*pref.okinawa.jp*, and the **subdomain** is *www*. You can't regex that.

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

### Parsing ###

Parsing domains is as simple as the example you see below.

``` php
<?php

require_once '../vendor/autoload.php';

$listManager = new \Pdp\PublicSuffixListManager();
$parser = new \Pdp\DomainParser($listManager->getList());

var_dump($parser->parse('https://github.com/jeremykendall/php-domain-parser'));
var_dump($parser->parse('waxaudio.com.au'));
var_dump($parser->parse('http://www.scottwills.co.uk'));
var_dump($parser->parse('http://www.pref.okinawa.jp'));
```

The above will output:

```
object(Pdp\Domain)[5]
  private 'subdomain' => null
  private 'registerableDomain' => string 'github.com' (length=10)
  private 'publicSuffix' => string 'com' (length=3)
object(Pdp\Domain)[5]
  private 'subdomain' => null
  private 'registerableDomain' => string 'waxaudio.com.au' (length=15)
  private 'publicSuffix' => string 'com.au' (length=6)
object(Pdp\Domain)[5]
  private 'subdomain' => string 'www' (length=3)
  private 'registerableDomain' => string 'scottwills.co.uk' (length=16)
  private 'publicSuffix' => string 'co.uk' (length=5)
object(Pdp\Domain)[5]
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

### Refreshing the Public Suffix List ###

While a cached PHP copy of the Public Suffix List is provided for you in the
`psl` directory, that copy may or may not be up to date (Mozilla provides an
[Atom change
feed](http://hg.mozilla.org/mozilla-central/atom-log/default/netwerk/dns/effective_tld_names.dat) to keep
up with changes to the list). Please use the provided vendor binary to refresh
your cached copy of the Public Suffix List.

From the root of your project, simply call:

```
$ ./vendor/bin/pdp-psl
```

You may verify the update by checking the timestamp on the files located in the
`psl` directory.

**Important**: The vendor binary `pdp-psl` depends on an internet connection to
update the cached Public Suffix List.

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
