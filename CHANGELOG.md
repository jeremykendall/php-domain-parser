# Changelog

All Notable changes to `PHP Domain Parser` will be documented in this file

## Next - TBD

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