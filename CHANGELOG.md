# Changelog

All Notable changes to `PHP Domain Parser` will be documented in this file

## 4.0.0 - TBD

### Added

- `Pdp\Exception` a base exception for the library
- `Pdp\Rules` a class to resolve domain name against the public suffix list
- `Pdp\Domain` an immutable value object to represents a parsed domain name
- `Pdp\Installer` a class to enable improve PSL maintenance
- `Pdp\Cache` a PSR-16 file cache implementation to cache a local copy of the PSL
- `Pdp\Manager` a class to enable managing PSL sources and `Rules` objects creation
- `Pdp\Converter` a class to convert the PSL into a PHP array

### Fixed

- Domain class with invalid domain names improved supported
- idn_* conversion error better handled

### Deprecated

- None

### Removed

- URL Parsing capabilities API is removed
- `Pdp\PublicSuffixList` class replaced by the `Pdp\Rules` class
- `Pdp\PublicSuffixManager` class replaced by the `Pdp\Manager` class
- `Pdp\HttpAdapter\HttpAdapterInterface` interface replaced by the `Pdp\HttpClient` interface
- `Pdp\HttpAdapter\CurlHttpAdapter` class replaced by the `Pdp\CurlHttpClient` class