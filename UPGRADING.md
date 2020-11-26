# PHP Domain Parser Upgrade Guide

## 5.0 to 6.0

In order to take advantage of PHP new features, the library dropped the 
support of **all versions before and including PHP 7.3**. The minimum supported
PHP version is now **PHP 7.4**. 

**Version 6.0 no longer provides an out of the box resource manager system.**

### Backward Incompatibility Changes

Domains resolution is done using the `IDNA2008` algorithm by default, in v5, 
by default, it is done `IDNA2003` instead.

**version 5**

~~~php
/** @var Rules $rules */
echo $rules->resolve('faß.de'); // returns 'fass.de'
~~~ 

**version 6**

~~~php
/** @var Rules $rules */
echo $rules->resolve('faß.de')->toString(); // returns 'faß.de'
~~~ 

#### Domain resolution

The `Pdp\Rules::resolve` and `Pdp\TopLevelDomains::resolve` returns a 
`ResolvedDomain` object instead of a `Pdp\Domain` object, thus, the `Domain` 
object no longer exposes the components from resolution, this is done by the 
new `ResolvedDomain` object instead.

**version 5**

~~~php
/** @var Rules $rules */
$domain = $rules->resolve('www.example.com');
$domain->getDomain();            //returns a string or null and was deprecated
$domain->getPublicSuffix();      //returns a string or null
$domain->getSubDomain();         //returns a string or null
$domain->getRegistrableDomain(); //returns a string or null
$domain->isICANN();              //returns a boolean
$domain->isPrivate();            //returns a boolean
$domain->isKnown();              //returns a boolean
~~~ 

**version 6**

~~~php
/** @var Rules $rules */
$result = $rules->resolve('www.example.com');
$result->domain();                    //returns a Domain object similar to v5 Domain object
$result->publicSuffix();              //returns a Public Suffix object
$result->publicSuffix()->isICANN();   //returns a boolean
$result->publicSuffix()->isPrivate(); //returns a boolean
$result->publicSuffix()->isKnown();   //returns a boolean
$result->subDomain();                 //returns a Domain object
$result->registrableDomain();         //returns a ResolvedDomain object
~~~ 

The `Domain` **no longer has access** to component information.

#### Normalizing domain resolution

The `Pdp\Rules::resolve` and `Pdp\TopLevelDomains::resolve` domain resolution
rules are identical. They will alway returns a return even if the domain contains
a syntax error. 

**version 5**

~~~php
/** @var TopLevelDomains $rules */
$result = $rules->resolve('####');
//throws an Exception
~~~ 

**version 6**

~~~php
/** @var TopLevelDomains $rules */
$result = $rules->resolve('####');
//returns a ResolvedDomain object 
~~~ 


#### Strict domain resolution

**version 5**
- `Rules::getCookieDomain` will throw on invalid domain value.
- `Rules::getICANNDomain` will throw on invalid domain value.
- `Rules::getPrivateDomain` will throw on invalid domain value.

**version 6**
- `Rules::getCookieDomain` will throw on invalid domain value.
- `Rules::getICANNDomain` will throw on public suffix not find in the ICANN Section.
- `Rules::getPrivateDomain` will throw on public suffix not find in the Private Section.

#### Domain format

- `Domain::__toString` is removed use `Domain::toString`.
- `Domain::getContent` is renamed `Domain::value`.
- The `Domain` constructor is private. To instantiate a domain object you
need to use on of the two (2) named constructor `Domain::fromIDNA2008` or 
`Domain::fromIDNA2008`.

**version 5**
~~~php
$domain = new Domain('faß.de', null, IDNA_NONTRANSITIONAL_TO_ASCII, IDNA_NONTRANSITIONAL_TO_UNICODE);
$domain->getContent();  // can be a string or null
echo $domain;           // display 'faß.de'
~~~ 

**version 6**
~~~php
$domain = Domain::fromIDNA2008('faß.de');
$domain->value();         // can be a string or null
echo $domain->toString(); // display 'faß.de'
~~~ 

#### Methods renamed

- `Domain::getLabel` method is renamed `Domain::label`.
- `Rules::createFromPath` method is renamed `Rules::fromPath`.
- `TopLevelDomains::createFromPath` method is renamed `TopLevelDomains::fromPath`.
- `Rules::createFromString` method is renamed `Rules::fromString`.
- `TopLevelDomains::createFromString` method is renamed `TopLevelDomains::fromString`.
- `TopLevelDomains::getVersion` method is renamed `TopLevelDomains::version`.
- `TopLevelDomains::getModifiedDate` method is renamed `TopLevelDomains::lastUpdated`.

#### Classes removed

- `HttpClient` is removed without replacement.
- `Cache` is removed without replacement.
- `Manager` is removed without replacement.
- `Installer` is removed without replacement.
- `Logger` is removed without replacement.
- The CLI script to update the cache is removed without replacement. 

*Please check the [README](README.md) doc for the alternative*

#### Methods removed

- `__toString` is removed from all classes.
- `__debugInfo` is removed from all classes.
- `DomainInterface` is removed use `DomainName` or `ResolvedDomainName` instead. 
- `Domain::isResolvable` is removed without replacement.
- `Domain::resolve` is removed without replacement.
- `PublicSuffix::createFromDomain` is removed without replacement. 
- `Rules::getPublicSuffix` is removed use `ResolvedDomain::publicSuffix` instead. 
- All v5 IDNA related methods are removed, IDNA is fully handle within the `Domain` object.

#### Methods return type changed

- `Domain::jsonSerialize` no longer returns an array but returns the string
representation or `null` to allow better compatibility with URL components
representation in other languages.

#### Objects instantiation

- `Rules::__construct` 
- `TopLevelDomains::__construct` 
- `Domain::__construct` 
- `PublicSuffix::__construct`

methods are now all private please use the provided named constructors instead.
