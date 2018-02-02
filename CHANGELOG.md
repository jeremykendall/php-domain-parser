# Changelog

All Notable changes to `PHP Domain Parser` **5.x** series will be documented in this file

## Next - TBD

### Added

- `Pdp\Rules::supports` returns a boolean to indicates if a given section is supported
- `Pdp\Rules::getPublicSuffix` returns a `Pdp\PublicSuffix` value object
- `Pdp\Rules::__set_state` is implemented
- `Pdp\Domain::getSection` returns a string containing the section name used to determine the public suffix
- `Pdp\Domain::toUnicode` returns a `Pdp\Domain` with its value converted to its Unicode form
- `Pdp\Domain::toAscii` returns a `Pdp\Domain` with its value converted to its AScii form
- `Pdp\PublicSuffix::getSection` returns a string containing the section name used to determine the public suffix
- `Pdp\PublicSuffix::toUnicode` returns a `Pdp\PublicSuffix` with its value converted to its Unicode form
- `Pdp\PublicSuffix::toAscii` returns a `Pdp\PublicSuffix` with its value converted to its AScii form

### Fixed

- `Pdp\Domain::getDomain` returns the normalized form of the domain name
- `Pdp\PublicSuffix` is no longer internal.

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