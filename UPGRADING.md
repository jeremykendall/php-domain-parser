# PHP Domain Parser Upgrade Guide

This guide will help you migrate from a 5.x version to 6.0.0. It will only 
explain backward compatibility breaks, it will not present the new features
([read the documentation](README.md) for that).

## 5.0 to 6.0

In order to take advantage of PHP new features, the library dropped the 
support of **all versions before and including PHP 7.3**. The minimum supported
PHP version is now **PHP 7.4**. 

**Version 6.0 no longer provides an out-of-the-box resource manager system.**

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
+ $rules->resolve('faß.de')->domain()->labels(); //returns ['de', 'faß']
```

Public suffix properties are not longer **directly** accessible on the
returned object.

```diff
<?php
/** @var Rules $rules */
- $rules->resolve('faß.de')->isICANN();           //returns true
+ $rules->resolve('faß.de')->suffix()->isICANN(); //returns true
```

Domain components are objects and no longer nullable scalar type.

```diff
<?php
/** @var Rules $rules */
- $rules->resolve('www.example.org')->registrableDomain();             //returns 'example.org'
+ $rules->resolve('www.example.org')->registrableDomain()->toString(); //returns 'example.org'
```

The `Domain` **no longer has access** to component information. Updating
the resolved component is done on the `ResolvedDomain` and no longer on the
`Domain` object.

```diff
<?php
/** @var Rules $rules */
- echo $rules->resolve('www.example.org')->withPublicSuffix('com');       //returns 'example.com'
+ echo $rules->resolve('www.example.org')->withSuffix('com')->toString(); //returns 'example.com'
```

The `Pdp\PublicSuffix` object is replaced by the more generic `Pdp\Suffix` object

```diff
<?php
/** @var Rules $rules */
- echo $rules->getPublicSuffix('www.example.org'); //returns 'Pdp\PublicSuffix' instance
+ echo $rules->resolve('www.example.org')->suffix(); //returns 'Pdp\Suffix' instance
```

The `Pdp\Suffix` class **no longer has direct access** to the underlying domain properties.

```diff
<?php
- $suffix = new PublicSuffix('co.uk', self::ICANN_DOMAINS);
- $suffix->getLabel(-1); //returns 'co';
+ $suffix = Suffix::fromICANN('co.uk');
+ $suffix->domain()->label(-1); //returns 'co';
```

#### Normalizing domain resolution

The `Pdp\Rules::resolve` and `Pdp\TopLevelDomains::resolve` domain resolution
rules are identical. They will always return a result even if the domain 
contains a syntax error. 

```diff
<?php
/** @var TopLevelDomains $topLevelDomain */
- $result = $topLevelDomain->resolve('####'); //throws an Exception
+ $result = $topLevelDomain->resolve('####'); //returns a ResolvedDomain object 
```

#### Strict domain resolution

Domain resolution is stricter with getter methods in version 6. If no
valid resolution is possible, because of the domain syntax or because
it is not possible in the given section. The method will throw instead 
of returning a response object.

```diff
<?php
/** @var Pdp\Rules $rules */
- $rules->getICANNDomain('toto.foobar')->isICANN();   //returns false
- $rules->getPrivateDomain('ulb.ac.be')->isPrivate(); //returns false
+ $rules->getICANNDomain('toto.foobar'); //will throw an exception 
+ $rules->getPrivateDomain('ulb.ac.be'); //will throw an exception 
```

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
- $domain->getContent();    // can be a string or null
- echo $domain;             // display 'faß.de'
- $domain->getLabel(-1);    // returns 'faß'
+ $domain = Domain::fromIDNA2008('faß.de');
+ $domain->value();         // can be a string or null
+ echo $domain->toString(); // display 'faß.de'
+ $domain->label(-1);       // returns 'faß'
```

#### Methods renamed

- The `create` prefix is removed from all named constructors.
- The `get` prefix is removed from `TopLevelDomains` methods.

```diff
<?php
use Pdp\Rules;
use Pdp\TopLevelDomains;

- $publicSuffixList = Rules::createFromPath('path/to/public-suffix-data.dat');
- $topLevelDomains = TopLevelDomains::createFromString($rootZoneInlineContent);
- $topLevelDomains->getVersion();      //returns 2018082200
- $topLevelDomains->getModifiedDate(); //returns \DateTimeImmutable object
+ $publicSuffixList = Rules::fromPath('path/to/public-suffix-data.dat');
+ $topLevelDomains = TopLevelDomains::fromString($rootZoneInlineContent);
+ $topLevelDomains->version();     //returns 2018082200
+ $topLevelDomains->lastUpdated(); //returns \DateTimeImmutable object
```

#### Resource manager system

The resource manager system (containing caching and refreshing resource) is removed.

- `HttpClient` is removed without replacement.
- `Cache` is removed without replacement.
- `Installer` is removed without replacement.
- `Logger` is removed without replacement.
- The CLI script to update the cache is removed without replacement. 
- `Manager` is removed and may be replace by the use of `Pdp\Storage\PsrStorageFactory`.

*Please check the [README](README.md) documentation for more details*

#### Methods removed

- `__toString` is removed from all classes.
- `__debugInfo` is removed from all classes.
- `DomainInterface` is removed use `DomainName` or `ResolvedDomainName` instead. 
- `Domain::isResolvable` is removed without replacement.
- `Domain::resolve` is removed without replacement.
- `Rules::getPublicSuffix` is removed use `ResolvedDomain::suffix` instead. 
- All v5 IDNA related methods are removed, IDNA is fully handle within the `Domain` object.

#### Methods return type changed

- `Domain::jsonSerialize` no longer returns an array but returns the string
representation or `null` to allow better compatibility with URL components
representation in other languages.

```diff
<?php
/** @var Rules $rules */
- $result = $rules->resolve('www.example.com'); 
- json_encode($result); // returns {
-     "domain":"www.example.com",
-     "registrableDomain":"example.com",
-     "subDomain":"www",
-     "publicSuffix":"com",
-     "isKnown":true,
-     "isICANN":true,
-     "isPrivate":false
-     }
+ json_encode($result); // returns '"www.example.com"'
+ echo json_encode([
+    'domain' => $result->value(),
+    'registrableDomain' => $result->registrableDomain()->value(),
+    'subDomain' => $result->subDomain()->value(),
+    'publicSuffix' => $result->suffix()->value(),
+    'isKnown' => $result->suffix()->isKnown(),
+    'isICANN' => $result->suffix()->isICANN(),
+    'isPrivate' => $result->suffix()->isPrivate(),
+ ]); // to get the v5 result
```

#### Objects instantiation

- `Rules::__construct` 
- `TopLevelDomains::__construct` 
- `Domain::__construct` 

methods are now all private please use the provided named constructors instead.
