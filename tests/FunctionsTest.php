<?php

declare(strict_types=1);

namespace Pdp\Tests;

use Pdp;
use Pdp\Domain;
use Pdp\DomainInterface;
use Pdp\Exception;
use Pdp\PublicSuffix;
use Pdp\Rules;
use PHPUnit\Framework\TestCase;

class FunctionsTest extends TestCase
{
    /**
     * @dataProvider prependProvider
     * @covers \Pdp\prepend
     * @covers \Pdp\PublicSuffix::createFromDomain
     *
     * @param mixed       $domain
     * @param mixed       $host
     * @param null|string $expected
     * @param bool        $isKnown
     * @param bool        $isIcann
     * @param bool        $isPrivate
     */
    public function testPrepend(
        $domain,
        $host,
        $expected,
        bool $isKnown,
        bool $isIcann,
        bool $isPrivate
    ) {
        $result = Pdp\prepend($host, $domain);
        $this->assertSame($expected, $result->getContent());
        $this->assertSame($isKnown, $result->isKnown());
        $this->assertSame($isIcann, $result->isICANN());
        $this->assertSame($isPrivate, $result->isPrivate());
    }

    public function prependProvider()
    {
        return [
            'simple prepend' => [
                'domain' => 'example.com',
                'host' => 'www',
                'expected' => 'www.example.com',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'prepend with a null domain' => [
                'domain' => 'example.com',
                'host' => null,
                'expected' => 'example.com',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'prepend a null domain' => [
                'domain' => null,
                'host' => 'example.com',
                'expected' => 'example.com',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'prepend does not change PSL info (1)' => [
                'domain' => new Domain('example.com', new PublicSuffix('com', Rules::ICANN_DOMAINS)),
                'host' => new Domain(),
                'expected' => 'example.com',
                'isKnown' => true,
                'isIcann' => true,
                'isPrivate' => false,
            ],
            'prepend does not change PSL info (2)' => [
                'domain' => new Domain('example.com', new PublicSuffix('com', Rules::ICANN_DOMAINS)),
                'host' => new PublicSuffix('bar', Rules::PRIVATE_DOMAINS),
                'expected' => 'bar.example.com',
                'isKnown' => true,
                'isIcann' => true,
                'isPrivate' => false,
            ],
            'prepend convert host format: IDN to ASCII process' => [
                'domain' => 'example.com',
                'host' => '中国',
                'expected' => 'xn--fiqs8s.example.com',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'prepend convert host format: ASCII to IDN process' => [
                'domain' => '食狮.中国',
                'host' => 'xn--fiqs8s',
                'expected' => '中国.食狮.中国',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'test general DomainInterface object' => [
                'domain' => 'example.com',
                'host' => new class() implements DomainInterface {
                    public function getContent()
                    {
                        return 'be';
                    }

                    public function count()
                    {
                        return 1;
                    }

                    public function getIterator()
                    {
                        foreach (['be'] as $label) {
                            yield $label;
                        }
                    }

                    public function toUnicode()
                    {
                        return clone $this;
                    }

                    public function toAscii()
                    {
                        return clone $this;
                    }

                    public function keys(string $label): array
                    {
                        return [];
                    }

                    public function getLabel(int $key)
                    {
                        return null;
                    }
                },
                'expected' => 'be.example.com',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
        ];
    }

    /**
     * @dataProvider appendProvider
     * @covers \Pdp\append
     * @covers \Pdp\PublicSuffix::createFromDomain
     *
     * @param mixed       $domain
     * @param mixed       $host
     * @param null|string $expected
     * @param bool        $isKnown
     * @param bool        $isIcann
     * @param bool        $isPrivate
     */
    public function testAppend(
        $domain,
        $host,
        $expected,
        bool $isKnown,
        bool $isIcann,
        bool $isPrivate
    ) {
        $result = Pdp\append($host, $domain);
        $this->assertSame($expected, $result->getContent());
        $this->assertSame($isKnown, $result->isKnown());
        $this->assertSame($isPrivate, $result->isPrivate());
        $this->assertSame($isIcann, $result->isICANN());
    }

    public function appendProvider()
    {
        return [
            'simple append' => [
                'domain' => 'example.com',
                'host' => 'be',
                'expected' => 'example.com.be',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'append a null domain' => [
                'domain' => null,
                'host' => 'example.com',
                'expected' => 'example.com',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'append with a null domain' => [
                'domain' => 'example.com',
                'host' => null,
                'expected' => 'example.com',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'adding a null public suffix returns the domain without PSL info changed' => [
                'domain' => new Domain('example.com', new PublicSuffix('com', Rules::ICANN_DOMAINS)),
                'host' => new PublicSuffix(),
                'expected' => 'example.com',
                'isKnown' => true,
                'isIcann' => true,
                'isPrivate' => false,
            ],
            'new domain inherit the new PublicSuffix PSL info' => [
                'domain' => new Domain('example.com', new PublicSuffix('com', Rules::ICANN_DOMAINS)),
                'host' => new PublicSuffix('bar', Rules::PRIVATE_DOMAINS),
                'expected' => 'example.com.bar',
                'isKnown' => true,
                'isIcann' => false,
                'isPrivate' => true,
            ],
            'Publix suffix is converted to the domain format: IDN to ASCII process' => [
                'domain' => 'example.com',
                'host' => '中国',
                'expected' => 'example.com.xn--fiqs8s',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'Publix suffix is converted to the domain format: ASCII to IDN process' => [
                'domain' => '食狮.中国',
                'host' => 'xn--fiqs8s',
                'expected' => '食狮.中国.中国',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'test general DomainInterface object' => [
                'domain' => 'example.com',
                'host' => new class() implements DomainInterface {
                    public function getContent()
                    {
                        return 'be';
                    }

                    public function count()
                    {
                        return 1;
                    }

                    public function getIterator()
                    {
                        foreach (['be'] as $label) {
                            yield $label;
                        }
                    }

                    public function toUnicode()
                    {
                        return clone $this;
                    }

                    public function toAscii()
                    {
                        return clone $this;
                    }

                    public function keys(string $label): array
                    {
                        return [];
                    }

                    public function getLabel(int $key)
                    {
                        return null;
                    }
                },
                'expected' => 'example.com.be',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
        ];
    }

    /**
     * @dataProvider publicSuffixReplaceProvider
     * @covers \Pdp\public_suffix_replace
     *
     * @param mixed       $domain
     * @param mixed       $publicSuffix
     * @param null|string $expected
     * @param bool        $isKnown
     * @param bool        $isIcann
     * @param bool        $isPrivate
     */
    public function testPublicSuffixReplace(
        $domain,
        $publicSuffix,
        $expected,
        bool $isKnown,
        bool $isIcann,
        bool $isPrivate
    ) {
        $result = Pdp\public_suffix_replace($publicSuffix, $domain);
        $this->assertSame($expected, $result->getContent());
        $this->assertSame($isKnown, $result->isKnown());
        $this->assertSame($isPrivate, $result->isPrivate());
        $this->assertSame($isIcann, $result->isICANN());
    }

    public function publicSuffixReplaceProvider()
    {
        return [
            'simple replace' => [
                'domain' => 'example.com',
                'host' => 'be',
                'expected' => 'example.be',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'replace with null public suffix returns the domain only' => [
                'domain' => 'example.com',
                'host' => null,
                'expected' => 'example.com',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'Publix suffix is converted to the domain format: IDN to ASCII process' => [
                'domain' => 'example.com',
                'host' => '中国',
                'expected' => 'example.xn--fiqs8s',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'Publix suffix is converted to the domain format: ASCII to IDN process' => [
                'domain' => '食狮.中国',
                'host' => 'xn--fiqs8s',
                'expected' => '食狮.中国',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'new domain inherit the new PublicSuffix PSL info' => [
                'domain' => new Domain('example.com', new PublicSuffix('com', Rules::ICANN_DOMAINS)),
                'host' => new PublicSuffix('bar', Rules::PRIVATE_DOMAINS),
                'expected' => 'example.bar',
                'isKnown' => true,
                'isIcann' => false,
                'isPrivate' => true,
            ],
            'replacing with a null public suffix returns the domain without PSL info' => [
                'domain' => new Domain('example.com', new PublicSuffix('com', Rules::ICANN_DOMAINS)),
                'host' => new PublicSuffix(),
                'expected' => 'example.com',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
        ];
    }

    /**
     * @covers \Pdp\public_suffix_replace
     */
    public function testPublicSuffixReplaceThrowsException()
    {
        $this->expectException(Exception::class);
        Pdp\public_suffix_replace('com', 'localhost');
    }

    /**
     * @dataProvider replaceProvider
     * @covers \Pdp\replace
     * @covers \Pdp\PublicSuffix::createFromDomain
     *
     * @param mixed       $domain
     * @param mixed       $host
     * @param int         $key
     * @param null|string $expected
     * @param bool        $isKnown
     * @param bool        $isIcann
     * @param bool        $isPrivate
     */
    public function testReplace(
        $domain,
        $host,
        $key,
        $expected,
        bool $isKnown,
        bool $isIcann,
        bool $isPrivate
    ) {
        $result = Pdp\replace($key, $host, $domain);
        $this->assertSame($expected, $result->getContent());
        $this->assertSame($isKnown, $result->isKnown());
        $this->assertSame($isIcann, $result->isICANN());
        $this->assertSame($isPrivate, $result->isPrivate());
    }

    public function replaceProvider()
    {
        return [
            'simple replace' => [
                'domain' => 'example.com',
                'host' => 'be',
                'key' => 0,
                'expected' => 'example.be',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'replace with null public suffix (1)' => [
                'domain' => 'example.com',
                'host' => null,
                'key' => 1,
                'expected' => 'example.com',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'replace with null public suffix (2)' => [
                'domain' => new Domain('www.ulb.ac.be', new PublicSuffix('ac.be', Rules::ICANN_DOMAINS)),
                'host' => new Domain('Foo'),
                'key' => -3,
                'expected' => 'www.ulb.foo.be',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'Publix suffix conversion: IDN to ASCII process' => [
                'domain' => 'example.com',
                'host' => '中国',
                'key' => 0,
                'expected' => 'example.xn--fiqs8s',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'Publix suffix conversion: ASCII to IDN process' => [
                'domain' => '食狮.中国',
                'host' => 'xn--fiqs8s',
                'key' => 0,
                'expected' => '食狮.中国',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
            'Domain inherits PublicSuffix PSL info' => [
                'domain' => new Domain('example.com', new PublicSuffix('com', Rules::ICANN_DOMAINS)),
                'host' => new PublicSuffix('bar', Rules::PRIVATE_DOMAINS),
                'key' => 0,
                'expected' => 'example.bar',
                'isKnown' => true,
                'isIcann' => false,
                'isPrivate' => true,
            ],
            'test Domain object' => [
                'domain' => new Domain('example.com', new PublicSuffix('com', Rules::ICANN_DOMAINS)),
                'host' => new Domain('ulb.ac.be', new PublicSuffix('ac.be', Rules::PRIVATE_DOMAINS)),
                'key' => 0,
                'expected' => 'example.ulb.ac.be',
                'isKnown' => true,
                'isIcann' => false,
                'isPrivate' => true,
            ],
            'test general DomainInterface object' => [
                'domain' => new Domain('example.com', new PublicSuffix('com', Rules::ICANN_DOMAINS)),
                'host' => new class() implements DomainInterface {
                    public function getContent()
                    {
                        return 'be';
                    }

                    public function count()
                    {
                        return 1;
                    }

                    public function getIterator()
                    {
                        foreach (['be'] as $label) {
                            yield $label;
                        }
                    }

                    public function toUnicode()
                    {
                        return clone $this;
                    }

                    public function toAscii()
                    {
                        return clone $this;
                    }

                    public function keys(string $label): array
                    {
                        return [];
                    }

                    public function getLabel(int $key)
                    {
                        return null;
                    }
                },
                'key' => 0,
                'expected' => 'example.be',
                'isKnown' => false,
                'isIcann' => false,
                'isPrivate' => false,
            ],
        ];
    }

    /**
     * @covers \Pdp\replace
     */
    public function testReplaceThrowsException()
    {
        $this->expectException(Exception::class);
        Pdp\replace(-23, 'com', 'localhost');
    }
}
