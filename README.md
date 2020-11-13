# PHP Domain Parser
**PHP Domain Parser** is a [Public Suffix List](http://publicsuffix.org/) based domain parser implemented in PHP.

![Quality Assurance](https://github.com/jeremykendall/php-domain-parser/workflows/Quality%20Assurance/badge.svg)
[![Total Downloads][ico-packagist]][link-packagist]
[![Latest Stable Version][ico-release]][link-release]
[![Software License][ico-license]][link-license]

## Motivation

While there are plenty of excellent URL parsers and builders available, there
are very few projects that can accurately parse a domain into its component
subdomain, registrable domain, and public suffix parts.

Consider the domain www.pref.okinawa.jp.  In this domain, the
*public suffix* portion is **okinawa.jp**, the *registrable domain* is
**pref.okinawa.jp**, and the *subdomain* is **www**. You can't regex that.

PHP Domain Parser is compliant around:

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

#### Resolving Domains

The first objective of the library is to use the [Public Suffix List](http://publicsuffix.org/) to easily resolve a 
domain as a `Pdp\ResolvedDomain` object using the following methods:

~~~php
<?php 
use Pdp\Rules;

$rules = Rules::fromPath('/path/to/cache/public-suffix-list.dat');

$resolvedDomain = $rules->resolve('www.PreF.OkiNawA.jP');
echo $resolvedDomain->getDomain();            //display 'www.pref.okinawa.jp';
echo $resolvedDomain->getPublicSuffix();      //display 'okinawa.jp';
echo $resolvedDomain->getSecondLevelDomain(); //display 'pref';
echo $resolvedDomain->getRegistrableDomain(); //display 'pref.okinawa.jp';
echo $resolvedDomain->getSubDomain();         //display 'www';
~~~

In case of an error an exception which extends `Pdp\CannotProcessHost` is thrown.

#### Public Suffix Type

The [Public Suffix List](http://publicsuffix.org/) is organized in sections. This library can give you access to this
information via its public suffix object.

~~~php
<?php 
use Pdp\Rules;

$rules = Rules::fromPath('/path/to/cache/public-suffix-list.dat');

$publicSuffix = $rules->resolve('example.github.io')->getPublicSuffix();

echo $publicSuffix;         // display 'github.io';
$publicSuffix->isICANN();   // will return false
$publicSuffix->isPrivate(); // will return true
$publicSuffix->isKnown();   // will return true
~~~

Because the PSL algorithm is fault tolerant this library exposes more strict methods

- `Rules::getCookieDomain`
- `Rules::getICANNDomain`
- `Rules::getPrivateDomain`

These methods act and resolve the domain against the PSL just like the `resolve` method but will throw
an exception if no valid effective TLD is found in the respective PSL section or if the submitted domain is invalid.

~~~php
<?php 
use Pdp\Rules;

$rules = Rules::fromPath('/path/to/cache/public-suffix-list.dat');

$rules->getICANNDomain('qfdsf.unknownTLD');
// will trigger an UnableToResolveDomain exception because `.unknownTLD` is not 
// part of the ICANN section

$rules->getCookieDomain('qfdsf.unknownTLD');
// will not throw because the domain syntax is correct.

$rules->getCookieDomain('com');
// will throw because no public suffix can be determined

$rules->resolve('com');
// will return a Nullable Resolved domain
~~~

#### Accessing and processing Domain labels

From the `ResolvedDomain` you can access the underlying domain object using the `ResolvedDomain::getDomain` method.
Accessing this object enables you to work with the domain labels. 

**WARNING: all objects are immutable, modifying the underlying object will not affect the parent object.**

~~~php
<?php 
use Pdp\Rules;

$rules = Rules::fromPath('/path/to/cache/public-suffix-list.dat');

$resolvedDomain = $rules->resolve('www.example.com');
$domain = $resolvedDomain->getDomain();
$domain->labels();  // returns ['com', 'example', 'www'];
$domain->label(-1); // returns 'www'
$domain->label(0);  // returns 'com'
foreach ($domain as $label) {
   echo $label, PHP_EOL;
}
// display 
// com
// example
// www
~~~ 

You can also add or remove labels according to their key index using the following methods:

- `Domain::withLabel(int $key, string|Stringable $label): self;`
- `Domain::withoutLabel(int $key, int ...$keys): self;`
- `Domain::append(string|Stringable $label): self;`
- `Domain::prepend(string|Stringable $label): self;`

### Top Level Domains resolution

While the [Public Suffix List](http://publicsuffix.org/) is a community based list, the package provides access to 
the Top Level domain information given by the [IANA website](https://data.iana.org/TLD/tlds-alpha-by-domain.txt) to always resolve
top domain against all registered TLD even the new ones.

~~~php
use Pdp\TopLevelDomains;

$iana = TopLevelDomains::fromPath('/path/to/cache/tlds-alpha-by-domain.txt');

$resolvedDomain = $iana->resolve('www.PreF.OkiNawA.jP');
echo $resolvedDomain->getDomain();            //display 'www.pref.okinawa.jp';
echo $resolvedDomain->getPublicSuffix();      //display 'jp';
echo $resolvedDomain->getSecondLevelDomain(); //display 'okinawa';
echo $resolvedDomain->getRegistrableDomain(); //display 'okinawa.jp';
echo $resolvedDomain->getSubDomain();         //display 'www.pref';
~~~

In case of an error an exception which extends `Pdp\CannotProcessHost` is thrown.

**WARNING:**

**You should never use the library this way in production, without, at least, a caching mechanism to reduce PSL downloads.**

**Using the Public Suffix List to determine what is a valid domain name and what isn't is dangerous, particularly in these days when new gTLDs are arriving at a rapid pace.**
**The DNS is the proper source for this information.** 

**If you are looking to know the validity of a Top Level Domain, the IANA Root Zone Database is the proper source for this information.** 

**If you must use this library for any of the above purposes, please consider integrating an update mechanism into your software.**

## Managing the package databases

Depending on your software the mechanism to store your database may differ, nevertheless, the library comes bundle with a **optional service** 
which enables resolving domain name without the constant network overhead of continuously downloading the remote databases.

The `Pdp\Storage\PsrStorageFactory` enables returning storage instances that retrieve, convert and cache the Public Suffix List as well as the IANA Root Zone Database using
standard interfaces published by the PHP-FIG to improve its interoperability with any modern PHP codebase.

### Instantiate `Pdp\Storage\PsrStorageFactory`

To work as intended, the `Pdp\Storage\PsrStorageFactory` constructor requires:

- a [PSR-16](http://www.php-fig.org/psr/psr-16/) A Cache object to store the rules locally.
- a [PSR-18](http://www.php-fig.org/psr/psr-18/) A PSR-18 HTTP Client.

When creating a new storage instance you will require:

- a `$cachePrefix` argument to optionally add a prefix to your cache index, default to the empty string `'''`;
- a `$ttl` argument if you need to set the default `$ttl`, default to `null` to use the underlying caching default TTL;

The `$ttl` argument can be:

- an `int` representing time in second (see PSR-16);
- a `DateInterval` object (see PSR-16);
- a `DateTimeInterface` object representing the date and time when the item should expire;

However, the package no longer provides any implementation of such interfaces are
they are many robust implementations that can easily be found on packagist.org. 

#### Refreshing the cached PSL and RZD data

**THIS IS THE RECOMMENDED WAY OF USING THE LIBRARY**

For the purpose of this example we used:
 
- Guzzle as a PSR-18 implementation HTTP client
- The Symfony Cache Component to use a PSR-16 cache implementation

You could easily use other packages as long as they implement the required PSR interfaces. 

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
$client = new Client();
$requestFactory = new class implements RequestFactoryInterface {
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new Request($method, $uri);
    }
};

$cachePrefix = 'pdp_';
$cacheTtl = new DateInterval('P1D');
$factory = new PsrStorageFactory($cache, $client, $requestFactory);
$pslStorage = $factory->createPublicSuffixListStorage($cachePrefix, $cacheTtl);
$rzdStorage = $factory->createRootZoneDatabaseStorage($cachePrefix, $cacheTtl);

$rules = $pslStorage->get(PsrStorageFactory::URL_PSL);
$tldDomains = $rzdStorage->get(PsrStorageFactory::URL_RZD);
~~~

### Automatic Updates

It is important to always have an up to date Public Suffix List and Root Zone Database.
This library no longer provide an out of the box script to do so as implementing such a job heavily depends on your application setup. 

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
- [Ignace Nyamagana Butera](https://github.com/nyamsprod)
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

[ico-packagist]: https://img.shields.io/packagist/dt/jeremykendall/php-domain-parser.svg?style=flat-square
[ico-release]: https://img.shields.io/github/release/jeremykendall/php-domain-parser.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/jeremykendall/php-domain-parser
[link-release]: https://github.com/jeremykendall/php-domain-parser/releases
[link-license]: https://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE
