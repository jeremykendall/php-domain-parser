# PHP Domain Parser
**PHP Domain Parser** is a [Public Suffix List](http://publicsuffix.org/) based domain parser implemented in PHP.

[![Build Status][ico-travis]][link-travis]
[![Total Downloads][ico-packagist]][link-packagist]
[![Latest Stable Version][ico-release]][link-release]
[![Software License][ico-license]][link-license]

## Motivation

While there are plenty of excellent URL parsers and builders available, there
are very few projects that can accurately parse a domaine into its component
subdomain, registrable domain, and public suffix parts.

Consider the domain www.pref.okinawa.jp.  In this domain, the
*public suffix* portion is **okinawa.jp**, the *registrable domain* is
**pref.okinawa.jp**, and the *subdomain* is **www**. You can't regex that.

PHP Domain Parser is built around:

- accurate Public Suffix List based parsing.
- accurate Root Zone Database parsing.

## Installation

### Composer

~~~
$ composer require jeremykendall/php-domain-parser
~~~

### System Requirements

You need:

- **PHP >= 7.4** but the latest stable version of PHP is recommended
- the `intl` extension

## Usage

### Public Suffix List Resolution

The first objective of the library is to use the [Public Suffix List](http://publicsuffix.org/) to easily resolve a 
domain as a `Pdp\ResolvedDomain` object using the following methods:

~~~php
<?php 
use Pdp\Rules;

$rules = Rules::fromPath('/path/to/cache/public-suffix-list.dat');

echo $rules->resolve('www.ulb.ac.be')->getPublicSuffix();          //display 'ac.be';
echo $rules->getCookieDomain('www.ulb.ac.be')->getPublicSuffix();  //display 'ac.be';
echo $rules->getICANNDomain('www.ulb.ac.be')->getPublicSuffix();   //display 'ac.be';
echo $rules->getPrivateDomain('www.ulb.ac.be')->getPublicSuffix(); //display 'be';
~~~

In case of an error an exception which extends `Pdp\ExceptionInterface` is thrown.

### Top Level Domains resolution

While the [Public Suffix List](http://publicsuffix.org/) is a community based list, the package provides access to 
the Top Level domain information given by the [IANA website](https://data.iana.org/TLD/tlds-alpha-by-domain.txt) to always resolve
top domain against the newly registered TLD.

~~~php
use Pdp\TopLevelDomains;

$iana = TopLevelDomains::fromPath('/path/to/cache/tlds-alpha-by-domain.txt');

echo $iana->resolve('www.UlB.Ac.bE')->getPublicSuffix(); //display 'be';
~~~

In case of an error an exception which extends `Pdp\ExceptionInterface` is thrown.

### Domain, ResolvedDomain and PublicSuffix

In order to resolve a specific domain the package needs to make a distinction
between several domain representations. Each on of them is expressed as an immutable value object
which implements the `Pdp\Host` interface.

#### Domain

A `Pdp\Domain` instance is a host that exposes its labels and allow to change them.

~~~php
use Pdp\Domain;

$domain = new Domain('www.bébé.ExAmple.com');
$domain->getContent();             // www.bébé.example.com
echo $domain;                      // www.bébé.example.com
echo $domain->label(0);         // 'com'
echo $domain->label(-1);        // 'www'
$domain->keys('example');          // array(1)
count($domain);                    //returns 4
iterator_to_array($domain, false); // ['com', 'example', 'bébé', 'www']
$domain->labels();                 // ['com', 'example', 'bébé', 'www']  since v5.5
$domain->toAscii()->getContent();  // www.xn--bb-bjab.example.com
echo (new Domain('www.xn--bb-bjab.example.com'))->toAscii(); // www.bébé.example.com
$domain->getAsciiIDNAOption();     // IDNA_DEFAULT
$domain->getUnicodeIDNAOption();   // IDNA_DEFAULT
~~~

Using the above code you have parse, validate a domain name. The domain object has no information regarding its effective TLD.
To gain information against those databases you need to use their respective instance.

The `Pdp\Domain` object supports IDNA options for a better transformation between i18n and ascii domain name.

~~~php
<?php

use Pdp\Domain;

$defaultDomain = new Domain('faß.test.de');
echo $defaultDomain->toAscii()->getContent(); // 'fass.test.de'

$altDomain = new Domain('faß.test.de', IDNA_NONTRANSITIONAL_TO_ASCII, IDNA_NONTRANSITIONAL_TO_UNICODE);
echo $altDomain->toAscii()->getContent(); // 'xn--fa-hia.test.de'
~~~

The object also implements PHP's `Countable`, `IteratorAggregate` and `JsonSerializable` interfaces to ease retrieving the domain labels and properties.

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

#### Public suffix resolution.

**THIS EXAMPLE ILLUSTRATES HOW THE OBJECT WORK BUT SHOULD BE AVOIDED IN PRODUCTON**

~~~php
$pdp_url = 'https://raw.githubusercontent.com/publicsuffix/list/master/public_suffix_list.dat';
$rules = Pdp\Rules::fromPath($pdp_url);

$domain = $rules->resolve('www.Ulb.AC.be'); // resolution is done against all the sections available
echo $domain; // returns www.ulb.ac.be
echo $domain->getPublicSuffix();
// returns "ac.be"

//The same domain will return a different result using the PSL PRIVATE DOMAIN SECTION only

$domain = $rules->getPrivateDomain('www.Ulb.AC.be');
echo $domain->getPublicSuffix();
// returns "be"
~~~

**The domain public suffix status depends on the PSL section used to resolve it:**

- `Pdp\PublicSuffix::isKnown` returns `true` if the public suffix is found in the selected PSL;
- `Pdp\PublicSuffix::isICANN` returns `true` if the public suffix is found using a PSL which includes the ICANN DOMAINS section;
- `Pdp\PublicSuffix::isPrivate` returns `true` if the public suffix is found using a PSL which includes the PRIVATE DOMAINS section;

**WARNING:**

**You should never use the library this way in production, without, at least, a caching mechanism to reduce PSL downloads.**

**Using the PSL to determine what is a valid domain name and what isn't is dangerous, particularly in these days where new gTLDs are arriving at a rapid pace. The DNS is the proper source for this information. If you must use this library for this purpose, please consider integrating a PSL update mechanism into your software.**

### Top Level Domains resolutions

~~~php
<?php

namespace Pdp;

final class TopLevelDomains implements Countable, IteratorAggregate
{
    public static function fromPath(string $path, resource $context = null): TopLevelDomains
    public static function fromString(string $content): TopLevelDomains
    public static function fromJsonString(string $content): TopLevelDomains

    public function resolve($domain): ResolvedDomainName
    public function contains($domain): bool
    public function isEmpty(): bool
}
~~~

The `Pdp\TopLevelDomains` object is responsible for top level domain resolution for a given domain. The resolution is done using a resource which should follow [IANA resource file](https://data.iana.org/TLD/tlds-alpha-by-domain.txt). The class is a collection which contains the list of all top levels domain as registered on the IANA servers.

**THIS EXAMPLE ILLUSTRATES HOW THE OBJECT WORK BUT SHOULD BE AVOIDED IN PRODUCTON**

~~~php
use Pdp\TopLevelDomains;

$pdp_url = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';
$tlds    = TopLevelDomains::fromPath($pdp_url);
$result  = $tlds->contains('be'); // resolution is done against the retrieves list
$domain  = json_encode($tlds->resolve('www.Ulb.Ac.BE'), JSON_PRETTY_PRINT);
// returns "www.ulb.ac.be"
~~~

## Managing the package databases

The library comes bundle with a service which enables resolving domain name without the constant network overhead of continuously downloading the remote databases.
The `Pdp\Storage\PsrStorageFactory` enables returns instances that retrieve, convert and cache the Public Suffix List as well as the IANA Root Zone Database.

### Instantiate `Pdp\Storage\PsrStorageFactory`

To work as intended, the `Pdp\Storage\PsrStorageFactory` constructor requires:

- a [PSR-16](http://www.php-fig.org/psr/psr-16/) A Cache object to store the rules locally.
- a [PSR-18](http://www.php-fig.org/psr/psr-18/) A PSR-18 HTTP Client.
- a [PSR-3](http://www.php-fig.org/psr/psr-3/) A Logger object to log storage usage.

When creating a new storage instance you will require:

- a `$cachePrefix` argument to optionally add a prefix to your cache index;
- a `$ttl` argument if you need to set the default `$ttl`;

The `$ttl` argument can be:

- an `int` representing time in second (see PSR-16);
- a `DateInterval` object (see PSR-16);
- a `DateTimeInterface` object representing the date and time when the item should expire;

The package comes bundle no longer provide any implementation of such interfaces are
they are many robust implementation that can easily be found on packagist.org. 

#### Refreshing the cached PSL and RZD data

**THIS IS THE RECOMMENDED WAY OF USING THE LIBRARY**

~~~php
<?php 

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Pdp\Storage\PsrStorageFactory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

$cache = new Psr16Cache(new FilesystemAdapter('pdp', 3600, __DIR__.'/data'));
$requestFactory = new class implements RequestFactoryInterface {
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new Request($method, $uri);
    }
};

$cachePrefix = 'pdp_';
$cacheTtl = new DateInterval('P1D');
$factory = new PsrStorageFactory(new Client(), $requestFactory, $cache);
$pslStorage = $factory->createPublicSuffixListStorage($cachePrefix, $cacheTtl);
$rzdStorage = $factory->createRootZoneDatabaseStorage($cachePrefix, $cacheTtl);

$rules = $pslStorage->get(PsrStorageFactory::PSL_URL);
$tldDomains = $rzdStorage->get(PsrStorageFactory::RZD_URL);
~~~

### Automatic Updates

It is important to always have an up to date Public Suffix List and a Root Zone Database.
This library no longer provide an out of the box script to do so as implementing such job heavily depends on your application setup. 

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
