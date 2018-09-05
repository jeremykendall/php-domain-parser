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

use Pdp\Exception\CouldNotLoadTLDs;
use Pdp\TLDConverter;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Pdp\TLDConverter
 */
class TLDConverterTest extends TestCase
{
    public function testConverter()
    {
        $string = file_get_contents(__DIR__.'/data/root_zones.dat');
        $res = (new TLDConverter())->convert($string);
        $this->assertInternalType('array', $res);
        $this->assertArrayHasKey('version', $res);
        $this->assertArrayHasKey('modifiedDate', $res);
        $this->assertArrayHasKey('records', $res);
        $this->assertInternalType('array', $res['records']);
    }

    /**
     * @dataProvider invalidContentProvider
     * @param string $content
     */
    public function testConverterThrowsException(string $content)
    {
        $this->expectException(CouldNotLoadTLDs::class);
        (new TLDConverter())->convert($content);
    }

    public function invalidContentProvider()
    {
        $double_header = <<<EOF
# Version 2018082200, Last Updated Wed Aug 22 07:07:01 2018 UTC
FOO
BAR
# Version 2018082200, Last Updated Wed Aug 22 07:07:01 2018 UTC
ABARTH
ABB
ABBOTT
ABBVIE
EOF;

        $invalid_header = <<<EOF
# Version 2018082200
FOO
BAR
EOF;

        $header_no_first_line = <<<EOF
FOO
BAR
# Version 2018082200, Last Updated Wed Aug 22 07:07:01 2018 UTC
ABARTH
ABB
ABBOTT
ABBVIE
EOF;

        $invalid_tld_content = <<<EOF
# Version 2018082200, Last Updated Wed Aug 22 07:07:01 2018 UTC
FOO
BAR
%ef%bc%85%ef%bc%94%ef%bc%91
EOF;

        $invalid_tld_content_not_root_zone = <<<EOF
# Version 2018082200, Last Updated Wed Aug 22 07:07:01 2018 UTC
FOO
BAR
GITHUB.COM
EOF;

        $invalid_tld_content_empty_tld = <<<EOF
# Version 2018082200, Last Updated Wed Aug 22 07:07:01 2018 UTC
FOO
  
GITHUB.COM
EOF;

        return [
            'double header' => [$double_header],
            'invalid header' => [$invalid_header],
            'empty content' => [''],
            'header not on the first line' => [$header_no_first_line],
            'invalid tld content' => [$invalid_tld_content],
            'invalid root zone' => [$invalid_tld_content_not_root_zone],
            'empty tld' => [$invalid_tld_content_empty_tld],
        ];
    }
}
