# PHP Domain Parser

**PHP Domain Parser** is a [Public Suffix List](http://publicsuffix.org/) based
domain parser implemented in PHP.

[![Build Status](https://img.shields.io/travis/jeremykendall/php-domain-parser/master.svg?style=flat-square)](https://travis-ci.org/jeremykendall/php-domain-parser)
[![Total Downloads](https://img.shields.io/packagist/dt/jeremykendall/php-domain-parser.svg?style=flat-square)](https://packagist.org/packages/jeremykendall/php-domain-parser)
[![Latest Stable Version](https://img.shields.io/github/release/jeremykendall/php-domain-parser.svg?style=flat-square)](https://github.com/jeremykendall/php-domain-parser/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](https://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE)


Motivation
-------

While there are plenty of excellent URL parsers and builders available, there
are very few projects that can accurately parse a url into its component
subdomain, registrable domain, and public suffix parts.

Consider the domain www.pref.okinawa.jp.  In this domain, the
*public suffix* portion is **okinawa.jp**, the *registrable domain* is
**pref.okinawa.jp**, and the *subdomain* is **www**. You can't regex that.

PHP Domain Parser is built around accurate Public Suffix List based parsing. For URL parsing, building or manipulation please refer to [libraries](https://packagist.org/packages/sabre/uri?q=uri%20rfc3986&p=0) focused on those area of development.

System Requirements
-------

You need:

- **PHP >= 7.0** but the latest stable version of PHP is recommended
- the `mbstring` extension
- the `intl` extension

Dependencies
-------

- [PSR-16](http://www.php-fig.org/psr/psr-16/)

Installation
--------

~~~
$ composer require jeremykendall/php-domain-parser
~~~

Documentation
--------

### Domain name resolution

The `Pdp\Rules` object is responsible for domain name resolution.

~~~php
<?php

namespace Pdp;

final class Rules
{
    const ALL_DOMAINS = 'ALL_DOMAINS';
    const ICANN_DOMAINS = 'ICANN_DOMAINS';
    const PRIVATE_DOMAINS = 'PRIVATE_DOMAINS';

    public static function createFromPath(string $path, $context = null): self
    public static function createFromString(string $content): self
    public function __construct(array $rules)
    public function supports(string $section): bool
    public function getPublicSuffix(string $domain = null, string $section = self::ALL_DOMAINS): PublicSuffix
    public function resolve(string $domain = null, string $section = self::ALL_DOMAINS): Domain
}
~~~

**NEW IN VERSION 5.2:**

- `Rules::supports` returns a boolean to tell whether the specific section is present in the `Rules` object;
- `Rules::getPublicSuffix` returns a `PublicSuffix` object determined from the `Rules` object;

**NEW IN VERSION 5.1:**

- `Rules::createFromString` expects a string content which follows [the PSL format](https://publicsuffix.org/list/#list-format);
- `Rules::createFromPath` expects a valid path to a readable PSL. You can optionnally submit a context resource as defined in PHP's `fopen` function;

Both named constructors:

- uses internally a `Pdp\Converter` object to convert the raw content into a suitable array to instantiate a valid `Pdp\Rules`;
- do not have any cache functionnality;

#### Usage

Domain name resolution is done using the `Pdp\Rules::resolve` method which expects at most two parameters:

- `$domain` a domain name as a string
- `$section` a string which specifies which section of the PSL you want to validate the given domain against. The possible values are:
    - `Rules::ALL_DOMAINS`, to validate against the full PSL.
    - `Rules::ICANN_DOMAINS`, to validate against the PSL ICANN DOMAINS section only.
    - `Rules::PRIVATE_DOMAINS`, to validate against the PSL PRIVATE DOMAINS section only.

By default, the `$section` argument is equal to `Rules::ALL_DOMAINS`. If an unsupported section is submitted a `Pdp\Exception` exception will be thrown.

The `Pdp\Rules::resolve` returns a `Pdp\Domain` object.

~~~php
<?php

final class Domain implements JsonSerializable
{
    public function getDomain(): ?string
    public function getPublicSuffix(): ?string
    public function getRegistrableDomain(): ?string
    public function getSubDomain(); ?string
    public function isKnown(): bool;
    public function isICANN(): bool;
    public function isPrivate(): bool;
    public function getSection(): string;
    public function toUnicode(): self;
    public function toAscii(): self;
}
~~~

**NEW IN VERSION 5.2:**

- `Domain::getSection` returns the section string name if presents else returns an empty string;
- `Domain::toUnicode` returns an instance with the domain converted to its unicode representation;
- `Domain::toAscii` returns an instance with the domain converted to its ascii representation;

**THIS EXAMPLE ILLUSTRATES HOW THE OBJECT WORK BUT SHOULD BE AVOIDED IN PRODUCTON**

~~~php
<?php

use Pdp\Rules;
use Pdp\Converter;

$pdp_url = 'https://raw.githubusercontent.com/publicsuffix/list/master/public_suffix_list.dat';
$rules = Rules::createFromPath($pdp_url);

$domain = $rules->resolve('www.ulb.ac.be'); //using Rules::ALL_DOMAINS
$domain->getDomain();            //returns 'www.ulb.ac.be'
$domain->getPublicSuffix();      //returns 'ac.be'
$domain->getRegistrableDomain(); //returns 'ulb.ac.be'
$domain->getSubDomain();         //returns 'www'
$domain->isKnown();              //returns true
$domain->isICANN();              //returns true
$domain->isPrivate();            //returns false
echo json_encode($domain, JSON_PRETTY_PRINT);
// returns
//  {
//      "domain": "www.ulb.ac.be",
//      "registrableDomain": "ulb.ac.be",
//      "subDomain": "www",
//      "publicSuffix": "ac.be",
//      "isKnown": true,
//      "isICANN": true,
//      "isPrivate": false
//  }

//The same domain will yield a different result using the PSL PRIVATE DOMAIN SECTION only

$domain = $rules->resolve('www.ulb.ac.be', Rules::PRIVATE_DOMAINS);
echo json_encode($domain, JSON_PRETTY_PRINT);
// returns
//  {
//      "domain": "www.ulb.ac.be",
//      "registrableDomain": "ac.be",
//      "subDomain": "www.ulb",
//      "publicSuffix": "be",
//      "isKnown": false,
//      "isICANN": false,
//      "isPrivate": false
//  }
~~~

The `Pdp\Domain` getter methods returns:

- the submitted domain name using `Pdp\Domain::getDomain`
- the public suffix part normalized according to the domain using `Pdp\Domain::getPublicSuffix`
- the registrable domain part using `Pdp\Domain::getRegistrableDomain`
- the subdomain part using `Pdp\Domain::getSubDomain`.

If the domain name or some of its part are seriously malformed or unrecognized, the getter methods will return `null`.

**The Domain name status depends on the PSL section used to resolve it:**

- `Pdp\Domain::isKnown` returns `true` if the public suffix is found in the selected PSL;
- `Pdp\Domain::isICANN` returns `true` if the public suffix is found using a PSL which includes the ICANN DOMAINS section;
- `Pdp\Domain::isPrivate` returns `true` if the public suffix is found using a PSL which includes the PRIVATE DOMAINS section;

The `Rules::getPublicSuffix` method expects the same arguments as `Rules::resolve` but returns a `Pdp\PublicSuffix` object instead.

~~~php
<?php

final class PublicSuffix implements Countable, JsonSerializable
{
    public function getContent(): ?string
    public function isKnown(): bool;
    public function isICANN(): bool;
    public function isPrivate(): bool;
    public function getSection(): string;
    public function toUnicode(): self;
    public function toAscii(): self;
}
~~~

While `Rules::resolve` will only throws an exception if the section value is invalid, the `Rules::getPublicSuffix` is more restrictive and will additionnally throw if:

- If the Domain is invalid or seriously malformed
- If the PublicSuffix can not be normalized and converted using the domain encoding.

**WARNING:**

**The `Pdp\Rules::resolve` does not validate the submitted host. You are require to use a host validator prior to using this library.**

**You should never use the library this way in production, without, at least, a caching mechanism to reduce PSL downloads.**

**Using the PSL to determine what is a valid domain name and what isn't is dangerous, particularly in these days where new gTLDs are arriving at a rapid pace. The DNS is the proper source for this information. If you must use this library for this purpose, please consider integrating a PSL update mechanism into your software.**

### Public Suffix List Maintenance

The library comes bundle with a service which enables resolving domain name without the constant network overhead of continously downloading the PSL. The `Pdp\Manager` class retrieves, converts and caches the PSL as well as creates the corresponding `Pdp\Rules` object on demand. It internally uses a `Pdp\Converter` object to convert the fetched PSL into its `array` representation when required.

~~~php
<?php

namespace Pdp;

use Psr\SimpleCache\CacheInterface;

final class Manager
{
    const PSL_URL = 'https://publicsuffix.org/list/public_suffix_list.dat';
    public function __construct(CacheInterface $cache, HttpClient $http)
    public function getRules(string $source_url = self::PSL_URL): Rules
    public function refreshRules(string $source_url = self::PSL_URL): bool
}
~~~

#### Instantiate `Pdp\Manager`

To work as intended, the `Pdp\Manager` constructor requires:

- a [PSR-16](http://www.php-fig.org/psr/psr-16/) Cache object to store the rules locally.

- a `Pdp\HttpClient` object to retrieve the PSL.  
the `Pdp\HttpClient` is a simple interface which exposes the `HttpClient::getContent` method. This method expects a string URL representation has its sole argument and returns the body from the given URL resource as a string.  
If an error occurs while retrieving such body a `HttpClientException` exception is thrown.

~~~php
<?php

namespace Pdp;

interface HttpClient
{
    /**
     * Returns the content fetched from a given URL.
     *
     * @param string $url
     *
     * @throws HttpClientException If an errors occurs while fetching the content from a given URL
     *
     * @return string Retrieved content
     */
    public function getContent(string $url): string;
}
~~~

The package comes bundle with:

- a file cache PSR-16 implementation based on the excellent [FileCache](https://github.com/kodus/file-cache) which **caches the local copy for a maximum of 7 days**.
- a HTTP client based on the cURL extension.

#### Refreshing the cached PSL

~~~php
<?php

public Manager::refreshRules(string $source_url = self::PSL_URL): bool
~~~

The `Pdp\Manager::refreshRules` method enables refreshing your local copy of the PSL stored with your [PSR-16](http://www.php-fig.org/psr/psr-16/) Cache and retrieved using the Http Client. By default the method will use the `Manager::PSL_URL` as the source URL but you are free to substitute this URL with your own.  
The method returns a boolean value which is `true` on success.

~~~php
<?php

use Pdp\Cache;
use Pdp\CurlHttpClient;
use Pdp\Manager;

$manager = new Manager(new Cache(), new CurlHttpClient());
$retval = $manager->refreshRules('https://publicsuffix.org/list/public_suffix_list.dat');
if ($retval) {
    //the local cache has been updated
} else {
    //the local cache has not been updated
}
~~~

#### Returning a `Pdp\Rules` object

~~~php
<?php

public Manager::getRules(string $source_url = self::PSL_URL): Rules
~~~

This method returns a `Rules` object which is instantiated with the PSL rules.

The method takes an optional `$source_url` argument which specifies the PSL source URL. If no local cache exists for the submitted source URL, the method will:

1. call `Manager::refreshRules` with the given URL to update its local cache
2. instantiate the `Rules` object with the newly cached data.

On error, the method throws an `Pdp\Exception`.

**THIS IS THE RECOMMENDED WAY OF USING THE LIBRARY**

~~~php
<?php

use Pdp\Cache;
use Pdp\CurlHttpClient;
use Pdp\Manager;

$manager = new Manager(new Cache(), new CurlHttpClient());
$rules = $manager->getRules('https://raw.githubusercontent.com/publicsuffix/list/master/public_suffix_list.dat');
$domain = $rules->resolve('www.ulb.ac.be');
echo json_encode($domain, JSON_PRETTY_PRINT);
// returns
//  {
//      "domain": "www.ulb.ac.be",
//      "registrableDomain": "ulb.ac.be",
//      "subDomain": "www",
//      "publicSuffix": "ac.be",
//      "isKnown": true,
//      "isICANN": true,
//      "isPrivate": false
//  }
~~~

### Automatic Updates

It is important to always have an up to date PSL. In order to do so the library comes bundle with an auto-update script located in the `bin` directory.

~~~bash
$ php ./bin/update-psl
~~~

This script requires:

- The PHP `curl` extension
- The `Pdp\Installer` class which organizes how to update the cache.
- The `Pdp\Cache` and `Pdp\CurlHttpClient` classes to retrieve and cache the PSL

You can also add a composer script in your `composer.json` file to update the PSL cache everytime after the `install` or the `update` command are executed.

~~~bash
{
    "scripts": {
        "post-install-cmd": "\\Pdp\\Installer::updateLocalCache",
        "post-update-cmd": "\\Pdp\\Installer::updateLocalCache"
    }
}
~~~


If you prefer using your own implementations you should:

1. Copy the `Pdp\Installer` class
2. Adapt its code to reflect your requirements.

In any case, your are required to update regularly your PSL information with your chosen script to keep your data up to date.

For example, below I'm using the `Manager` with

- the [Symfony Cache component](https://github.com/symfony/cache)
- the [Guzzle](https://github.com/guzzle/guzzle) client.

Of course you can add more setups depending on your usage.

<p class="message-notice">Be sure to adapt the following code to your own framework/situation. The following code is given as an example without warranty of it working out of the box.</p>

~~~php
<?php

use GuzzleHttp\Client as GuzzleClient;
use Pdp\HttpClient;
use Pdp\HttpClientException;
use Pdp\Manager;
use Symfony\Component\Cache\Simple\PDOCache;

final class GuzzleHttpClientAdapter implements HttpClient
{
    private $client;

    public function __construct(GuzzleClient $client)
    {
        $this->client = $client;
    }

    public function getContent(string $url): string
    {
        try {
            return $client->get($url)->getBody()->getContents();
        } catch (Throwable $e) {
            throw new HttpClientException($e->getMessage(), $e->getCode(), $e);
        }
    }
}

$dbh = new PDO('mysql:dbname=testdb;host=127.0.0.1', 'dbuser', 'dbpass');
$symfonyCache = new PDOCache($dbh, 'psl', 86400);
$guzzleAdapter = new GuzzleHttpClientAdapter(new GuzzleClient());
$manager = new Manager($symfonyCache, $guzzleAdapter);
$manager->refreshRules();
//the rules are saved to the database for 1 day
//the rules are fetched using GuzzlClient

$rules = $manager->getRules();
$domain = $rules->resolve('nl.shop.bébé.faketld');
$domain->getDomain();            //returns 'nl.shop.bébé.faketld'
$domain->getPublicSuffix();      //returns 'faketld'
$domain->getRegistrableDomain(); //returns 'bébé.faketld'
$domain->getSubDomain();         //returns 'nl.shop'
$domain->isKnown();              //returns false
~~~

Contributing
-------

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

Credits
-------

- [Jeremy Kendall](https://github.com/jeremykendall)
- [ignace nyamagana butera](https://github.com/nyamsprod)
- [All Contributors](https://github.com/jeremykendall/php-domain-parser/contributors)

License
-------

The MIT License (MIT). Please see [License File](LICENSE) for more information.


Attribution
-------

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

Portions of the `Pdp\Converter` and `Pdp\Rules` are derivative works of the PHP
[registered-domain-libs](https://github.com/usrflo/registered-domain-libs).
Those parts of this codebase are heavily commented, and I've included a copy of
the Apache Software Foundation License 2.0 in this project.
