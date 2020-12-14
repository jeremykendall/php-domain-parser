# Changelog

All Notable changes to `PHP Domain Parser` starting from the **5.x** series will be documented in this file

## Next - TBD

### Added

- `TimeToLive::fromNow`
- `TimeToLive::fromDurationString`

### Fixed

- `.gitattributes` files to be filter out.

### Deprecated

- `TimeToLive::fromDateTimeInterface` use `TimeToLive::fromNow`
- `TimeToLive::fromScalar` use `TimeToLive::fromDurationString`
- `TimeToLive::convert` without replacement

### Removed

- None

## 6.0.0 - 2020-12-13

### Added

- Adding proper Interfaces
- Added `Domain::clear` to easily initialize an empty domain object
- Added `Domain::slice` to easily slice a domain object
- Added `ResolvedDomain` object to normalize Resolver results.
- Added `Suffix` object to replace the `PublicSuffix` object from v5.
- Public Suffix List and IANA Top Level Domain List are fully decoupled
- Added `Idna`, IDN support has been completely revamped
- Added internal `Stream` class, to improve path/stream resolution
- Resolver uses by default UTS#46 IDNA2008 algorithm to convert domain names
- Storage capability is now optional and can be based on PHP-FIG related interfaces to improve interoperability
- `Pdp\TopLevelDomains::getIANADomain` which throws on syntax error and if no effective TLD is found (behave like `Pdp\TopLevelDomains::resolve` in v5).

### Fixed

- The `Pdp\Domain` class not longer directly exposes Effective TLD status.
- Effective TLD resolver (`Pdp\Rules::resolve` and `Pdp\TopLevelDomains::resolve`) no longer accept IDNA options.
- `Rules::getICANNDomain`, `Rules::getPrivateDomain` will throw even if a `PublicSuffix` is found but does not belong to the correct PSL section.
- `Pdp\TopLevelDomains::resolve` acts like `Pdp\Rules::resolve` and only throw on `TypeError`

### Deprecated

- None

### Removed

- `__toString` and `__debugInfo` usage
- Support for PHP7.4-
- Composer script for automatic updates of the remote databases
- CLI command `bin/update-psl`
- `Pdp\Cache`, `Pdp\CacheException`: The package PSR-16 Cache implementation using the underlying filesystem.
- `Pdp\HttpClient`, `Pdp\CurlHttpClient` and `Pdp\HttpClientException`: The package Http Client.
- `Pdp\Installer`, `Pdp\Manager`: The package source manager and installer
- `Pdp\Logger`, The package logger implementation
- `Pdp\Rules::ALL_DOMAINS` constant deprecated in version 5.3
- `Pdp\Domain::getDomain` deprecated in version 5.3
- `Pdp\Domain::resolve`
- `Pdp\Domain::getPublicSuffix` replaced by `Pdp\ResolvedDomain::suffix`
- `Pdp\Domain::getRegistrableDomain` replaced by `Pdp\ResolvedDomain::registrableDomain`
- `Pdp\Domain::getSubDomain` replaced by `Pdp\ResolvedDomain::subDomain`
- `Pdp\Domain::withPublicSuffix` replaced by `Pdp\ResolvedDomain::withSuffix`
- `Pdp\Domain::getLabel` replaced by `Pdp\Domain::label`
- `Pdp\Domain::isTransitionalDifferent` replaced by `Pdp\IdnaInfo::isTransitionalDifferent`
- `Pdp\PublicSuffix` replaced by `Pdp\Suffix`
- Accessing suffix information from the `Pdp\Domain` object is no longer possible you need to do it from `Pdp\Suffix`
- `Pdp\TopLevelDomains::contains` without replacement
- Internal Converter classes (implementation details are no longer exposed).

## 5.7.2 - 2020-10-25

### Added

- None

### Fixed

- Added support for PHP8 see [#289](https://github.com/jeremykendall/php-domain-parser/pull/289) based on works by [@szepeviktor](https://github.com/szepeviktor)

### Deprecated

- None

### Remove

- None

## 5.7.1 - 2020-08-24

### Added

- None

### Fixed

- Cache throws when cache directory doesn't exist [#280](https://github.com/jeremykendall/php-domain-parser/issues/280)

### Deprecated

- None

### Removed

- None

## 5.7.0 - 2020-08-02

### Added

- `Rules::getCookieDomain`
- `Rules::getICANNDomain`
- `Rules::getPrivateDomain`
- `CouldNotResolvePublicSuffix::dueToUnresolvableDomain`

### Fixed

- Improve type hinting and return type by dropping EOL PHP versions support.
- Improve development environment by dropping EOL PHP versions support.
- Composer script

### Deprecated

- None

### Removed

- Support for PHP7.0 and PHP7.1
- The external data from IANA and mozilla is no longer part of the package and will be downloaded only on demand on composer update/install.

## 5.6.0 - 2019-12-29

### Added

- A simple `Psr3` compatible logger class which output the logs to you cli console.

### Fixed

- `composer.json` updated to be composer 2.0 ready
- package bundle installer is rewritten to improve its usage see [#249](https://github.com/jeremykendall/php-domain-parser/issues/249) and [#250](https://github.com/jeremykendall/php-domain-parser/issues/250)

### Deprecated

- None

### Removed

- None

## 5.5.0 - 2019-04-14

### Added

- Support for IDNA options see [#236](https://github.com/jeremykendall/php-domain-parser/pull/236) thanks to [Insolita](https://github.com/Insolita). 

- `PublicSuffix::labels` and `Domain::labels` to return the VO labels see [#241](https://github.com/jeremykendall/php-domain-parser/pull/241)

- `IDNAConverterTrait::parse` (internal)

### Fixed

- Don't swallow cache errors [#232](https://github.com/jeremykendall/php-domain-parser/issues/232)
- Update travis settings to allow testing against future version of PHP.

### Deprecated

- `IDNAConverterTrait::setLabels` replaced by `IDNAConverterTrait::parse` (internal)

### Removed

- None

## 5.4.0 - 2018-11-22

### Added

- `Pdp\TopLevelDomains` to allow resolving domain againts IANA Root zone database
- `Pdp\TLDConverter` converts the IANA Root Zones database into an associative array
- `Pdp\Manager::getTLDs` a service to return a cache version of the IANA Root zone database
- `Pdp\Manager::refreshTLDs` a service to refresh the cache version of the IANA Root zone database
-  added a new `$ttl` parameter to improve PSR-16 supports to
	- `Pdp\Manager::__construct`
	- `Pdp\Manager::getRules`
	- `Pdp\Manager::refreshRules`
- `Pdp\Exception\CouldNotLoadTLDs` exception

### Fixed

- `Pdp\IDNAConverterTrait::setLabels` improve IDN domain handling
- `Pdp\IDNAConverterTrait` throws a `UnexpectedValueException` if the Intl extension is misconfigured see [#230](https://github.com/jeremykendall/php-domain-parser/issues/230)

### Deprecated

- None

### Removed

- None

## 5.3.0 - 2018-05-22

### Added

- `Pdp\PublicSuffixListSection` interface implemented by `Pdp\Rules` and `Pdp\PublicSuffix`
- `Pdp\DomainInterface` interface implemented by `Pdp\Domain` and `Pdp\PublicSuffix`
- `Pdp\Domain::getContent` replaces `Pdp\Domain::getDomain`
- `Pdp\Domain::withLabel` adds a new label to the `Pdp\Domain`.
- `Pdp\Domain::withoutLabel` removes labels from the `Pdp\Domain`.
- `Pdp\Domain::withPublicSuffix` updates the `Pdp\Domain` public suffix part.
- `Pdp\Domain::withSubDomain` updates the `Pdp\Domain` sub domain part.
- `Pdp\Domain::append` appends a label to `Pdp\Domain`.
- `Pdp\Domain::prepend` prepends a label to `Pdp\Domain`.
- `Pdp\Domain::resolve` attach a public suffix to the `Pdp\Domain`.
- `Pdp\Domain::isResolvable` tells whether the current `Pdp\Domain` can have a public suffix attached to it or not.
- `Pdp\PublicSuffix::createFromDomain` returns a new `Pdp\PublicSuffix` object from a `Pdp\Domain`object
- `Pdp\Exception` sub namespace to organize exception. All exception extends the `Pdp\Exception` class to prevent BC break.

### Fixed

- `Pdp\Domain` domain part computation (public suffix, registrable domain and sub domain)
- `Pdp\Domain` and `Pdp\PublicSuffix` host validation compliance to RFC improved
- Improve `Pdp\Converter` and `Pdp\Manager` class to better report error on IDN conversion.
- Improve `Pdp\Installer` vendor directory resolution see [PR #222](https://github.com/jeremykendall/php-domain-parser/pull/222)
- `Pdp\Exception` nows extends `InvalidArgumentException` instead of `RuntimeException`

### Deprecated

- `Pdp\Domain::getDomain` use instead `Pdp\Domain::getContent`
- `Pdp\Rules::ALL_DOMAINS` use the empty string instead

### Removed

- None

## 5.2.0 - 2018-02-23

### Added

- `Pdp\Rules::getPublicSuffix` returns a `Pdp\PublicSuffix` value object
- `Pdp\Rules::__set_state` is implemented
- `Pdp\Domain::toUnicode` returns a `Pdp\Domain` with its value converted to its Unicode form
- `Pdp\Domain::toAscii` returns a `Pdp\Domain` with its value converted to its AScii form
- `Pdp\PublicSuffix::toUnicode` returns a `Pdp\PublicSuffix` with its value converted to its Unicode form
- `Pdp\PublicSuffix::toAscii` returns a `Pdp\PublicSuffix` with its value converted to its AScii form

### Fixed

- `Pdp\Domain::getDomain` returns the normalized form of the domain name
- `Pdp\PublicSuffix` is no longer internal.
- Normalizes IDN conversion using a internal `IDNConverterTrait`
- Internal code improved by requiring PHPStan for development

### Deprecated

- None

### Removed

- None

## 5.1.0 - 2017-12-18

### Added

- `Pdp\Rules::createFromPath` named constructor to returns a new instance from a path
- `Pdp\Rules::createFromString` named constructor to returns a new instance from a string

### Fixed

- None

### Deprecated

- None

### Removed

- None

## 5.0.0 - 2017-12-13

### Added

- `Pdp\Exception` a base exception for the library
- `Pdp\Rules` a class to resolve domain name against the public suffix list
- `Pdp\Domain` an immutable value object to represents a parsed domain name
- `Pdp\Installer` a class to enable improve PSL maintenance
- `Pdp\Cache` a PSR-16 file cache implementation to cache a local copy of the PSL
- `Pdp\Manager` a class to enable managing PSL sources and `Rules` objects creation
- `Pdp\Converter` a class to convert the PSL into a PHP array

### Fixed

- invalid domain names improved supported
- idn_* conversion error better handled
- domain name with RFC3986 encoded string improved supported

### Deprecated

- None

### Removed

- PHP5 support
- URL Parsing capabilities and domain name validation
- `Pdp\PublicSuffixList` class replaced by the `Pdp\Rules` class
- `Pdp\PublicSuffixManager` class replaced by the `Pdp\Manager` class
- `Pdp\HttpAdapter\HttpAdapterInterface` interface replaced by the `Pdp\HttpClient` interface
- `Pdp\HttpAdapter\CurlHttpAdapter` class replaced by the `Pdp\CurlHttpClient` class
