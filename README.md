PHP Domain Parser
=================

**PHP Domain Parser** is a [Public Suffix List](http://publicsuffix.org/) based 
domain parser implemented in PHP.

master: [![Build Status](https://travis-ci.org/jeremykendall/php-domain-parser.png?branch=master)](https://travis-ci.org/jeremykendall/php-domain-parser)
develop: [![Build
Status](https://travis-ci.org/jeremykendall/php-domain-parser.png?branch=development)](https://travis-ci.org/jeremykendall/php-domain-parser) 

Motivation
----------

While there are plenty of excellent URL parsers and builders available, there
are very few projects that can accurately parse a url into its component 
subdomain, registerable domain, and public suffix parts.

Consider the domain www.pref.okinawa.jp.  In this domain, the
**public suffix** portion is *okinawa.jp*, the **registerable domain** is
*pref.okinawa.jp*, and the **subdomain** is *www*. You can't regex that.

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

### Parsing URLs ###

Parsing URLs into their component parts is as simple as the example you see below.

``` php
<?php

require_once '../vendor/autoload.php';

$pslManager = new PublicSuffixListManager();
$parser = new Parser($pslManager->getList());
$url = $parser->parseUrl('http://user:pass@www.pref.okinawa.jp:8080/path/to/page.html?query=string#fragment');
var_dump($url);
```

The above will output:

```
class Pdp\Uri\Url#6 (8) {
    private $scheme =>
    string(4) "http"
    private $host =>
    class Pdp\Uri\Url\Host#5 (3) {
        private $subdomain =>
        string(3) "www"
        private $registerableDomain =>
        string(15) "pref.okinawa.jp"
        private $publicSuffix =>
        string(10) "okinawa.jp"
    }
    private $port =>
    int(8080)
    private $user =>
    string(4) "user"
    private $pass =>
    string(4) "pass"
    private $path =>
    string(18) "/path/to/page.html"
    private $query =>
    string(12) "query=string"
    private $fragment =>
    string(8) "fragment"
}
```

A magic __get() method is provided to access the above object properties.  Obtaining the
public suffix for a parsed domain is as simple as:

``` php
<?php

$url = $parser->parseUrl('waxaudio.com.au');
$publicSuffix = $domain->host->publicSuffix;

// $publicSuffix = 'com.au'
```

### Parsing Domains ###

If you'd like to parse the domain (or host) portion only, you can use 
`Parser::parseHost()`.

```php
<?php

$host = $parser->parseHost('a.b.c.cy');
var_dump($host);
```

The above will output:

```
class Pdp\Uri\Url\Host#7 (3) {
    private $subdomain =>
    string(1) "a"
    private $registerableDomain =>
    string(6) "b.c.cy"
    private $publicSuffix =>
    string(4) "c.cy"
}
```

### Retrieving Domain Components Only ###

If you're only interested in a domain component, you can use the parser to
retrieve only the component you're interested in

```php
<?php

var_dump($parser->getSubdomain('www.scottwills.co.uk'));
var_dump($parser->getRegisterableDomain('www.scottwills.co.uk'));
var_dump($parser->getPublicSuffix('www.scottwills.co.uk'));
```

The above will output:

```
string(3) "www"
string(16) "scottwills.co.uk"
string(5) "co.uk"
```

### Example Script ###

For more information on using the PHP Domain Parser, please see the provided
[example
script](https://github.com/jeremykendall/php-domain-parser/blob/master/example.php).

### Refreshing the Public Suffix List ###

While a cached PHP copy of the Public Suffix List is provided for you in the
`data` directory, that copy may or may not be up to date (Mozilla provides an
[Atom change
feed](http://hg.mozilla.org/mozilla-central/atom-log/default/netwerk/dns/effective_tld_names.dat) to keep
up with changes to the list). Please use the provided vendor binary to refresh
your cached copy of the Public Suffix List.

From the root of your project, simply call:

```
$ ./vendor/bin/pdp-psl
```

You may verify the update by checking the timestamp on the files located in the
`data` directory.

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
