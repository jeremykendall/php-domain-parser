# PHP Domain Parser

**PHP Domain Parser** is domain parser implemented in PHP.

![Quality Assurance](https://github.com/jeremykendall/php-domain-parser/workflows/Quality%20Assurance/badge.svg)
[![Total Downloads][ico-packagist]][link-packagist]
[![Latest Stable Version][ico-release]][link-release]
[![Software License][ico-license]][link-license]

## Motivation

While there are plenty of excellent URL parsers and builders available, there
are very few projects that can accurately parse a domain into its component
subdomain, registrable domain, second level domain and public suffix parts.

Consider the domain www.pref.okinawa.jp.  In this domain, the
*public suffix* portion is **okinawa.jp**, the *registrable domain* is
**pref.okinawa.jp**, the *subdomain* is **www** and 
the *second level domain* is **pref**.  
You can't regex that.

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

**If you are upgrading from version 5 please check the [upgrading guide](UPGRADING.md) for known issues.**

### Resolving Domains

To effectively resolve a domain you need a public source. This library can
resolve a domain against:
 
- The [Public Suffix List](http://publicsuffix.org/)
- The [IANA Root Zone Database](https://data.iana.org/TLD/tlds-alpha-by-domain.txt)

**WARNING: all objects are immutable, modifying the underlying object will not 
affect the parent object.**

#### Resolving the Domain against the Public Suffix List

Using the `Pdp\Rules` class you can resolve a domain as a `Pdp\ResolvedDomain` 
object against the [Public Suffix List](http://publicsuffix.org/) as shown below:

~~~php
<?php 
use Pdp\Rules;

$rules = Rules::fromPath('/path/to/cache/public-suffix-list.dat');

$resolvedDomain = $rules->resolve('www.PreF.OkiNawA.jP');
echo $resolvedDomain->toString();                         //display 'www.pref.okinawa.jp';
echo $resolvedDomain->getSubDomain()->toString();         //display 'www';
echo $resolvedDomain->getSecondLevelDomain();             //display 'pref';
echo $resolvedDomain->getRegistrableDomain()->toString(); //display 'pref.okinawa.jp';
echo $resolvedDomain->getPublicSuffix()->toString();      //display 'okinawa.jp';
$resolvedDomain->getPublicSuffix()->isICANN();            //returns true;
~~~

In case of an error an exception which implements the `Pdp\CannotProcessHost` 
is thrown.

The `Pdp\ResolvedDomain` instance can be modify using the following methods:

~~~php
<?php 
use Pdp\Rules;

$rules = Rules::fromPath('/path/to/cache/public-suffix-list.dat');

$resolvedDomain = $rules->resolve('shop.example.com');
$newResolvedDomain = $resolvedDomain
    ->withSubDomain('foo.bar')
    ->withSecondLevelDomain('test')
    ->withPublicSuffix('example');

echo $resolvedDomain->toString();              //display 'shop.example.com';
$resolvedDomain->getPublicSuffix()->isKnown(); //returns true;

echo $newResolvedDomain->toString();               //display 'foo.bar.test.example';
$newResolvedDomain->getPublicSuffix()->isKnown();  //returns false;
~~~

The public suffix method `isKnown` will always return `false` if 
you use a simple string to update the public suffix. 
If you use a `PublicSuffix` object the method may return `true`.
See the following section for more information.

#### Public Suffix List Sections

The [Public Suffix List](http://publicsuffix.org/) is organized in sections.
This library can give you access to this information via its public suffix 
object.

~~~php
<?php 
use Pdp\Rules;

$rules = Rules::fromPath('/path/to/cache/public-suffix-list.dat');

$publicSuffix = $rules->resolve('example.github.io')->getPublicSuffix();

echo $publicSuffix->toString(); // display 'github.io';
$publicSuffix->isICANN();       // will return false
$publicSuffix->isPrivate();     // will return true
$publicSuffix->isKnown();       // will return true
~~~

The public suffix state depends on its value:
 
- `isKnown` returns `true` if the value is part of the PSL.
- `isICANN` returns `true` if the value is part of the PSL ICANN section.
- `isPrivate` returns `true` if the value is part of the PSL private section.

If the value is not present in the PSL all the methods above will return `false`.

Because the PSL algorithm is fault tolerant this library exposes more strict 
methods:

- `Rules::getCookieDomain`
- `Rules::getICANNDomain`
- `Rules::getPrivateDomain`

These methods act and resolve the domain against the PSL just like 
the `resolve` method but will throw an exception if no valid effective 
TLD is found in the respective PSL section or if the submitted domain 
is invalid.

~~~php
<?php 
use Pdp\Rules;

$rules = Rules::fromPath('/path/to/cache/public-suffix-list.dat');

$rules->getICANNDomain('qfdsf.unknownTLD');
// will throw because `.unknownTLD` is not part of the ICANN section

$rules->getCookieDomain('qfdsf.unknownTLD');
// will not throw because the domain syntax is correct.

$rules->getCookieDomain('com');
// will throw because no public suffix can be determined

$rules->resolve('com');
// will return a Nullable Resolved domain
~~~
 
#### Resolving the Domain against the IANA Root Zone Database

While the [Public Suffix List](http://publicsuffix.org/) is a community based 
list, the package provides access to the Top Level domain information given by 
the [IANA website](https://data.iana.org/TLD/tlds-alpha-by-domain.txt) to always 
resolve top domain against all registered TLD even the new ones.

~~~php
use Pdp\TopLevelDomains;

$iana = TopLevelDomains::fromPath('/path/to/cache/tlds-alpha-by-domain.txt');

$resolvedDomain = $iana->resolve('www.PreF.OkiNawA.jP');
echo $resolvedDomain->toString();                         //display 'www.pref.okinawa.jp';
echo $resolvedDomain->getPublicSuffix()->toString();      //display 'jp';
echo $resolvedDomain->getSecondLevelDomain();             //display 'okinawa';
echo $resolvedDomain->getRegistrableDomain()->toString(); //display 'okinawa.jp';
echo $resolvedDomain->getSubDomain()->toString();         //display 'www.pref';
~~~

In case of an error an exception which extends `Pdp\CannotProcessHost` is thrown.

**WARNING:**

**You should never use the library this way in production, without, at least, a 
caching mechanism to reduce PSL downloads.**

**Using the Public Suffix List to determine what is a valid domain name and what 
isn't is dangerous, particularly in these days when new gTLDs are arriving at a 
rapid pace.**

**The DNS is the proper source for this information.** 

**If you are looking to know the validity of a Top Level Domain, the IANA Root Zone 
Database is the proper source for this information.** 

**If you must use this library for any of the above purposes, please consider 
integrating an update mechanism into your software.**

### Accessing and processing Domain labels

If you are interested into manipulating the domain labels without taking into 
account the Effective TLD, you can access them using the `getDomain` method 
from the `ResolvedDomain` or the `PublicSuffixList` instances.

Accessing this object enables you to work with the domain labels.

It is possible to access the labels composing the underlying public suffix 
domain using the following call:

Domain objects usage are explain in the next section.

~~~php
<?php 
/** @var  Rules $rules */
$resolvedDomain = $rules->resolve('www.bbc.co.uk');
$domain = $resolvedDomain->getDomain();
echo $domain->toString(); // display 'www.bbc.co.uk'
count($domain);           // returns 4
$domain->labels();        // returns ['uk', 'co', 'bbc', 'www'];
$domain->label(-1);       // returns 'www'
$domain->label(0);        // returns 'uk'
foreach ($domain as $label) {
   echo $label, PHP_EOL;
}
// display 
// uk
// co
// bbc
// www

$publicSuffixDomain = $resolvedDomain->getPublicSuffix()->getDomain();
$publicSuffixDomain->labels(); // returns ['uk', 'co']
~~~ 

You can also add or remove labels according to their key index using the 
following methods:

~~~php
<?php 

/** @var  Rules $rules */
$resolvedDomain = $rules->resolve('www.ExAmpLE.cOM');
$domain = $resolvedDomain->getDomain();

$newDomain = $domain
    ->withLabel(1, 'com')  //replace 'example' by 'com'
    ->withoutLabel(0, -1)  //remove the first and last labels
    ->append('www')
    ->prepend('docs.example');

echo $domain->toString();    // display 'www.example.com'
echo $newDomain->toString(); // display 'docs.example.com.www'
~~~ 

The following methods from the `ResolvedDomain` object will **also** return 
an `Domain` object:

- `ResolvedDomain::getRegistrableDomain`
- `ResolvedDomain::getSubDomain`

**WARNING: Because of its definition, a domain name can be `null` or a non-empty 
string; empty string domain are invalid.**

To distinguish this possibility the object exposes two (2) formatting methods 
`Domain::value` which can be `null` or a `string` and `Domain::toString` which 
will always cast the domain value to a string.

 ~~~php
use Pdp\Domain;
 
$domain = new Domain(null);
$domain->value(); // returns null;
$domain->toString(); // returns '';
 
$domain = new Domain(''); // will throw
 ~~~ 

### Internationalization

Domain names come in different format (ascii and unicode format), the package 
by default will convert the domain in its ascii format for resolution against
the public suffix source and convert it back to its unicode form if needed. 
This is done using PHP `ext-intl` extension. As such all domain objects expose 
a `toAscii` and a `toUnicode` methods which returns a new instance in the
converted format.

~~~php
/** @var  Rules $rules */
$unicodeDomain = $rules->resolve(new Domain('bébé.be'));
echo $unicodeDomain->toString();        // returns 'bébé.be'
$unicodeDomain->getSecondLevelDomain(); // returns 'bébé'

$asciiDomain = $rules->resolve(new Domain('xn--bb-bjab.be'));
$asciiDomain->toString();             // returns 'xn--bb-bjab.be'
$asciiDomain->getSecondLevelDomain(); // returns 'xn--bb-bjab'

$asciiDomain->toUnicode()->toString() === $unicodeDomain->toString(); //returns true
~~~

Because the domain conversion occurs during instantiation to normalize the 
domain name you are required to give the `IDNA_*` constants on domain 
construction. All domain objects accept as optional parameters the 
`$asciiIDNAOption` and the `$unicodeIDNAOption` where those variables should be
a combination of the `IDNA_*` constants (except `IDNA_ERROR_*` constants) used 
with the `idn_to_utf8` and `idn_to_ascii` functions from the `ext-intl` package.

~~~php
use Pdp\Domain;

$domain = new Domain('faß.de');
$altDomain = new Domain('faß.de', IDNA_NONTRANSITIONAL_TO_ASCII, IDNA_NONTRANSITIONAL_TO_UNICODE);

/** @var  Rules $rules */
echo $rules->resolve($domain)->toString(); // display 'fass.de'
echo $rules->resolve($altDomain)->toString(); // display 'faß.de'
~~~

**TIP: Always favor submitting a `Domain` object for resolution rather that a 
string or an object that can be cast to a string to avoid unexpected format 
conversion results.**

## Managing the package databases

Depending on your application, the mechanism to store your database may differ, 
nevertheless, the library comes bundle with a **optional service** which 
enables resolving domain name without the constant network overhead of 
continuously downloading the remote databases.

The interfaces defined under the `Pdp\Storage` storage namespace enable 
integrating a database managing system and as an implementation example 
a PHP-FIG PSR interfaces powered managing system is provided.

The `Pdp\Storage\PsrStorageFactory` enables returning storage instances that
retrieve, convert and cache the Public Suffix List as well as the IANA Root 
Zone Database using standard interfaces published by the PHP-FIG.

### Instantiate `Pdp\Storage\PsrStorageFactory`

To work as intended, the `Pdp\Storage\PsrStorageFactory` constructor requires:

- a [PSR-16](http://www.php-fig.org/psr/psr-16/) Cache object.
- a [PSR-17](http://www.php-fig.org/psr/psr-17/) HTTP Factory.
- a [PSR-18](http://www.php-fig.org/psr/psr-18/) HTTP Client.

When creating a new storage instance you will require:

- a `$cachePrefix` argument to optionally add a prefix to your cache index, 
default to the empty string;
- a `$ttl` argument if you need to set the default `$ttl`, default to `null` 
to use the underlying caching default TTL;

The `$ttl` argument can be:

- an `int` representing time in second (see PSR-16);
- a `DateInterval` object (see PSR-16);
- a `DateTimeInterface` object representing the date and time when the item 
will expire;

The package does not provide any implementation of such interfaces as you can
find robust and battle tested implementations on packagist.

#### Refreshing the cached PSL and RZD data

**THIS IS THE RECOMMENDED WAY OF USING THE LIBRARY**

For the purpose of this example we will use our PSR powered solution with:
 
- *Guzzle* as our PSR-18 HTTP client;
- *Guzzle\Psr7* to create a PSR-17 compliant `RequestFactoryInterface` object;
- *Symfony Cache Component* as our PSR-16 cache implementation;

We will cache both external sources for 24 hours in a PostgreSQL database.

You are free to use other libraries/solutions as long as they implement the required PSR interfaces. 

~~~php
<?php 

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Pdp\Storage\PsrStorageFactory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Psr16Cache;

$pdo = new PDO(
    'pgsql:host=localhost;port:5432;dbname=testdb', 
    'user', 
    'password', 
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$cache = new Psr16Cache(new PdoAdapter($pdo, 'pdp', 43200));
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

**Be sure to adapt the following code to your own application.
The following code is an example given without warranty of it working 
out of the box.**

**You should use your dependency injection container to avoid repeating this
code in your application.**

### Automatic Updates

It is important to always have an up to date Public Suffix List and Root Zone
Database.  
This library no longer provide an out of the box script to do so as implementing
such a job heavily depends on your application setup.
You can use the above example script as a starting point to implement such a job.

Changelog
-------

Please see [CHANGELOG](CHANGELOG.md) for more information about what has been
changed since version **5.0.0** was released.

Contributing
-------

Contributions are welcome and will be fully credited. Please see 
[CONTRIBUTING](.github/CONTRIBUTING.md) for details.

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

If you discover any security related issues, please email nyamsprod@gmail.com
instead of using the issue tracker.

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
