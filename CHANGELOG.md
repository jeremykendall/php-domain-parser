# Changelog

All Notable changes to `PHP Domain Parser` **5.x** series will be documented in this file

## Next - TBD

### Added

- `Pdp\PublicSuffixListSection` interface implemented by `Pdp\Rules` and `Pdp\PublicSuffix`
- `Pdp\DomainInterface` interface implemented by `Pdp\Domain` and `Pdp\PublicSuffix`
- `Pdp\Domain` implements the `Countable` interface.
- `Pdp\Domain::getContent` returns the Domain name value replaces `Pdp\Domain::getDomain`
- `Pdp\Domain::withPublicSuffix` updates the `Pdp\Domain` public suffix part.
- `Pdp\Domain::withSubDomain` updates the `Pdp\Domain` sub domain part.
- `Pdp\Domain::withLabel` adds a new label to the `Pdp\Domain`.
- `Pdp\Domain::withoutLabel` removes a label from the `Pdp\Domain`.
- `Pdp\Domain::resolve` attach a public suffix to the `Pdp\Domain`.
- `Pdp\Domain::isResolvable` tell whether the current `Pdp\Domain` can have a public suffix attached to it or not.
- `Pdp\PublicSuffix::createFromDomain` returns a new `Pdp\PublicSuffix` object from a `Pdp\Domain`object

### Fixed

- `Pdp\Domain` domain part computation (public suffix, registrable domain and sub domain)
- `Pdp\Domain` and `Pdp\PublicSuffix` host validation compliance to RFC improved
- Improved `Pdp\Converter` and `Pdp\Manager` class to better report error on IDN conversion.

### Deprecated

- `Pdp\Domain::getDomain` use instead `Pdp\Domain::getContent`
- `Pdp\PublicSuffixListSection::ALL_DOMAINS` use the empty string instead

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