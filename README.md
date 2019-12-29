# PHP Domain Parser

**PHP Domain Parser** is a [Public Suffix List](http://publicsuffix.org/) based
domain parser implemented in PHP.

[![Build Status][ico-travis]][link-travis]
[![Total Downloads][ico-packagist]][link-packagist]
[![Latest Stable Version][ico-release]][link-release]
[![Software License][ico-license]][link-license]


Motivation
-------

While there are plenty of excellent URL parsers and builders available, there
are very few projects that can accurately parse a url into its component
subdomain, registrable domain, and public suffix parts.

Consider the domain www.pref.okinawa.jp.  In this domain, the
*public suffix* portion is **okinawa.jp**, the *registrable domain* is
**pref.okinawa.jp**, and the *subdomain* is **www**. You can't regex that.

PHP Domain Parser is built around accurate Public Suffix List based parsing. For URL parsing, building or manipulation please refer to [libraries][link-parse-library] focused on those area of development.

System Requirements
-------

You need:

- **PHP >= 7.0** but the latest stable version of PHP is recommended
- the `intl` extension

Dependencies
-------

- [PSR-16](http://www.php-fig.org/psr/psr-16/)
- [PSR-3](http://www.php-fig.org/psr/psr-3/) *since version 5.6*

Installation
--------

~~~
$ composer require jeremykendall/php-domain-parser
~~~

Usage
--------

~~~php
<?php

use Pdp\Cache;
use Pdp\CurlHttpClient;
use Pdp\Manager;
use Pdp\Rules;

$manager = new Manager(new Cache(), new CurlHttpClient());
$rules = $manager->getRules(); //$rules is a Pdp\Rules object

$domain = $rules->resolve('www.ulb.ac.be'); //$domain is a Pdp\Domain object
echo $domain->getContent();            // 'www.ulb.ac.be'
echo $domain->getPublicSuffix();       // 'ac.be'
echo $domain->getRegistrableDomain();  // 'ulb.ac.be'
echo $domain->getSubDomain();          // 'www'
$domain->isResolvable();               // returns true
$domain->isKnown();                    // returns true
$domain->isICANN();                    // returns true
$domain->isPrivate();                  // returns false
$domain->labels();                     // returns ['be', 'ac', 'ulb', 'www']
$publicSuffix = $rules->getPublicSuffix('mydomain.github.io', Rules::PRIVATE_DOMAINS); //$publicSuffix is a Pdp\PublicSuffix object
echo $publicSuffix->getContent(); // 'github.io'
$publicSuffix->isKnown();         // returns true
$publicSuffix->isICANN();         // returns false
$publicSuffix->isPrivate();       // returns true
$publicSuffix->labels();          // returns ['io', 'github']

$altSuffix = $rules->getPublicSuffix('mydomain.github.io', Rules::ICANN_DOMAINS);
echo $altSuffix->getContent(); // 'io'
$altSuffix->isKnown();         // returns true
$altSuffix->isICANN();         // returns true
$altSuffix->isPrivate();       // returns false

$tldList = $manager->getTLDs(); //$tldList is a Pdp\TopLevelDomains object
$domain = $tldList->resolve('www.ulb.ac.be'); //$domain is a Pdp\Domain object
$tldList->contains('be'); //returns true
$tldList->contains('localhost'); //return false
foreach($tldList as $tld) {
    //$tld is a Pdp\PublisSuffix object
}
~~~

Using the above code you have parse, validate and resolve a domain name and its public suffix status against the Public Suffix list.

**Starting with vesion 5.5 support for IDNA options is added to the package**

**Before**

~~~php
$manager = new Manager(new Cache(), new CurlHttpClient());
$rules = $manager->getRules();

$domain = $rules->resolve('faß.test.de');
echo $domain->toAscii()->getContent(); // 'fass.test.de'
~~~

**After**

~~~php
$manager = new Manager(new Cache(), new CurlHttpClient());
$rules = $manager->getRules()
    ->withAsciiIDNAOption(IDNA_NONTRANSITIONAL_TO_ASCII)
    ->withUnicodeIDNAOption(IDNA_NONTRANSITIONAL_TO_UNICODE);

// or
// 
// $rules = $manager->getRules(
//     Manager::PSL_URL,
//     null,
//     IDNA_NONTRANSITIONAL_TO_ASCII,
//     IDNA_NONTRANSITIONAL_TO_UNICODE
// );

$domain = $rules->resolve('faß.test.de');
echo $domain->toAscii()->getContent(); // 'xn--fa-hia.test.de'
~~~

Documentation
--------

### Domain objects

~~~php
use Pdp\Domain;
use Pdp\PublicSuffix;

$publicSuffix = new PublicSuffix('com');
$domain = new Domain('www.bébé.ExAmple.com', $publicSuffix);
$domain->getContent();             // www.bébé.example.com
echo $domain;                      // www.bébé.example.com
echo $domain->getLabel(0);         // 'com'
echo $domain->getLabel(-1);        // 'www'
$domain->keys('example');          // array(1)
count($domain);                    //returns 4
$domain->getPublicSuffix();        // 'com'
$domain->getRegistrableDomain();   // 'example.com'
$domain->getSubDomain();           // 'www.bébé'
$domain->isKnown();                // returns false
$domain->isICANN();                // returns false
$domain->isPrivate();              // returns false
iterator_to_array($domain, false); // ['com', 'example', 'bébé', 'www']
$domain->labels();                 // ['com', 'example', 'bébé', 'www']  since v5.5
$domain->toAscii()->getContent();  // www.xn--bb-bjab.example.com
echo (new Domain('www.xn--bb-bjab.example.com'))->toAscii() // www.bébé.example.com
$domain->getAsciiIDNAOption();     // IDNA_DEFAULT
$domain->getUnicodeIDNAOption();   // IDNA_DEFAULT
~~~

The `Pdp\Domain` and `Pdp\PublicSuffix` objects are an immutable value object representing a valid domain name. These objects let's you access the domain properties.

*The getter methods return normalized and lowercased domain labels or `null` if no value was found for a particular domain part.*

Theses objects also implements PHP's `Countable`, `IteratorAggregate` and `JsonSerializable` interfaces to ease retrieving the domain labels and properties.

Modify the domain content is only possible for the `Pdp\Domain` object using the following methods:

~~~php
public function Domain::isResolvable();
public function Domain::withLabel(int $key, $label): Domain
public function Domain::withoutLabel(int $key, int ...$keys): Domain
public function Domain::append($label): Domain
public function Domain::prepend($label): Domain
public function Domain::withPublicSuffix($publicSuffix): Domain
public function Domain::withSubDomain($subDomain): Domain
public function Domain::withAsciiIDNAOption(int $option): Domain
public function Domain::withUnicodeIDNAOption(int $option): Domain
~~~

~~~php
use Pdp\Domain;

$domain = new Domain('www.bébé.be');
$domain->getContent();     // 'www.bébé.be'
echo $domain->toAscii();   // 'www.xn--bb-bjab.be'
echo $domain->toUnicode(); // 'www.bébé.be'
$newDomain = $domain
    ->withLabel(-1, 'shop')
    ->withLabel(0, 'com')
    ->withoutLabel(1)
;
echo $domain;   // 'www.bébé.be'
echo $newDomain // 'shop.com'
~~~

Because the `Pdp\Domain` object is immutable:

- If the method change any of the current object property, a new object is returned.
- If a modification is not possible a `Pdp\Exception` exception is thrown.

**WARNING: URI and URL accept registered name which encompass domain name. Therefore, some URI host are invalid domain name and will trigger an exception if you try to instantiate a `Pdp\Domain` with them.**

The `Pdp\Domain` object can tell whether a public suffix can be attached to it using the `Pdp\Domain::isResolvable` method.

~~~php
use Pdp\Domain;

$domain = new Domain('www.ExAmple.com');
$domain->isResolvable(); //returns true;

$altDomain = new Domain('localhost');
$altDomain->isResolvable(); //returns false;
~~~

Here's a more complex example:

~~~php
$domain = $rules->resolve('www.bbc.co.uk');
$domain->getContent();      //returns 'www.bbc.co.uk';
$domain->getPublicSuffix(); //returns 'co.uk';
$domain->isKnown();         //return true;
$domain->isICANN();         //return true;

$newDomain = $domain
    ->withPublicSuffix('com')
    ->withSubDomain('shop')
    ->withLabel(-2, 'example')
;
$newDomain->getContent();      //returns 'shop.example.com';
$newDomain->getPublicSuffix(); //returns 'com';
$newDomain->isKnown();         //return false;
~~~

**WARNING: in the example above the public suffix informations are lost because the newly attached public suffix had none.**

To avoid this data loss you should use a `Pdp\PublicSuffix` object instead.

~~~php
$domain = $rules->resolve('www.bbc.co.uk');
$newPublicSuffix = $rules->getPublicSuffix('example.com'); //$newPublicSuffix is a Pdp\PublicSuffix object
$newDomain = $domain
    ->withPublicSuffix($newPublicSuffix)
    ->withSubDomain('shop')
    ->withLabel(-2, 'example')
;
$newDomain->getContent();      //returns 'shop.example.com';
$newDomain->getPublicSuffix(); //returns 'com';
$newDomain->isKnown();         //return true;
~~~

### Public suffix resolution.

~~~php
<?php

namespace Pdp;

final class Rules
{
    public function __construct(
        array $rules,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): void

    public static function createFromPath(
        string $path,
        resource $context = null,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): Rules

    public static function createFromString(
        string $content,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): Rules

    public function resolve($domain, string $section = ''): Domain
    public function getPublicSuffix($domain, string $section = ''): PublicSuffix
    public function getAsciiIDNAOption(): int
    public function getUnicodeIDNAOption(): int
    public function withAsciiIDNAOption(int $asciiIDNAOption): Rules
    public function withUnicodeIDNAOption(int $unicodeIDNAOption): Rules
}
~~~

The `Pdp\Rules` object is responsible for public suffix resolution for a given domain. Public suffix resolution is done using:

- the `Pdp\Rules::resolve` method which returns a `Pdp\Domain` object;
- the `Pdp\Rules::getPublicSuffix` methods which returns a `Pdp\PublicSuffix` object;

Both methods expect the same arguments:

- `$domain` a domain name
- `$section` a string which specifies which section of the PSL you want to validate the given domain against. The possible values are:
    - `Rules::ICANN_DOMAINS`, to validate against the PSL ICANN DOMAINS section only.
    - `Rules::PRIVATE_DOMAINS`, to validate against the PSL PRIVATE DOMAINS section only.
    - the empty string to validate against all the PSL sections.

By default, the `$section` argument is equal to the empty string. If an unsupported section is submitted a `Pdp\Exception` exception will be thrown.

**THIS EXAMPLE ILLUSTRATES HOW THE OBJECT WORK BUT SHOULD BE AVOIDED IN PRODUCTON**

~~~php
$pdp_url = 'https://raw.githubusercontent.com/publicsuffix/list/master/public_suffix_list.dat';
$rules = Pdp\Rules::createFromPath($pdp_url);

$domain = $rules->resolve('www.Ulb.AC.be'); // resolution is done against all the sections available
echo $domain; // returns www.ulb.ac.be
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

//The same domain will return a different result using the PSL PRIVATE DOMAIN SECTION only

$domain = $rules->resolve('www.Ulb.AC.be', Rules::PRIVATE_DOMAINS);
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

**The domain public suffix status depends on the PSL section used to resolve it:**

- `Pdp\Domain::isKnown` returns `true` if the public suffix is found in the selected PSL;
- `Pdp\Domain::isICANN` returns `true` if the public suffix is found using a PSL which includes the ICANN DOMAINS section;
- `Pdp\Domain::isPrivate` returns `true` if the public suffix is found using a PSL which includes the PRIVATE DOMAINS section;

**WARNING:**

**You should never use the library this way in production, without, at least, a caching mechanism to reduce PSL downloads.**

**Using the PSL to determine what is a valid domain name and what isn't is dangerous, particularly in these days where new gTLDs are arriving at a rapid pace. The DNS is the proper source for this information. If you must use this library for this purpose, please consider integrating a PSL update mechanism into your software.**

### Top Level Domains resolutions

**since version 5.4**

~~~php
<?php

namespace Pdp;

final class TopLevelDomains implements Countable, IteratorAggregate
{
    public function __construct(
        array $records,
        string $version,
        DateTimeInterface $modifiedDate,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): void

    public static function createFromPath(
        string $path,
        resource $context = null,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): TopLevelDomains

    public static function createFromString(
        string $content,
        int $asciiIDNAOption = IDNA_DEFAULT,
        int $unicodeIDNAOption = IDNA_DEFAULT
    ): TopLevelDomains

    public function resolve($domain): Domain
    public function contains($domain): bool
    public function isEmpty(): bool
    public function getAsciiIDNAOption(): int
    public function getUnicodeIDNAOption(): int
    public function withAsciiIDNAOption(int $option): TopLevelDomains
    public function withUnicodeIDNAOption(int $option): TopLevelDomains
}
~~~

The `Pdp\TopLevelDomains` object is responsible for top level domain resolution for a given domain. The resolution is done using a resource which should follow [IANA resource file](https://data.iana.org/TLD/tlds-alpha-by-domain.txt). The class is a collection which contains the list of all top levels domain as registered on the IANA servers.

**THIS EXAMPLE ILLUSTRATES HOW THE OBJECT WORK BUT SHOULD BE AVOIDED IN PRODUCTON**

~~~php
use Pdp\TopLevelDomains;

$pdp_url = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';
$tlds    = TopLevelDomains::createFromPath($pdp_url);
$result  = $tlds->contains('be'); // resolution is done against the retrieves list
$domain  = json_encode($tlds->resolve('www.Ulb.Ac.BE'), JSON_PRETTY_PRINT);
// returns
//  {
//      "domain": "www.ulb.ac.be",
//      "registrableDomain": "ac.be",
//      "subDomain": "www.ulb",
//      "publicSuffix": "be",
//      "isKnown": true,
//      "isICANN": true,
//      "isPrivate": false
//  }
~~~

### Managing the package lists

The library comes bundle with a service which enables resolving domain name without the constant network overhead of continously downloading the PSL. The `Pdp\Manager` class retrieves, converts and caches the PSL as well as creates the corresponding `Pdp\Rules` object on demand. It internally uses a `Pdp\Converter` object to convert the fetched PSL into its `array` representation when required.

~~~php
<?php

namespace Pdp;

use Psr\SimpleCache\CacheInterface;

final class Manager
{
    const PSL_URL = 'https://publicsuffix.org/list/public_suffix_list.dat';
    const RZD_URL = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';

    public function __construct(CacheInterface $cache, HttpClient $http, $ttl = null)
}
~~~

#### Instantiate `Pdp\Manager`

To work as intended, the `Pdp\Manager` constructor requires:

- a [PSR-16](http://www.php-fig.org/psr/psr-16/) Cache object to store the rules locally.
- a `Pdp\HttpClient` object to retrieve the PSL.
- a `$ttl` argument if you need to set the default $ttl; **since 5.4**

The `$ttl` argument can be:

- an `int` representing time in second (see PSR-16);
- a `DateInterval` object (see PSR-16);
- a `DateTimeInterface` object representing the date and time when the item should expire;

**the `$ttl` argument is added to improve PSR-16 interoperability**

The `Pdp\HttpClient` is a simple interface which exposes the `HttpClient::getContent` method. This method expects a string URL representation has its sole argument and returns the body from the given URL resource as a string.  
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

#### Refreshing the cached PSL and TLD data

~~~php
public Manager::refreshRules(string $source_url = self::PSL_URL, $ttl = null): bool
public Manager::refreshTLD(string $source_url = self::RZD_URL, $ttl = null): bool
~~~

The both methods method enables refreshing your local copy of the stored resources with your [PSR-16](http://www.php-fig.org/psr/psr-16/) Cache and retrieved using the Http Client. By default the method will use the resource default source URL but you are free to substitute this URL with your own.

The method returns a boolean value which is `true` on success.

~~~php
$manager = new Pdp\Manager(new Pdp\Cache(), new Pdp\CurlHttpClient());
$retval = $manager->refreshRules('https://publicsuffix.org/list/public_suffix_list.dat');
if ($retval) {
    //the local cache has been updated
} else {
    //the local cache was not updated
}
~~~

#### Returning `Pdp\Rules` and `Pdp\TopLevelDomains` objects

~~~php
public Manager::getRules(
    string $url = self::PSL_URL,
    $ttl = null,
    int $asciiIDNAOption = IDNA_DEFAULT,
    int $unicodeIDNAOption = IDNA_DEFAULT    
): Rules

public Manager::getTLDs(
    string $url = self::RZD_URL,
    $ttl = null,
    int $asciiIDNAOption = IDNA_DEFAULT,
    int $unicodeIDNAOption = IDNA_DEFAULT
): TopLevelDomains
~~~

These methods returns a `Pdp\Rules` or `Pdp\TopLevelDomains` objects seeded with their corresponding data fetch from the cache or from the external resources depending on the submitted `$ttl` argument.

These methods take an optional `$url` argument which specifies the PSL source URL. If no local cache exists for the submitted source URL, the method will:

1. call `Manager::refreshRules` with the given URL and `$ttl` argument to update its local cache
2. instantiate the `Rules` or the `TopLevelDomains` objects with the newly cached data.

On error, theses methods will throw an `Pdp\Exception`.

 **since version 5.5**

the following optional arguments are added to the methods:

- `$asciiIDNAOption` optional IDNA option for ascii conversion;
- `$asciiIDNAOption` optional IDNA option for unicode conversion;

**theses arguments are a combination of `IDNA_*` constants (except `IDNA_ERROR_*` constants).**

They are used when instantiated the returned object.

**THIS IS THE RECOMMENDED WAY OF USING THE LIBRARY**

~~~php
$manager = new Pdp\Manager(new Pdp\Cache(), new Pdp\CurlHttpClient());
$tldCollection = $manager->getTLDs(self::RZD_URL);
$domain = $tldCollection->resolve('www.ulb.ac.be');
echo $domain->getPublicSuffix(); // print 'be'
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
- A `Psr3` implementation.

You can add a composer script in your `composer.json` file to update the PSL cache every time after the `install` or the `update` command are executed.

~~~bash
{
    "scripts": {
        "post-install-cmd": "\\Pdp\\Installer::updateLocalCache",
        "post-update-cmd": "\\Pdp\\Installer::updateLocalCache"
    }
}
~~~

#### Advanced usage of the update-psl script

**since version 5.6.0**

The following command is accessible to the end user:

```bash
$ php bin/update-psl --rzd --rzd-url=http://localhost/rzd-mirror/list.txt --ttl="8 HOURS" --cache-dir="/tmp"
````

This lines means:

- the only cache that will be updated will be the one for the Root Zone Domains;
- it will be updated using the submitted URL;
- the data will be cached for 8 hours;
- the cache directory will be the `/tmp` directory.;

##### Options and arguments

- `--cache-dir` : specify the root directory used to save the cached data;
- `h`, `--h`, `--help` : display the helper message;
- `--psl`: specify that the PSL cache must be updated;
- `--psl-url`: specify that PSL source URL;
- `--rzd`: specify that the RZD  cache must be updated;
- `--rzd-url`: specify that RZD source URL;
- `--ttl`: specify the cache TTL;

#### Alternatives to the update-psl script

**Using the `update-psl` script is not a requirement but your MUST update regularly your PSL information to keep your cache data up to date.**

For example, below I'm using the `Manager` with

- the [Symfony Cache component](https://github.com/symfony/cache)
- the [Guzzle](https://github.com/guzzle/guzzle) client.

Of course you can add more setups depending on your usage.

**Be sure to adapt the following code to your own framework/situation. The following code is given as an example without warranty of it working out of the box.**

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
$manager = new Manager(
    new PDOCache($dbh, 'psl', 86400),
    new GuzzleHttpClientAdapter(new GuzzleClient())
);
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

Changelog
-------

Please see [CHANGELOG](CHANGELOG.md) for more information about what has been changed since version **5.0.0** was released.

Contributing
-------

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

Testing
-------

`pdp-domain-parser` has:

- a [PHPUnit](https://phpunit.de) test suite
- a coding style compliance test suite using [PHP CS Fixer](http://cs.sensiolabs.org/).
- a code analysis compliance test suite using [PHPStan](https://github.com/phpstan/phpstan).

To run the tests, run the following command from the project folder.

``` bash
$ composer test
```

Security
-------

If you discover any security related issues, please email nyamsprod@gmail.com instead of using the issue tracker.

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

[ico-travis]: https://img.shields.io/travis/jeremykendall/php-domain-parser/master.svg?style=flat-square
[ico-packagist]: https://img.shields.io/packagist/dt/jeremykendall/php-domain-parser.svg?style=flat-square
[ico-release]: https://img.shields.io/github/release/jeremykendall/php-domain-parser.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square

[link-travis]: https://travis-ci.org/jeremykendall/php-domain-parser
[link-packagist]: https://packagist.org/packages/jeremykendall/php-domain-parser
[link-release]: https://github.com/jeremykendall/php-domain-parser/releases
[link-license]: https://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE

[link-parse-library]: https://packagist.org/explore/?query=rfc3986
