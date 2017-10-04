<?php

namespace Pdp\Exception;

use PHPUnit\Framework\TestCase;

class SeriouslyMalformedUrlExceptionTest extends TestCase
{
    public function testInstanceOfPdpException()
    {
        $this->assertInstanceOf(
            PdpException::class,
            new SeriouslyMalformedUrlException()
        );
    }

    public function testInstanceOfInvalidArgumentException()
    {
        $this->assertInstanceOf(
            'InvalidArgumentException',
            new SeriouslyMalformedUrlException()
        );
    }

    public function testMessage()
    {
        $url = 'http:///example.com';
        $this->expectException(SeriouslyMalformedUrlException::class);
        $this->expectExceptionMessage(sprintf('"%s" is one seriously malformed url.', $url));

        throw new SeriouslyMalformedUrlException($url);
    }
}
