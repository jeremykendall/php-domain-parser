# PHP Domain Parser

**PHP Domain Parser** is a [Public Suffix List](http://publicsuffix.org/) based
domain parser implemented in PHP.

[![Build Status](https://travis-ci.org/jeremykendall/php-domain-parser.png?branch=master)](https://travis-ci.org/jeremykendall/php-domain-parser)
[![Total Downloads](https://poser.pugx.org/jeremykendall/php-domain-parser/downloads.png)](https://packagist.org/packages/jeremykendall/php-domain-parser)
[![Latest Stable Version](https://poser.pugx.org/jeremykendall/php-domain-parser/v/stable.png)](https://packagist.org/packages/jeremykendall/php-domain-parser)

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

### Public Suffix Manager

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

This class obtains, parses, caches, and returns a PHP representation of the PSL rules.

#### Creating a new manager

To work as intended, the `Manager` constructor requires:

- a [PSR-16](http://www.php-fig.org/psr/psr-16/) Cache object to store the retrieved rules using a basic HTTP client.

- a `HttpClient` interface which exposes the `HttpClient::getContent` method which expects a string URL representation has its sole argument and returns the body from the given URL resource as a string.  
If an error occurs while retrieving such body a `HttpClientException` is thrown.

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

For advance usages you are free to use your own cache and/or http implementation.

By default and out of the box, the package uses:

- a file cache PSR-16 implementation based on the excellent [FileCache](https://github.com/kodus/file-cache) which **caches the local copy for a maximum of 7 days**.
- a HTTP client based on the cURL extension.

#### Accessing the Public Suffix rules

~~~php
<?php

public function getRules(string $source_url = self::PSL_URL): Rules
~~~

This method returns a `Rules` object which is instantiated with the PSL rules.

The method takes an optional `$source_url` argument which specifies the PSL source URL. If no local cache exists for the submitted source URL, the method will:

1. call `Manager::refreshRules` with the given URL to update its local cache
2. instantiate the `Rules` object with the newly cached data.

On error, the method throws an `Pdp\Exception`.

~~~php
<?php

use Pdp\Cache;
use Pdp\CurlHttpClient;
use Pdp\Manager;

$manager = new Manager(new Cache(), new CurlHttpClient());
$rules = $manager->getRules('https://publicsuffix.org/list/public_suffix_list.dat');
$rules->resolve('www.bébé.be');
~~~

#### Refreshing the cached rules

This method enables refreshing your local copy of the PSL stored with your [PSR-16](http://www.php-fig.org/psr/psr-16/) Cache and retrieved using the Http Client. By default the method will use the `Manager::PSL_URL` as the source URL but you are free to substitute this URL with your own.  
The method returns a boolean value which is `true` on success.

~~~php
<?php

use Pdp\Cache;
use Pdp\CurlHttpClient;
use Pdp\Manager;

$manager = new Manager(new Cache(), new CurlHttpClient());
$manager->refreshRules('https://publicsuffix.org/list/public_suffix_list.dat');
~~~

## Automatic Updates

It is important to always have an up to date PSL ICANN Section. In order to do so the library comes bundle with an auto-update script located in the `bin` directory.

~~~bash
$ php ./bin/update-psl
~~~

This script requires that:

- the PHP `curl` extension
- The `Pdp\Installer` class which comes bundle with this package
- The use of the Cache and HTTP Client implementations bundle with the package.

If you prefer using your own implementations you should:

1. Copy the `Pdp\Installer` class
2. Adapt its code to reflect your requirements.


In any cases your are required to register a cron with your chosen script to keep your data up to date

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
$symfonyCache = new PDOCache($dbh, 'league-psl-icann', 86400);
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
$domain->isValid();              //returns false
~~~

In any case, you should setup a cron to regularly update your local cache.


### Public Suffix Resolver


#### Public Suffix and Domain Resolution

~~~php
<?php

namespace Pdp;

final class Rules
{
    const ALL_DOMAINS = 'ALL_DOMAINS';
    const ICANN_DOMAINS = 'ICANN_DOMAINS';
    const PRIVATE_DOMAINS = 'PRIVATE_DOMAINS';

    public function __construct(array $rules)
    public function resolve(string $domain = null, string $type = self::ALL_DOMAINS): Domain
}
~~~

The `Rules` constructor expects a `array` representation of the Public Suffix List. This `array` representation is constructed by the `Manager` and stored using a PSR-16 compliant cache.

The `Rules` class resolves the submitted domain against the parsed rules from the PSL. This is done using the `Rules::resolve` method which returns a `Pdp\Domain` object. The method expects

- a valid domain name as a string
- a string to optionnally specify which section of the PSL you want to validate the given domain against.  
 By default all sections are used `Rules::ALL_DOMAINS` but you can validate your domain against the ICANN only section (`Rules::ICANN_DOMAINS` or the private section (`Rules::PRIVATE_DOMAINS`) of the PSL.

~~~php
<?php

final class Domain
{
    public function getDomain(): ?string
    public function getPublicSuffix(): ?string
    public function getRegistrableDomain(): ?string
    public function getSubDomain(); ?string
    public function isKnown(): bool;
    public function isICANN(): bool;
    public function isPrivate(): bool;
}
~~~

The `Domain` getters method always return normalized value according to the domain status against the PSL rules.

<p class="message-notice"><code>Domain::isKnown</code> status depends on the PSL rules used. For the same domain, depending on the rules used a domain public suffix may be known or not.</p>

~~~php
<?php

use Pdp\Cache;
use Pdp\CurlHttpClient;
use Pdp\Domain;
use Pdp\Manager;

$manager = new Manager(new Cache(), new CurlHttpClient());
$rules = $manager->getRules('https://raw.githubusercontent.com/publicsuffix/list/master/public_suffix_list.dat');
//$rules is a Pdp\Rules object

$domain = $rules->resolve('www.ulb.ac.be');
$domain->getDomain();            //returns 'www.ulb.ac.be'
$domain->getPublicSuffix();      //returns 'ac.be'
$domain->getRegistrableDomain(); //returns 'ulb.ac.be'
$domain->getSubDomain();         //returns 'www'
$domain->isKnown();              //returns true
$domain->isICANN();              //returns true
$domain->isPrivate();            //returns false

//let's resolve the same domain against the PRIVATE DOMAIN SECTION

$domain = $rules->resolve('www.ulb.ac.be', Rules::PRIVATE_DOMAINS);
$domain->getDomain();            //returns 'www.ulb.ac.be'
$domain->getPublicSuffix();      //returns 'be'
$domain->getRegistrableDomain(); //returns 'ac.be'
$domain->getSubDomain();         //returns 'www.ulb'
$domain->isKnown();              //returns false
$domain->isICANN();              //returns false
$domain->isPrivate();            //returns false
~~~

<p class="message-warning"><strong>Warning:</strong> Some people use the PSL to determine what is a valid domain name and what isn't. This is dangerous, particularly in these days where new gTLDs are arriving at a rapid pace, if your software does not regularly receive PSL updates, it may erroneously think new gTLDs are not known. The DNS is the proper source for this information. If you must use it for this purpose, please do not bake static copies of the PSL into your software with no update mechanism.</p>

Contributing
-------

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

Credits
-------

- [Jeremy Kendall](https://github.com/jeremykendall)
- [ignace nyamagana butera](https://github.com/nyamsprod)
- [All Contributors](https://github.com/thephpleague/uri/contributors)

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

Portions of the PublicSuffixListManager and the DomainParser are derivative
works of the PHP
[registered-domain-libs](https://github.com/usrflo/registered-domain-libs).
Those parts of this codebase are heavily commented, and I've included a copy of
the Apache Software Foundation License 2.0 in this project.
