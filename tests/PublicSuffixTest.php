<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Pdp\Tests;

use InvalidArgumentException;
use Pdp\InvalidDomain;
use Pdp\PublicSuffix;
use PHPUnit\Framework\TestCase;
use function json_encode;
use const IDNA_NONTRANSITIONAL_TO_ASCII;
use const IDNA_NONTRANSITIONAL_TO_UNICODE;

/**
 * @coversDefaultClass \Pdp\PublicSuffix
 */
class PublicSuffixTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::__set_state
     * @covers ::__toString
     * @covers ::jsonSerialize
     */
    public function testInternalPhpMethod(): void
    {
        $publicSuffix = PublicSuffix::fromICANNSection('ac.be');
        $generatePublicSuffix = eval('return '.var_export($publicSuffix, true).';');
        self::assertEquals($publicSuffix, $generatePublicSuffix);
        self::assertEquals('"ac.be"', json_encode($publicSuffix));
        self::assertSame('ac.be', (string) $publicSuffix);
    }

    /**
     * @covers ::__construct
     * @covers ::parse
     * @covers ::setSection
     * @covers ::getContent
     * @covers ::toUnicode
     */
    public function testPSToUnicodeWithUrlEncode(): void
    {
        self::assertSame('bébe', PublicSuffix::fromUnknownSection('b%C3%A9be')->toUnicode()->getContent());
    }

    /**
     * @covers ::__construct
     * @covers ::parse
     * @covers ::setPublicSuffix
     * @covers ::setSection
     * @covers ::isKnown
     * @covers ::isICANN
     * @covers ::isPrivate
     * @dataProvider PSProvider
     * @param ?string $publicSuffix
     */
    public function testSetSection(?string $publicSuffix, string $section, bool $isKnown, bool $isIcann, bool $isPrivate): void
    {
        if ('' === $section) {
            $ps = PublicSuffix::fromUnknownSection($publicSuffix);
        } elseif ('ICANN_DOMAINS' === $section) {
            $ps = PublicSuffix::fromICANNSection($publicSuffix);
        } elseif ('PRIVATE_DOMAINS' === $section) {
            $ps = PublicSuffix::fromPrivateSection($publicSuffix);
        }

        if (!isset($ps)) {
            throw new InvalidArgumentException('Missing PublicSuffix instance.');
        }

        self::assertSame($isKnown, $ps->isKnown());
        self::assertSame($isIcann, $ps->isICANN());
        self::assertSame($isPrivate, $ps->isPrivate());
    }

    public function PSProvider(): iterable
    {
        return [
            [null, 'ICANN_DOMAINS', false, false, false],
            ['foo', 'ICANN_DOMAINS', true, true, false],
            ['foo', 'PRIVATE_DOMAINS', true, false, true],
        ];
    }

    /**
     * @covers ::__construct
     * @covers ::parse
     * @covers ::setPublicSuffix
     * @dataProvider invalidPublicSuffixProvider
     *
     */
    public function testConstructorThrowsException(string $publicSuffix): void
    {
        self::expectException(InvalidDomain::class);

        PublicSuffix::fromUnknownSection($publicSuffix);
    }

    public function invalidPublicSuffixProvider(): iterable
    {
        return [
            'empty string' => [''],
            'absolute host' => ['foo.'],
        ];
    }

    /**
     * @covers ::__construct
     * @covers ::parse
     * @covers ::idnToAscii
     */
    public function testPSToAsciiThrowsException(): void
    {
        self::expectException(InvalidDomain::class);

        PublicSuffix::fromUnknownSection('a⒈com');
    }

    /**
     * @covers ::toUnicode
     * @covers ::idnToUnicode
     */
    public function testToUnicodeThrowsException(): void
    {
        self::expectException(InvalidDomain::class);

        PublicSuffix::fromUnknownSection('xn--a-ecp.ru')->toUnicode();
    }

    /**
     * @covers ::toAscii
     * @covers ::toUnicode
     * @covers ::idnToAscii
     * @covers ::idnToUnicode
     *
     * @dataProvider conversionReturnsTheSameInstanceProvider
     * @param ?string $publicSuffix
     */
    public function testConversionReturnsTheSameInstance(?string $publicSuffix): void
    {
        $instance = PublicSuffix::fromUnknownSection($publicSuffix);
        self::assertSame($instance->toUnicode(), $instance);
        self::assertSame($instance->toAscii(), $instance);
    }

    public function conversionReturnsTheSameInstanceProvider(): iterable
    {
        return [
            'ascii only domain' => ['ac.be'],
            'null domain' => [null],
        ];
    }

    /**
     * @covers ::toUnicode
     * @covers ::idnToUnicode
     */
    public function testToUnicodeReturnsSameInstance(): void
    {
        $instance = PublicSuffix::fromUnknownSection('食狮.公司.cn');

        self::assertSame($instance->toUnicode(), $instance);
    }

    /**
     * @covers ::count
     * @dataProvider countableProvider
     * @param ?string $domain
     */
    public function testCountable(?string $domain, int $nbLabels, array $labels): void
    {
        $domain = PublicSuffix::fromUnknownSection($domain);

        self::assertCount($nbLabels, $domain);
    }

    public function countableProvider(): iterable
    {
        return [
            'null' => [null, 0, []],
            'simple' => ['foo.bar.baz', 3, ['baz', 'bar', 'foo']],
            'unicode' => ['www.食狮.公司.cn', 4, ['cn', '公司', '食狮', 'www']],
        ];
    }

    /**
     * @covers ::isTransitionalDifferent
     *
     * @dataProvider customIDNAProvider
     */
    public function testResolveWithCustomIDNAOptions(
        string $name,
        string $expectedContent,
        string $expectedAscii,
        string $expectedUnicode
    ): void {
        $publicSuffix = PublicSuffix::fromUnknownSection($name, IDNA_NONTRANSITIONAL_TO_ASCII, IDNA_NONTRANSITIONAL_TO_UNICODE);
        self::assertSame($expectedContent, $publicSuffix->getContent());
        self::assertSame($expectedAscii, $publicSuffix->toAscii()->getContent());
        self::assertSame($expectedUnicode, $publicSuffix->toUnicode()->getContent());
        /** @var PublicSuffix $instance */
        $instance = $publicSuffix->toUnicode();
        self::assertSame(
            [$publicSuffix->getAsciiIDNAOption(), $publicSuffix->getUnicodeIDNAOption()],
            [$instance->getAsciiIDNAOption(), $instance->getUnicodeIDNAOption()]
        );
    }

    public function customIDNAProvider(): iterable
    {
        return [
            'without deviation characters' => [
                'example.com',
                'example.com',
                'example.com',
                'example.com',
            ],
            'without deviation characters with label' => [
                'www.example.com',
                'www.example.com',
                'www.example.com',
                'www.example.com',
            ],
            'with deviation in domain' => [
                'www.faß.de',
                'www.faß.de',
                'www.xn--fa-hia.de',
                'www.faß.de',
            ],
            'with deviation in label' => [
                'faß.test.de',
                'faß.test.de',
                'xn--fa-hia.test.de',
                'faß.test.de',
            ],
        ];
    }

    /**
     * @covers ::isTransitionalDifferent
     *
     * @dataProvider transitionalProvider
     */
    public function testIsTransitionalDifference(PublicSuffix $publicSuffix, bool $expected): void
    {
        self::assertSame($expected, $publicSuffix->isTransitionalDifferent());
    }

    public function transitionalProvider(): iterable
    {
        return [
            'simple' => [PublicSuffix::fromUnknownSection('example.com'), false],
            'idna' => [PublicSuffix::fromUnknownSection('français.fr'), false],
            'in domain' => [PublicSuffix::fromUnknownSection('faß.de'), true],
            'in domain 2' => [PublicSuffix::fromUnknownSection('βόλος.com'), true],
            'in domain 3' => [PublicSuffix::fromUnknownSection('ශ්‍රී.com'), true],
            'in domain 4' => [PublicSuffix::fromUnknownSection('نامه‌ای.com'), true],
            'in label' => [PublicSuffix::fromUnknownSection('faß.test.de'), true],
        ];
    }

    /**
     * @covers ::getAsciiIDNAOption
     * @covers ::getUnicodeIDNAOption
     * @covers ::withAsciiIDNAOption
     * @covers ::withUnicodeIDNAOption
     */
    public function testwithIDNAOptions(): void
    {
        $publicSuffix = PublicSuffix::fromUnknownSection('com');

        self::assertSame($publicSuffix, $publicSuffix->withAsciiIDNAOption(
            $publicSuffix->getAsciiIDNAOption()
        ));

        self::assertNotEquals($publicSuffix, $publicSuffix->withAsciiIDNAOption(
            IDNA_NONTRANSITIONAL_TO_ASCII
        ));

        self::assertSame($publicSuffix, $publicSuffix->withUnicodeIDNAOption(
            $publicSuffix->getUnicodeIDNAOption()
        ));

        self::assertNotEquals($publicSuffix, $publicSuffix->withUnicodeIDNAOption(
            IDNA_NONTRANSITIONAL_TO_UNICODE
        ));
    }
}
