# PHP Domain Parser Upgrade Guide

## 5.0 to 6.0

In order to take advantage of the new features of PHP, the library dropped the 
support of **all versions before and including PHP 7.3**. The minimum supported PHP 
version is now **PHP 7.4**. 

**Version 6.0 introduces more interfaces and removed the resource manager system.**

### Backward Incompatibility Changes

#### Domain resolution

The `Pdp\Rules::resolve` and `Pdp\TopLevelDomains::resolve` returns a 
`ResolvedDomain` object instead of a `Pdp\Domain` object, thus, the `Domain` 
object no longer exposes the components from resolution, this is done by the 
new `ResolvedDomain` object instead.

**version 5**
~~~php
/** @var Domain $domain */
$domain = $rules->resolve('www.example.com');
$domain->getDomain();            //returns a string or null and was deprecated
$domain->getPublicSuffix();      //returns a string or null
$domain->getSubDomain();         //returns a string or null
$domain->getRegistrableDomain(); //returns a string or null
$domain->isICANN();              //returns a boolean
$domain->isPrivate();            //returns a boolean
$domain->isKnown();              //returns a boolean
~~~ 

The `Domain` **has access** to the domain parts and to the public suffix list state.

**version 6**
~~~php
/** @var ResolvedDomain $domain */
$result = $rules->resolve('www.example.com');
$result->domain();                    //returns a Domain object similar to v5 Domain object
$result->publicSuffix();              //returns a Public Suffix object
$result->publicSuffix()->isICANN();   //returns a boolean
$result->publicSuffix()->isPrivate(); //returns a boolean
$result->publicSuffix()->isKnown();   //returns a boolean
$result->subDomain();                 //returns a Domain object
$result->registrableDomain();         //returns a Domain object
~~~ 

The `Domain` **has no access**  to the domain parts or the public suffix list state.

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

- Domain objects no longer exposes the `__toString` magic methods.
- `Domain::getContent` is renamed `Domain::value`
- The `Domain` constructor is private. To instantiate a domain object you
need to use on of the two named constructor `Domain::fromIDNA2008` or 
`Domain::fromIDNA2008`.

**version 5**
~~~php
$domain = new Domain('www.example.com');
echo $domain;           // display 'www.example.com'
$domain->getContent();  // can be a string or null
~~~ 

**version 6**
~~~php
$domain = Domain::fromIDNA2008('www.example.com');
echo $domain->toString(); // display 'www.example.com'
$domain->value();         // can be a string or null
~~~ 

#### Methods renamed

- `Domain::getLabel` method is renamed `Domain::label`.
- `Rules::createFromPath` method is renamed `Rules::fromPath`.
- `TopLevelDomains::createFromPath` method is renamed `TopLevelDomains::fromPath`.
- `Rules::createFromString` method is renamed `Rules::fromString`.
- `TopLevelDomains::createFromString` method is renamed `TopLevelDomains::fromString`.

#### Classes removed

- `HttpClient` is removed without replacement.
- `Cache` is removed without replacement.
- `Manager` is removed without replacement.
- `Installer` is removed without replacement.
- `Logger` is removed without replacement.
- The CLI script to update the cache is removed without replacement. 

*Please check the [README](README.md) doc for the alternative*

#### Methods removed

- `__debugInfo` is removed from all classes.
- `DomainInterface` is removed use `DomainName` instead or `ResolvedDomainName`. 
- `Domain::isResolvable` is removed without replacement.
- `Domain::resolve` is removed without replacement. 
- `Domain::isTransitionalDifferent` is removed without replacement. 
- `Domain::withAsciiIDNAOption` is removed without replacement.
- `Domain::withUnicodeIDNAOption` is removed without replacement.
- `PublicSuffix::isTransitionalDifferent` is removed without replacement. 
- `PublicSuffix::withAsciiIDNAOption` is removed without replacement.
- `PublicSuffix::withUnicodeIDNAOption` is removed without replacement.
- `PublicSuffix::createFromDomain` is removed without replacement. 
- `Rules::getPublicSuffix` is removed use `ResolvedDomain::getPublicSuffix` instead. 
- IDNA related methods from `Rules` and `TopLevelDomains` are removed.

#### Methods return type changed

- `Domain::jsonSerialize` no longer returns an array but returns the string
representation or `null` to allow better compatibility with URL components
representation in other languages.

#### Objects instantiation

- `Rules::__construct` 
- `TopLevelDomains::__construct` 
- `Domain::__construct` 
- `PublicSuffix::__construct` 

methods are now all private.
