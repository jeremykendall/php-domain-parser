<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Pdp;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use function json_encode;
use const IDNA_NONTRANSITIONAL_TO_ASCII;
use const IDNA_NONTRANSITIONAL_TO_UNICODE;

/**
 * @coversDefaultClass \Pdp\PublicSuffix
 */
class PublicSuffixTest extends TestCase
{
    public function testInternalPhpMethod(): void
    {
        $publicSuffix = PublicSuffix::fromICANN('ac.be');
        $generatePublicSuffix = eval('return '.var_export($publicSuffix, true).';');
        self::assertEquals($publicSuffix, $generatePublicSuffix);
        self::assertEquals('"ac.be"', json_encode($publicSuffix));
        self::assertSame('ac.be', (string) $publicSuffix);
    }

    public function testPSToUnicodeWithUrlEncode(): void
    {
        self::assertSame('bébe', PublicSuffix::fromUnknown('b%C3%A9be')->toUnicode()->getContent());
    }

    /**
     * @dataProvider PSProvider
     * @param ?string $publicSuffix
     */
    public function testSetSection(?string $publicSuffix, string $section, bool $isKnown, bool $isIcann, bool $isPrivate): void
    {
        if ('' === $section) {
            $ps = PublicSuffix::fromUnknown($publicSuffix);
        } elseif ('ICANN_DOMAINS' === $section) {
            $ps = PublicSuffix::fromICANN($publicSuffix);
        } elseif ('PRIVATE_DOMAINS' === $section) {
            $ps = PublicSuffix::fromPrivate($publicSuffix);
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
     * @dataProvider invalidPublicSuffixProvider
     */
    public function testConstructorThrowsException(string $publicSuffix): void
    {
        self::expectException(InvalidDomainName::class);

        PublicSuffix::fromUnknown($publicSuffix);
    }

    public function invalidPublicSuffixProvider(): iterable
    {
        return [
            'empty string' => [''],
            'absolute host' => ['foo.'],
        ];
    }

    public function testPSToAsciiThrowsException(): void
    {
        self::expectException(InvalidHost::class);

        PublicSuffix::fromUnknown('a⒈com');
    }

    public function testToUnicodeThrowsException(): void
    {
        self::expectException(InvalidHost::class);

        PublicSuffix::fromUnknown('xn--a-ecp.ru')->toUnicode();
    }

    /**
     * @dataProvider conversionReturnsTheSameInstanceProvider
     * @param ?string $publicSuffix
     */
    public function testConversionReturnsTheSameInstance(?string $publicSuffix): void
    {
        $instance = PublicSuffix::fromUnknown($publicSuffix);

        self::assertEquals($instance->toUnicode(), $instance);
        self::assertEquals($instance->toAscii(), $instance);
    }

    public function conversionReturnsTheSameInstanceProvider(): iterable
    {
        return [
            'ascii only domain' => ['ac.be'],
            'null domain' => [null],
        ];
    }

    public function testToUnicodeReturnsSameInstance(): void
    {
        $instance = PublicSuffix::fromUnknown('食狮.公司.cn');

        self::assertEquals($instance->toUnicode(), $instance);
    }

    /**
     * @dataProvider countableProvider
     * @param ?string $domain
     */
    public function testCountable(?string $domain, int $nbLabels, array $labels): void
    {
        $domain = PublicSuffix::fromUnknown($domain);

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
     * @dataProvider customIDNAProvider
     */
    public function testResolveWithCustomIDNAOptions(
        string $name,
        string $expectedContent,
        string $expectedAscii,
        string $expectedUnicode
    ): void {
        $publicSuffix = PublicSuffix::fromUnknown($name, IDNA_NONTRANSITIONAL_TO_ASCII, IDNA_NONTRANSITIONAL_TO_UNICODE);
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

    public function testWithIDNAOptions(): void
    {
        $publicSuffix = PublicSuffix::fromUnknown('com');

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
