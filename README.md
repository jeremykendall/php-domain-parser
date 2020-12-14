# PHP Domain Parser

**PHP Domain Parser** is a resource based domain parser implemented in PHP.

[![Build Status][ico-github-actions-build]][link-github-actions-build]
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
- accurate IANA Top Level Domain List parsing.

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

This library can resolve a domain against:
 
- The [Public Suffix List](https://publicsuffix.org/)
- The [IANA Top Level Domain List](https://www.iana.org/domains/root/files)

In both cases this is done using the `resolve` method implemented on the resource 
instance. The method returns a `Pdp\ResolvedDomain` object which represents the 
result of that process.

For the [Public Suffix List](http://publicsuffix.org/) you need to use the
`Pdp\Rules` class as shown below:

~~~php
<?php 
use Pdp\Rules;

$publicSuffixList = Rules::fromPath('/path/to/cache/public-suffix-list.dat');

$result = $publicSuffixList->resolve('www.PreF.OkiNawA.jP');
echo $result->domain()->toString();            //display 'www.pref.okinawa.jp';
echo $result->subDomain()->toString();         //display 'www';
echo $result->secondLevelDomain()->toString(); //display 'pref';
echo $result->registrableDomain()->toString(); //display 'pref.okinawa.jp';
echo $result->suffix()->toString();            //display 'okinawa.jp';
$result->suffix()->isICANN();                  //return true;
~~~

For the [IANA Top Level Domain List](https://www.iana.org/domains/root/files),
the `Pdp\TopLevelDomains` class is use instead:

~~~php
use Pdp\TopLevelDomains;

$topLevelDomains = TopLevelDomains::fromPath('/path/to/cache/tlds-alpha-by-domain.txt');

$result = $topLevelDomains->resolve('www.PreF.OkiNawA.jP');
echo $result->domain()->toString();            //display 'www.pref.okinawa.jp';
echo $result->suffix()->toString();            //display 'jp';
echo $result->secondLevelDomain()->toString(); //display 'okinawa';
echo $result->registrableDomain()->toString(); //display 'okinawa.jp';
echo $result->subDomain()->toString();         //display 'www.pref';
echo $result->suffix()->isIANA();              //return true
~~~

In case of an error an exception which extends `Pdp\CannotProcessHost` is thrown.

The `resolve` method will always return a `ResolvedDomain` even if the domain
syntax is invalid or if there is no match found in the resource data. 
To work around this limitation, the library exposes more strict methods,
namely:

- `Rules::getCookieDomain`
- `Rules::getICANNDomain`
- `Rules::getPrivateDomain`

for the Public Suffix List and the following method for the Top Level
Domain List:

- `TopLevelDomains::getIANADomain`

These methods resolve the domain against their respective data source using
the same rules as the `resolve` method but will instead throw an exception 
if no valid effective TLD is found or if the submitted domain is invalid.

~~~php
<?php 
use Pdp\Rules;
use Pdp\TopLevelDomains;

$publicSuffixList = Rules::fromPath('/path/to/cache/public-suffix-list.dat');

$publicSuffixList->getICANNDomain('qfdsf.unknownTLD');
// will throw because `.unknownTLD` is not part of the ICANN section

$result = $publicSuffixList->getCookieDomain('qfdsf.unknownTLD');
$result->suffix()->value();   // returns 'unknownTLD'
$result->suffix()->isKnown(); // returns false
// will not throw because the domain syntax is correct.

$publicSuffixList->getCookieDomain('com');
// will not throw because the domain syntax is invalid (ie: does not support public suffix)

$result = $publicSuffixList->resolve('com');
$result->suffix()->value();   // returns null
$result->suffix()->isKnown(); // returns false
// will not throw but its public suffix value equal to NULL

$topLevelDomains = TopLevelDomains::fromPath('/path/to/cache/public-suffix-list.dat');
$topLevelDomains->getIANADomain('com');
// will not throw because the domain syntax is invalid (ie: does not support public suffix)
~~~

To instantiate each domain resolver you can use the following named constructor:

- `fromString`: instantiate the resolver from a inline string representing the data source;
- `fromPath`: instantiate the resolver from a local path or online URL by relying on `fopen`;

**If the instantiation does not work an exception will be thrown.**

**WARNING:**

**You should never resolve domain name this way in production, without, at 
least, a caching mechanism to reduce PSL downloads.**

**Using the Public Suffix List to determine what is a valid domain name and what 
isn't is dangerous, particularly in these days when new gTLDs are arriving at a 
rapid pace.**

**If you are looking to know the validity of a Top Level Domain, the 
IANA Top Level Domain List is the proper source for this information or 
alternatively consider using directly the DNS.** 

**If you must use this library for any of the above purposes, please consider 
integrating an updating mechanism into your software.**

**For more information go to the [Managing external data source section](#managing-the-package-external-resources)** 

### Resolved domain information.

Whichever methods chosen to resolve the domain on success, the package will
return a `Pdp\ResolvedDomain` instance.

The `Pdp\ResolvedDomain` decorates the `Pdp\Domain` class resolved but also 
gives access as separate methods to the domain different components.

~~~php
use Pdp\TopLevelDomains;

/** @var TopLevelDomains $topLevelDomains */
$result = $topLevelDomains->resolve('www.PreF.OkiNawA.jP');
echo $result->domain()->toString();            //display 'www.pref.okinawa.jp';
echo $result->suffix()->toString();            //display 'jp';
echo $result->secondLevelDomain()->toString(); //display 'okinawa';
echo $result->registrableDomain()->toString(); //display 'okinawa.jp';
echo $result->subDomain()->toString();         //display 'www.pref';
echo $result->suffix()->isIANA();              //return true
~~~
 
You can modify the returned `Pdp\ResolvedDomain` instance using the following methods:

~~~php
<?php 

use Pdp\Rules;

/** @var Rules $publicSuffixList */
$result = $publicSuffixList->resolve('shop.example.com');
$altResult = $result
    ->withSubDomain('foo.bar')
    ->withSecondLevelDomain('test')
    ->withSuffix('example');

echo $result->domain()->toString(); //display 'shop.example.com';
$result->suffix()->isKnown();       //return true;

echo $altResult->domain()->toString(); //display 'foo.bar.test.example';
$altResult->suffix()->isKnown();       //return false;
~~~

**TIP: Always favor submitting a `Pdp\Suffix` object rather that any other
supported type to avoid unexpected results. By default, if the input is not a
`Pdp\Suffix` instance, the resulting public suffix will be labelled as
being unknown. For more information go to the [Public Suffix section](#public-suffix)**

### Domain Suffix

The domain effective TLD is represented using the `Pdp\Suffix`. Depending on
the data source the object exposes different information regarding its
origin.

~~~php
<?php 
use Pdp\Rules;

/** @var Rules $publicSuffixList */
$suffix = $publicSuffixList->resolve('example.github.io')->suffix();

echo $suffix->domain()->toString(); //display 'github.io';
$suffix->isICANN();                 //will return false
$suffix->isPrivate();               //will return true
$suffix->isPublicSuffix();          //will return true
$suffix->isIANA();                  //will return false
$suffix->isKnown();                 //will return true
~~~

The public suffix state depends on its origin:
 
- `isKnown` returns `true` if the value is present in the data resource.
- `isIANA` returns `true` if the value is present in the IANA Top Level Domain List.
- `isPublicSuffix` returns `true` if the value is present in the Public Suffix List.
- `isICANN` returns `true` if the value is present in the Public Suffix List ICANN section.
- `isPrivate` returns `true` if the value is present in the Public Suffix List private section.
 
The same information is used when `Pdp\Suffix` object is 
instantiate via its named constructors:
 
 ~~~php
 <?php 
 use Pdp\Suffix;

$iana = Suffix::fromIANA('ac.be');
$icann = Suffix::fromICANN('ac.be');
$private = Suffix::fromPrivate('ac.be');
$unknown = Suffix::fromUnknown('ac.be');
~~~

Using a `Suffix` object instead of a string or `null` with 
`ResolvedDomain::withSuffix` will ensure that the returned value will
always contain the correct information regarding the public suffix resolution.
 
Using a `Domain` object instead of a string or `null` with the named 
constructor ensure a better instantiation of the Public Suffix object for
more information go to the [ASCII and Unicode format section](#ascii-and-unicode-formats) 
 
### Accessing and processing Domain labels

If you are interested into manipulating the domain labels without taking into 
account the Effective TLD, the library provides a `Domain` object tailored for
manipulating domain labels. You can access the object using the following methods:
 
- the `ResolvedDomain::domain` method 
- the `ResolvedDomain::subDomain` method
- the `ResolvedDomain::registrableDomain` method
- the `ResolvedDomain::secondLevelDomain` method
- the `Suffix::domain` method

`Domain` objects usage are explain in the next section.

~~~php
<?php 
use Pdp\Rules;

/** @var Rules $publicSuffixList */
$result = $publicSuffixList->resolve('www.bbc.co.uk');
$domain = $result->domain();
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

$publicSuffixDomain = $result->suffix()->domain();
$publicSuffixDomain->labels(); // returns ['uk', 'co']
~~~ 

You can also add or remove labels according to their key index using the 
following methods:

~~~php
<?php 
use Pdp\Rules;

/** @var Rules $publicSuffixList */
$domain = $publicSuffixList->resolve('www.ExAmpLE.cOM')->domain();

$newDomain = $domain
    ->withLabel(1, 'com')  //replace 'example' by 'com'
    ->withoutLabel(0, -1)  //remove the first and last labels
    ->append('www')
    ->prepend('docs.example');

echo $domain->toString();           //display 'www.example.com'
echo $newDomain->toString();        //display 'docs.example.com.www'
$newDomain->clear()->labels();      //return []
echo $domain->slice(2)->toString(); //display 'www'
~~~

**WARNING: Because of its definition, a domain name can be `null` or a string.**

To distinguish this possibility the object exposes two (2) formatting methods 
`Domain::value` which can be `null` or a `string` and `Domain::toString` which 
will always cast the domain value to a string.

 ~~~php
use Pdp\Domain;
 
$nullDomain = Domain::fromIDNA2008(null);
$nullDomain->value();    // returns null;
$nullDomain->toString(); // returns '';
 
$emptyDomain = Domain::fromIDNA2008('');
$emptyDomain->value();    // returns '';
$emptyDomain->toString(); // returns '';
 ~~~ 

### ASCII and Unicode formats.

Domain names originally only supported ASCII characters. Nowadays,
they can also be presented under a UNICODE representation. The conversion
between both formats is done using the compliant implementation of 
[UTS#46](https://www.unicode.org/reports/tr46/), otherwise known as Unicode 
IDNA Compatibility Processing. Domain objects expose a `toAscii` and a 
`toUnicode` methods which returns a new instance in the converted format.

~~~php
<?php 
use Pdp\Rules;

/** @var Rules $publicSuffixList */
$unicodeDomain = $publicSuffixList->resolve('bébé.be')->domain();
echo $unicodeDomain->toString(); // returns 'bébé.be'

$asciiDomain = $publicSuffixList->resolve('xn--bb-bjab.be')->domain();
echo $asciiDomain->toString();  // returns 'xn--bb-bjab.be'

$asciiDomain->toUnicode()->toString() === $unicodeDomain->toString(); //returns true
$unicodeDomain->toAscii()->toString() === $asciiDomain->toString();   //returns true
~~~

By default, the library uses IDNA2008 algorithm to convert domain name between 
both formats. It is still possible to use the legacy conversion algorithm known
as IDNA2003.

Since direct conversion between both algorithms is not possible you need 
to explicitly specific on construction which algorithm you will use
when creating a new domain instance via the `Pdp\Domain` object. This 
is done via two (2) named constructors:

- `Pdp\Domain::fromIDNA2008`
- `Pdp\Domain::fromIDNA2003`

At any given moment the `Pdp\Domain` instance can tell you whether it is in 
`ASCII` mode or not.

**Once instantiated there's no way to tell which algorithm is used to convert
the object from ascii to unicode and vice-versa**

~~~php
use Pdp\Domain;

$domain = Domain::fromIDNA2008('faß.de');
echo $domain->value(); // display 'faß.de'
$domain->isAscii();    // return false

$asciiDomain = $domain->toAscii(); 
echo $asciiDomain->value(); // display 'xn--fa-hia.de'
$asciiDomain->isAscii();    // returns true

$domain = Domain::fromIDNA2003('faß.de');
echo $domain->value(); // display 'fass.de'
$domain->isAscii();    // returns true

$asciiDomain = $domain->toAscii();
echo $asciiDomain->value(); // display 'fass.de'
$asciiDomain->isAscii();    // returns true
~~~

**TIP: Always favor submitting a `Pdp\Domain` object for resolution rather that a 
string or an object that can be cast to a string to avoid unexpected format 
conversion errors/results. By default, and with lack of information conversion
is done using IDNA 2008 rules.**

### Managing the package external resources

Depending on your application, the mechanism to store your resources may differ, 
nevertheless, the library comes bundle with a **optional service** which 
enables resolving domain name without the constant network overhead of 
continuously downloading the remote databases.

The interfaces and classes defined under the `Pdp\Storage` namespace enable 
integrating a resource managing system and provide an implementation example 
using PHP-FIG PSR interfaces.

#### Using PHP-FIG interfaces

The `Pdp\Storage\PsrStorageFactory` enables returning storage instances that
retrieve, convert and cache the Public Suffix List and the IANA Top Level 
Domain List using standard interfaces published by the PHP-FIG.

To work as intended, the `Pdp\Storage\PsrStorageFactory` constructor requires:

- a [PSR-16](http://www.php-fig.org/psr/psr-16/) Simple Cache implementing library.
- a [PSR-17](http://www.php-fig.org/psr/psr-17/) HTTP Factory implementing library.
- a [PSR-18](http://www.php-fig.org/psr/psr-18/) HTTP Client implementing library.

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
find [robust](https://packagist.org/providers/psr/simple-cache-implementation) 
and [battle tested](https://packagist.org/providers/psr/http-client-implementation) 
[implementations](https://packagist.org/providers/psr/http-factory-implementation) 
on packagist.

#### Refreshing the resource using the provided factories

**THIS IS THE RECOMMENDED WAY OF USING THE LIBRARY**

For the purpose of this example we will use our PSR powered solution with:
 
- *Guzzle HTTP Client* as our PSR-18 HTTP client;
- *Guzzle PSR-7 package* which provide factories to create a PSR-7 objects using PSR-17 interfaces;
- *Symfony Cache Component* as our PSR-16 cache implementation provider;

We will cache both external sources for 24 hours in a PostgreSQL database.

You are free to use other libraries/solutions/settings as long as they 
implement the required PSR interfaces.

~~~php
<?php 

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
$client = new GuzzleHttp\Client();
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
$rzdStorage = $factory->createTopLevelDomainListStorage($cachePrefix, $cacheTtl);

// if you need to force refreshing the rules 
// before calling them (to use in a refresh script)
// uncomment this part or adapt it to you script logic
// $pslStorage->delete(PsrStorageFactory::PUBLIC_SUFFIX_LIST_URI);
$publicSuffixList = $pslStorage->get(PsrStorageFactory::PUBLIC_SUFFIX_LIST_URI);

// if you need to force refreshing the rules 
// before calling them (to use in a refresh script)
// uncomment this part or adapt it to you script logic
// $rzdStorage->delete(PsrStorageFactory::TOP_LEVEL_DOMAIN_LIST_URI);
$topLevelDomains = $rzdStorage->get(PsrStorageFactory::TOP_LEVEL_DOMAIN_LIST_URI);
~~~

**Be sure to adapt the following code to your own application.
The following code is an example given without warranty of it working 
out of the box.**

**You should use your dependency injection container to avoid repeating this
code in your application.**

### Automatic Updates

It is important to always have an up to date Public Suffix List and Top Level
Domain List.  
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
- a code analysis compliance test suite using [PHPStan](https://phpstan.org).
- a code analysis compliance test suite using [Psalm](https://psalm.dev).
- a coding style compliance test suite using [PHP CS Fixer](https://cs.symfony.com).

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

[ico-github-actions-build]: https://img.shields.io/github/workflow/status/jeremykendall/php-domain-parser/Build?style=flat-square
[ico-packagist]: https://img.shields.io/packagist/dt/jeremykendall/php-domain-parser.svg?style=flat-square
[ico-release]: https://img.shields.io/github/release/jeremykendall/php-domain-parser.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square

[link-github-actions-build]: https://github.com/jeremykendall/php-domain-parser/actions?query=workflow%3ABuild
[link-packagist]: https://packagist.org/packages/jeremykendall/php-domain-parser
[link-release]: https://github.com/jeremykendall/php-domain-parser/releases
[link-license]: https://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE
