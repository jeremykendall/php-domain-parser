<?php

declare(strict_types=1);

namespace Pdp;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use function json_encode;

/**
 * @coversDefaultClass \Pdp\PublicSuffix
 */
class PublicSuffixTest extends TestCase
{
    public function testInternalPhpMethod(): void
    {
        $domain = Domain::fromIDNA2008('ac.be');
        $publicSuffix = PublicSuffix::fromICANN($domain);
        $generatePublicSuffix = eval('return '.var_export($publicSuffix, true).';');
        self::assertEquals($publicSuffix, $generatePublicSuffix);
        self::assertEquals('"ac.be"', json_encode($publicSuffix));
        self::assertSame('ac.be', $publicSuffix->toString());
    }

    public function testPSToUnicodeWithUrlEncode(): void
    {
        $domain = Domain::fromIDNA2008('b%C3%A9be');
        self::assertSame('bébe', PublicSuffix::fromUnknown($domain)->toUnicode()->value());
    }

    /**
     * @dataProvider PSProvider
     * @param ?string $publicSuffix
     */
    public function testSetSection(?string $publicSuffix, string $section, bool $isKnown, bool $isIcann, bool $isPrivate): void
    {
        $domain = Domain::fromIDNA2008($publicSuffix);

        if ('' === $section) {
            $ps = PublicSuffix::fromUnknown($domain);
        } elseif ('ICANN_DOMAINS' === $section) {
            $ps = PublicSuffix::fromICANN($domain);
        } elseif ('PRIVATE_DOMAINS' === $section) {
            $ps = PublicSuffix::fromPrivate($domain);
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
        self::expectException(SyntaxError::class);

        PublicSuffix::fromUnknown(Domain::fromIDNA2008($publicSuffix));
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
        self::expectException(SyntaxError::class);

        PublicSuffix::fromUnknown(Domain::fromIDNA2008('a⒈com'));
    }

    public function testToUnicodeThrowsException(): void
    {
        self::expectException(SyntaxError::class);

        PublicSuffix::fromUnknown(Domain::fromIDNA2008('xn--a-ecp.ru'))->toUnicode();
    }

    /**
     * @dataProvider conversionReturnsTheSameInstanceProvider
     * @param ?string $publicSuffix
     */
    public function testConversionReturnsTheSameInstance(?string $publicSuffix): void
    {
        $instance = PublicSuffix::fromUnknown(Domain::fromIDNA2008($publicSuffix));

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
        $instance = PublicSuffix::fromUnknown(Domain::fromIDNA2008('食狮.公司.cn'));

        self::assertEquals($instance->toUnicode(), $instance);
    }

    /**
     * @dataProvider countableProvider
     * @param ?string $domain
     */
    public function testCountable(?string $domain, int $nbLabels, array $labels): void
    {
        $domain = PublicSuffix::fromUnknown(Domain::fromIDNA2008($domain));

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
        $domain = Domain::fromIDNA2008($name);
        $publicSuffix = PublicSuffix::fromUnknown($domain);

        self::assertSame($expectedContent, $publicSuffix->value());
        self::assertSame($expectedAscii, $publicSuffix->toAscii()->value());
        self::assertSame($expectedUnicode, $publicSuffix->toUnicode()->value());
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
        self::assertNotEquals(
            PublicSuffix::fromUnknown(Domain::fromIDNA2008('com')),
            PublicSuffix::fromUnknown(Domain::fromIDNA2003('com'))
        );
    }
}
