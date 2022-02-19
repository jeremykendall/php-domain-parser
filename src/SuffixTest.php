<?php

declare(strict_types=1);

namespace Pdp;

use PHPUnit\Framework\TestCase;
use function json_encode;

/**
 * @coversDefaultClass \Pdp\Suffix
 */
final class SuffixTest extends TestCase
{
    public function testItCanBeCreatedWithAnotherResolvedDomain(): void
    {
        $domain = Suffix::fromICANN('ac.be');
        $newDomain = Suffix::fromPrivate($domain);

        self::assertEquals($domain->domain(), $newDomain->domain());
        self::assertNotEquals($domain->isICANN(), $newDomain->isICANN());
    }

    public function testInternalPhpMethod(): void
    {
        $publicSuffix = Suffix::fromICANN('ac.be');
        /** @var Suffix $generatePublicSuffix */
        $generatePublicSuffix = eval('return '.var_export($publicSuffix, true).';');
        self::assertEquals($publicSuffix, $generatePublicSuffix);
        self::assertEquals('"ac.be"', json_encode($publicSuffix));
        self::assertSame('ac.be', $publicSuffix->toString());
    }

    public function testPSToUnicodeWithUrlEncode(): void
    {
        self::assertSame('bébe', Suffix::fromUnknown('b%C3%A9be')->toUnicode()->value());
    }

    /**
     * @dataProvider invalidPublicSuffixProvider
     */
    public function testConstructorThrowsException(string $publicSuffix): void
    {
        $this->expectException(SyntaxError::class);

        Suffix::fromUnknown($publicSuffix);
    }

    /**
     * @return iterable<string,array<string>>
     */
    public function invalidPublicSuffixProvider(): iterable
    {
        return [
            'empty string' => [''],
            'absolute host' => ['foo.'],
        ];
    }

    public function testFromICANN(): void
    {
        $suffix = Suffix::fromICANN('be');

        self::assertTrue($suffix->isKnown());
        self::assertTrue($suffix->isPublicSuffix());
        self::assertTrue($suffix->isICANN());
        self::assertFalse($suffix->isPrivate());
        self::assertFalse($suffix->isIANA());
        self::assertSame('be', $suffix->domain()->toString());
        $this->expectException(SyntaxError::class);

        Suffix::fromICANN(null);
    }

    public function testFromPrivate(): void
    {
        $suffix = Suffix::fromPrivate('be');

        self::assertTrue($suffix->isKnown());
        self::assertTrue($suffix->isPublicSuffix());
        self::assertFalse($suffix->isICANN());
        self::assertTrue($suffix->isPrivate());
        self::assertFalse($suffix->isIANA());
        self::assertSame('be', $suffix->domain()->toString());

        $this->expectException(SyntaxError::class);

        Suffix::fromPrivate(null);
    }

    public function testFromIANA(): void
    {
        $suffix = Suffix::fromIANA('be');

        self::assertTrue($suffix->isKnown());
        self::assertFalse($suffix->isPublicSuffix());
        self::assertFalse($suffix->isICANN());
        self::assertFalse($suffix->isPrivate());
        self::assertTrue($suffix->isIANA());
        self::assertSame('be', $suffix->domain()->toString());

        $this->expectException(SyntaxError::class);

        Suffix::fromIANA('ac.be');
    }

    /**
     * @dataProvider conversionReturnsTheSameInstanceProvider
     */
    public function testConversionReturnsTheSameInstance(string|null $publicSuffix): void
    {
        $instance = Suffix::fromUnknown($publicSuffix);

        self::assertEquals($instance->toUnicode(), $instance);
        self::assertEquals($instance->toAscii(), $instance);
    }

    /**
     * @return iterable<string,array{0:null|string}>
     */
    public function conversionReturnsTheSameInstanceProvider(): iterable
    {
        return [
            'ascii only domain' => ['ac.be'],
            'null domain' => [null],
        ];
    }

    public function testToUnicodeReturnsSameInstance(): void
    {
        $instance = Suffix::fromUnknown('食狮.公司.cn');

        self::assertEquals($instance->toUnicode(), $instance);
    }

    /**
     * @dataProvider countableProvider
     */
    public function testCountable(string|null $domain, int $nbLabels): void
    {
        $domain = Suffix::fromUnknown($domain);

        self::assertCount($nbLabels, $domain);
    }

    /**
     * @return iterable<string, array{0:string|null, 1:int, 2:array<string>}>
     */
    public function countableProvider(): iterable
    {
        return [
            'null' => [null, 0, []],
            'simple' => ['foo.bar.baz', 3, ['baz', 'bar', 'foo']],
            'unicode' => ['www.食狮.公司.cn', 4, ['cn', '公司', '食狮', 'www']],
        ];
    }
}
