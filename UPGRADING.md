# PHP Domain Parser Upgrade Guide

This guide will help you migrate from a 5.x version to 6.0.0. It will only 
explain backward compatibility breaks, it will not present the new features
([read the documentation](README.md) for that).

## 5.0 to 6.0

In order to take advantage of PHP new features, the library dropped the 
support of **all versions before and including PHP 7.3**. The minimum supported
PHP version is now **PHP 7.4**. 

**Version 6.0 no longer provides an out of the box resource manager system.**

### Backward Incompatibility Changes

Domains resolution uses the `IDNA2008` algorithm by default, in v5, 
by default, it is done `IDNA2003` instead.

```diff
<?php
/** @var Rules $rules */
- echo $rules->resolve('faß.de')->__toString(); //returns 'fass.de'
+ echo $rules->resolve('faß.de')->toString();   //returns 'faß.de'
```

#### Domain resolution

The `Pdp\Rules::resolve` and `Pdp\TopLevelDomains::resolve` returns a 
`ResolvedDomain` object instead of a `Pdp\Domain` object, thus, the `Domain` 
object no longer exposes the components from resolution, this is done by the 
new `ResolvedDomain` object instead.

```diff
<?php
/** @var Rules $rules */
- $rules->resolve('faß.de')->labels();           //returns ['de', 'fass']
+ $rules->resolve('faß.de')->domain()->labels(); //returns [ 'de', 'faß']
```

Public suffix properties are not longer **directly** accessible on the
returned object.

```diff
<?php
/** @var Rules $rules */
- $rules->resolve('faß.de')->isICANN();           //returns true
+ $rules->resolve('faß.de')->suffix()->isICANN(); //returns true
```

```diff
<?php
/** @var Rules $rules */
- $rules->resolve('www.example.org')->registrableDomain();             //returns 'example.org'
+ $rules->resolve('www.example.org')->registrableDomain()->toString(); //returns 'example.org'
```

Domain components are converted to objects.

The `Domain` **no longer has access** to component information.

#### Normalizing domain resolution

The `Pdp\Rules::resolve` and `Pdp\TopLevelDomains::resolve` domain resolution
rules are identical. They will alway returns a return even if the domain contains
a syntax error. 

**version 5**

```diff
<?php
/** @var TopLevelDomains $rules */
- $result = $rules->resolve('####'); //throws an Exception
+ $result = $rules->resolve('####'); //returns a ResolvedDomain object 
```

#### Strict domain resolution

**version 5**
- `Rules::getCookieDomain` will throw on invalid domain value.
- `Rules::getICANNDomain` will throw on invalid domain value.
- `Rules::getPrivateDomain` will throw on invalid domain value.

**version 6**
- `Rules::getCookieDomain` will throw on invalid domain value.
- `Rules::getICANNDomain` will throw on invalid domain value **and** on public suffix not find in the ICANN Section.
- `Rules::getPrivateDomain` will throw on invalid domain value **and** on public suffix not find in the Private Section.

#### Domain format

- The `Domain::__toString` is removed use `Domain::toString` instead.
- The `Domain::getContent` is removed use `Domain::value` instead.
- The `Domain::getLabel` is removed use `Domain::label` instead.
- The `Domain` constructor is private. To instantiate a domain object you
need to use on of the two (2) named constructor `Domain::fromIDNA2008` or 
`Domain::fromIDNA2008`.

```diff
<?php
- $domain = new Domain('faß.de', null, IDNA_NONTRANSITIONAL_TO_ASCII, IDNA_NONTRANSITIONAL_TO_UNICODE);
+ $domain = Domain::fromIDNA2008('faß.de');
- $domain->getContent();    // can be a string or null
+ $domain->value();         // can be a string or null
- echo $domain;             // display 'faß.de'
+ echo $domain->toString(); // display 'faß.de'
- $domain->getLabel(-1);    // returns 'faß'
+ $domain->label(-1);       // returns 'faß'
```

#### Methods renamed

The `create` prefix is removed from all named constructors.

- `Rules::createFromPath` method is renamed `Rules::fromPath`.
- `TopLevelDomains::createFromPath` method is renamed `TopLevelDomains::fromPath`.
- `Rules::createFromString` method is renamed `Rules::fromString`.
- `TopLevelDomains::createFromString` method is renamed `TopLevelDomains::fromString`.
- `TopLevelDomains::getVersion` method is renamed `TopLevelDomains::version`.
- `TopLevelDomains::getModifiedDate` method is renamed `TopLevelDomains::lastUpdated`.

#### Resource manager system

The resource manager system is removed.

- `HttpClient` is removed without replacement.
- `Cache` is removed without replacement.
- `Manager` is removed without replacement.
- `Installer` is removed without replacement.
- `Logger` is removed without replacement.
- The CLI script to update the cache is removed without replacement. 

*Please check the [README](README.md) documentation for alternatives*

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

```diff
<?php
/** @var Rules $rules */
- $result = $rules->resolve('www.example.com'); 
- json_encode($result);           // returns {"domain":"www.example.com","registrableDomain":"example.com","subDomain":"www","publicSuffix":"com","isKnown":true,"isICANN":true,"isPrivate":false}
+ json_encode($result);           // returns '"www.example.com"'
+ echo json_encode([
    'domain' => $result->value(),
    'registrableDomain' => $result->registrableDomain()->value(),
    'subDomain' => $result->subDomain()->value(),
    'publicSuffix' => $result->suffix()->value(),
    'isKnown' => $result->suffix()->isKnown(),
    'isICANN' => $result->suffix()->isICANN(),
    'isPrivate' => $result->suffix()->isPrivate(),
]);
// to get the v5 result
```

#### Objects instantiation

- `Rules::__construct` 
- `TopLevelDomains::__construct` 
- `Domain::__construct` 
- `PublicSuffix::__construct`

methods are now all private please use the provided named constructors instead.
