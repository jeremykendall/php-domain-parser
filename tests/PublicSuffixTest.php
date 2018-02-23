<?php

declare(strict_types=1);

namespace Pdp\Tests;

use Pdp\Exception;
use Pdp\PublicSuffix;
use Pdp\Rules;
use PHPUnit\Framework\TestCase;

class PublicSuffixTest extends TestCase
{
    public function testDomainInternalPhpMethod()
    {
        $publicSuffix = new PublicSuffix('ac.be', Rules::ICANN_DOMAINS);
        $generatePublicSuffix = eval('return '.var_export($publicSuffix, true).';');
        $this->assertEquals($publicSuffix, $generatePublicSuffix);
    }

    public function testPSToUnicodeWithUrlEncode()
    {
        $this->assertSame('bébe', (new PublicSuffix('b%C3%A9be'))->toUnicode()->getContent());
    }

    public function testPSToAsciiThrowsException()
    {
        $this->expectException(Exception::class);
        (new PublicSuffix('_b%C3%A9bé.be-'))->toAscii();
    }

    public function testConversionReturnsTheSameInstance()
    {
        $instance = new PublicSuffix('ac.be', Rules::ICANN_DOMAINS);
        $this->assertSame($instance->toUnicode(), $instance);
        $this->assertSame($instance->toAscii(), $instance);
    }
}
