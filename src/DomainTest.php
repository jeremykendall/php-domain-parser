<?php

declare(strict_types=1);

namespace Pdp;

use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

/**
 * @coversDefaultClass \Pdp\Domain
 */
final class DomainTest extends TestCase
{
    /**
     * @covers \Pdp\SyntaxError
     * @dataProvider invalidDomainProvider
     */
    public function testToAsciiThrowsException(string $domain): void
    {
        $this->expectException(SyntaxError::class);

        Domain::fromIDNA2008($domain);
    }

    /**
     * @return iterable<string,array{0:string}>
     */
    public function invalidDomainProvider(): iterable
    {
        return [
            'invalid IDN domain' => ['a⒈com'],
            'invalid IDN domain full size' => ['％００.com'],
            'invalid IDN domain full size rawurlencode ' => ['%ef%bc%85%ef%bc%94%ef%bc%91.com'],
        ];
    }

    public function testToUnicodeNeverThrowsException(): void
    {
        self::assertSame(
            'xn--a-ecp.ru',
            Domain::fromIDNA2008('xN--a-eCp.rU')->toUnicode()->toString()
        );
    }

    public function testDomainInternalPhpMethod(): void
    {
        $domain = Domain::fromIDNA2008('www.ulb.ac.be');
        /** @var Domain $generateDomain */
        $generateDomain = eval('return '.var_export($domain, true).';');
        self::assertEquals($domain, $generateDomain);
        self::assertSame(['be', 'ac', 'ulb', 'www'], iterator_to_array($domain));
        self::assertEquals('"www.ulb.ac.be"', json_encode($domain));
        self::assertSame('www.ulb.ac.be', $domain->toString());
    }

    /**
     * @dataProvider countableProvider
     *
     * @param string[] $labels
     * @param ?string  $domain
     */
    public function testCountable(?string $domain, int $nbLabels, array $labels): void
    {
        $domain = Domain::fromIDNA2008($domain);
        self::assertCount($nbLabels, $domain);
        self::assertSame($labels, iterator_to_array($domain));
    }

    /**
     * @return iterable<string,array{0:string|null, 1:int, 2:array<string>}>
     */
    public function countableProvider(): iterable
    {
        return [
            'null' => [null, 0, []],
            'empty string' => ['', 1, ['']],
            'simple' => ['foo.bar.baz', 3, ['baz', 'bar', 'foo']],
            'unicode' => ['www.食狮.公司.cn', 4, ['cn', '公司', '食狮', 'www']],
        ];
    }

    public function testGetLabel(): void
    {
        $domain = Domain::fromIDNA2008('master.example.com');

        self::assertSame('com', $domain->label(0));
        self::assertSame('example', $domain->label(1));
        self::assertSame('master', $domain->label(-1));
        self::assertNull($domain->label(23));
        self::assertNull($domain->label(-23));
    }

    public function testOffsets(): void
    {
        $domain = Domain::fromIDNA2008('master.com.example.com');

        self::assertSame([0, 2], $domain->keys('com'));
        self::assertSame([], $domain->keys('toto'));
        self::assertSame([0, 1, 2, 3], $domain->keys());
    }

    public function testLabels(): void
    {
        self::assertSame([
            'com',
            'example',
            'com',
            'master',
        ], Domain::fromIDNA2008('master.com.example.com')->labels());

        self::assertSame([], Domain::fromIDNA2008(null)->labels());
    }

    /**
     * @dataProvider toUnicodeProvider
     *
     * @param ?string $domain
     * @param ?string $expectedDomain
     * @param ?string $expectedIDNDomain
     */
    public function testToIDN(
        ?string $domain,
        ?string $expectedDomain,
        ?string $expectedIDNDomain
    ): void {
        $domain = Domain::fromIDNA2008($domain);
        self::assertSame($expectedDomain, $domain->value());

        /** @var Domain $domainIDN */
        $domainIDN = $domain->toUnicode();
        self::assertSame($expectedIDNDomain, $domainIDN->value());
    }

    /**
     * @return iterable<string,array{domain:string|null, expectedDomain:string|null, expectedIDNDomain:string|null}>
     */
    public function toUnicodeProvider(): iterable
    {
        return [
            'simple domain' => [
                'domain' => 'www.ulb.ac.be',
                'expectedDomain' => 'www.ulb.ac.be',
                'expectedIDNDomain' => 'www.ulb.ac.be',
            ],
            'ASCII to IDN domain' => [
                'domain' => 'www.xn--85x722f.xn--55qx5d.cn',
                'expectedDomain' => 'www.xn--85x722f.xn--55qx5d.cn',
                'expectedIDNDomain' => 'www.食狮.公司.cn',
            ],
            'IDN to IDN domain' => [
                'domain' => 'www.食狮.公司.cn',
                'expectedDomain' => 'www.食狮.公司.cn',
                'expectedIDNDomain' => 'www.食狮.公司.cn',
            ],
            'empty string domain and null suffix' => [
                'domain' => '',
                'expectedDomain' => '',
                'expectedIDNDomain' => '',
            ],
            'null domain and suffix' => [
                'domain' => null,
                'expectedDomain' => null,
                'expectedIDNDomain' => null,
            ],
            'domain with null suffix' => [
                'domain' => 'www.xn--85x722f.xn--55qx5d.cn',
                'expectedDomain' => 'www.xn--85x722f.xn--55qx5d.cn',
                'expectedIDNDomain' => 'www.食狮.公司.cn',
            ],
            'domain with URLencoded data' => [
                'domain' => 'b%C3%A9b%C3%A9.be',
                'expectedDomain' => 'bébé.be',
                'expectedIDNDomain' => 'bébé.be',
            ],
        ];
    }

    /**
     * @dataProvider toAsciiProvider
     * @param ?string $domain
     * @param ?string $expectedDomain
     * @param ?string $expectedAsciiDomain
     */
    public function testToAscii(
        ?string $domain,
        ?string $expectedDomain,
        ?string $expectedAsciiDomain
    ): void {
        $domain = Domain::fromIDNA2008($domain);
        self::assertSame($expectedDomain, $domain->value());

        /** @var Domain $domainIDN */
        $domainIDN = $domain->toAscii();
        self::assertSame($expectedAsciiDomain, $domainIDN->value());
    }

    /**
     * @return iterable<string,array{domain:string|null, expectedDomain:string|null, expectedIDNDomain:string|null}>
     */
    public function toAsciiProvider(): iterable
    {
        return [
            'simple domain' => [
                'domain' => 'www.ulb.ac.be',
                'expectedDomain' => 'www.ulb.ac.be',
                'expectedIDNDomain' => 'www.ulb.ac.be',
            ],
            'ASCII to ASCII domain' => [
                'domain' => 'www.xn--85x722f.xn--55qx5d.cn',
                'expectedDomain' => 'www.xn--85x722f.xn--55qx5d.cn',
                'expectedIDNDomain' => 'www.xn--85x722f.xn--55qx5d.cn',
            ],
            'ASCII to IDN domain' => [
                'domain' => 'www.食狮.公司.cn',
                'expectedDomain' => 'www.食狮.公司.cn',
                'expectedIDNDomain' => 'www.xn--85x722f.xn--55qx5d.cn',
            ],
            'null domain and suffix' => [
                'domain' => null,
                'expectedDomain' => null,
                'expectedIDNDomain' => null,
            ],
            'domain with null suffix' => [
                'domain' => 'www.食狮.公司.cn',
                'expectedDomain' => 'www.食狮.公司.cn',
                'expectedIDNDomain' => 'www.xn--85x722f.xn--55qx5d.cn',
            ],
        ];
    }

    /**
     * @dataProvider withLabelWorksProvider
     *
     * @param ?string $expected
     */
    public function testWithLabelWorks(DomainName $domain, int $key, string $label, ?string $expected): void
    {
        $result = $domain->withLabel($key, $label);
        self::assertSame($expected, $result->value());
    }

    /**
     * @return iterable<string,array{domain:DomainName, key:int, label:string, expected:string}>
     */
    public function withLabelWorksProvider(): iterable
    {
        $base_domain = Domain::fromIDNA2008('www.example.com');

        return [
            'null domain' => [
                'domain' => Domain::fromIDNA2008(null),
                'key' => 0,
                'label' => 'localhost',
                'expected' => 'localhost',
            ],
            'simple replace positive offset' => [
                'domain' => $base_domain,
                'key' => 2,
                'label' => 'shop',
                'expected' => 'shop.example.com',
            ],
            'simple replace negative offset' => [
                'domain' => $base_domain,
                'key' => -1,
                'label' => 'shop',
                'expected' => 'shop.example.com',
            ],
            'simple addition positive offset' => [
                'domain' => $base_domain,
                'key' => 3,
                'label' => 'shop',
                'expected' => 'shop.www.example.com',
            ],
            'simple addition negative offset' => [
                'domain' => $base_domain,
                'key' => -4,
                'label' => 'shop',
                'expected' => 'www.example.com.shop',
            ],
            'simple replace remove PSL info' => [
                'domain' => $base_domain,
                'key' => 0,
                'label' => 'fr',
                'expected' => 'www.example.fr',
            ],
            'replace without any change' => [
                'domain' => $base_domain,
                'key' => 2,
                'label' => 'www',
                'expected' => 'www.example.com',
            ],
            'simple update IDN (1)' => [
                'domain' => $base_domain,
                'key' => 2,
                'label' => 'рф',
                'expected' => 'xn--p1ai.example.com',
            ],
            'simple update IDN (2)' => [
                'domain' => Domain::fromIDNA2008('www.bébé.be'),
                'key' => 2,
                'label' => 'xn--p1ai',
                'expected' => 'рф.bébé.be',
            ],
            'replace a domain with multiple label' => [
                'domain' => $base_domain,
                'key' => -1,
                'label' => 'www.shop',
                'expected' => 'www.shop.example.com',
            ],
        ];
    }

    public function testWithLabelFailsWithTypeError(): void
    {
        $this->expectException(TypeError::class);
        Domain::fromIDNA2008('example.com')->withLabel(1, new stdClass());
    }

    public function testWithLabelFailsWithInvalidKey(): void
    {
        $this->expectException(SyntaxError::class);
        Domain::fromIDNA2008('example.com')->withLabel(-4, 'www');
    }

    public function testWithLabelFailsWithInvalidLabel2(): void
    {
        $this->expectException(SyntaxError::class);

        Domain::fromIDNA2008('example.com')->withLabel(-1, '');
    }

    /**
     * @dataProvider validAppend
     */
    public function testAppend(string $raw, string $append, string $expected): void
    {
        self::assertSame($expected, Domain::fromIDNA2008($raw)->append($append)->toString());
    }

    /**
     * @return iterable<array-key, array{0:string, 1:string, 2:string}>
     */
    public function validAppend(): iterable
    {
        return [
            ['secure.example.com', '8.8.8.8', 'secure.example.com.8.8.8.8'],
            ['secure.example.com', 'master', 'secure.example.com.master'],
            ['secure.example.com', 'master.', 'secure.example.com.master.'],
            ['example.com', '', 'example.com.'],
        ];
    }

    /**
     * @dataProvider validPrepend
     */
    public function testPrepend(string $raw, string $prepend, string $expected): void
    {
        self::assertSame($expected, Domain::fromIDNA2008($raw)->prepend($prepend)->toString());
    }

    /**
     * @return iterable<array-key, array{0:string, 1:string, 2:string}>
     */
    public function validPrepend(): iterable
    {
        return [
            ['secure.example.com', 'master', 'master.secure.example.com'],
            ['secure.example.com', '127.0.0.1', '127.0.0.1.secure.example.com'],
            ['secure.example.com.', 'master', 'master.secure.example.com.'],
        ];
    }

    /**
     * @dataProvider withoutLabelWorksProvider
     * @param ?string $expected
     */
    public function testwithoutLabelWorks(DomainName $domain, int $key, ?string $expected): void
    {
        $result = $domain->withoutLabel($key);
        self::assertSame($expected, $result->value());
    }

    /**
     * @return iterable<string,array{domain:DomainName, key:int, expected:string}>
     */
    public function withoutLabelWorksProvider(): iterable
    {
        $base_domain = Domain::fromIDNA2008('www.example.com');

        return [
            'simple removal positive offset' => [
                'domain' => $base_domain,
                'key' => 2,
                'expected' => 'example.com',
            ],
            'simple removal negative offset' => [
                'domain' => $base_domain,
                'key' => -1,
                'expected' => 'example.com',
            ],
            'simple removal strip PSL info positive offset' => [
                'domain' => $base_domain,
                'key' => 0,
                'expected' => 'www.example',
            ],
            'simple removal strip PSL info negative offset' => [
                'domain' => $base_domain,
                'key' => -3,
                'expected' => 'www.example',
            ],
        ];
    }

    public function testwithoutLabelFailsWithInvalidKey(): void
    {
        $this->expectException(SyntaxError::class);
        Domain::fromIDNA2008('example.com')->withoutLabel(-3);
    }

    public function testwithoutLabelWorksWithMultipleKeys(): void
    {
        self::assertNull(Domain::fromIDNA2008('www.example.com')->withoutLabel(0, 1, 2)->value());
    }

    /**
     * @dataProvider resolveCustomIDNAOptionsProvider
     * @param ?string $expectedContent
     * @param ?string $expectedAscii
     * @param ?string $expectedUnicode
     * @param ?string $expectedWithLabel
     */
    public function testResolveWorksWithCustomIDNAOptions(
        string $domainName,
        string $withLabel,
        ?string $expectedContent,
        ?string $expectedAscii,
        ?string $expectedUnicode,
        ?string $expectedWithLabel
    ): void {
        $domain = Domain::fromIDNA2008($domainName);
        self::assertSame($expectedContent, $domain->value());
        self::assertSame($expectedAscii, $domain->toAscii()->value());
        self::assertSame($expectedUnicode, $domain->toUnicode()->value());
        self::assertSame($expectedWithLabel, $domain->withLabel(-1, $withLabel)->value());
    }

    /**
     * @return iterable<string,array<string>>
     */
    public function resolveCustomIDNAOptionsProvider(): iterable
    {
        return [
            'without deviation characters' => [
                'example.com',
                'größe',
                'example.com',
                'example.com',
                'example.com',
                'xn--gre-6ka8i.com',
            ],
            'without deviation characters with label' => [
                'www.example.com',
                'größe',
                'www.example.com',
                'www.example.com',
                'www.example.com',
                'xn--gre-6ka8i.example.com',
            ],
            'with deviation in domain' => [
                'www.faß.de',
                'größe',
                'www.faß.de',
                'www.xn--fa-hia.de',
                'www.faß.de',
                'größe.faß.de',
            ],
            'with deviation in label' => [
                'faß.test.de',
                'größe',
                'faß.test.de',
                'xn--fa-hia.test.de',
                'faß.test.de',
                'größe.test.de',
            ],
        ];
    }

    public function testWithIDNAOptions(): void
    {
        self::assertNotEquals(Domain::fromIDNA2003('example.com'), Domain::fromIDNA2008('example.com'));
    }

    public function testSlice(): void
    {
        $domain = Domain::fromIDNA2008('ulb.ac.be');

        self::assertSame($domain->toString(), $domain->slice(-3)->toString());
        self::assertSame($domain->toString(), $domain->slice(0)->toString());

        self::assertSame('ulb.ac', $domain->slice(1)->toString());
        self::assertSame('ulb', $domain->slice(-1)->toString());
        self::assertSame('be', $domain->slice(-3, 1)->toString());
    }

    public function testSliceThrowsOnOverFlow(): void
    {
        $this->expectException(SyntaxError::class);

        Domain::fromIDNA2008('ulb.ac.be')->slice(5);
    }
}
