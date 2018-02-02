<?php

declare(strict_types=1);

namespace pdp\tests;

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

    public function testPSToAsciiThrowsException()
    {
        $this->expectException(Exception::class);
        (new PublicSuffix('_b%C3%A9bé.be-'))->toAscii();
    }
}
