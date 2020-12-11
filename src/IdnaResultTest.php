<?php

declare(strict_types=1);

namespace Pdp;

use PHPUnit\Framework\TestCase;
use function var_export;

final class IdnaResultTest extends TestCase
{
    public function testDomainInternalPhpMethod(): void
    {
        $infos = ['result' => 'foo.bar', 'isTransitionalDifferent' => false, 'errors' => 0];
        $result = IdnaResult::fromIntl($infos);
        $generateResult = eval('return '.var_export($result, true).';');

        self::assertEquals($result, $generateResult);
        self::assertSame($infos, $generateResult->toIntl());
    }

    public function testItCanBeInstantiatedFromArray(): void
    {
        $infos = ['result' => '', 'isTransitionalDifferent' => false, 'errors' => 0];
        $result = IdnaResult::fromIntl($infos);

        self::assertSame('', $result->result());
        self::assertFalse($result->isTransitionalDifferent());
        self::assertCount(0, $result->errors());
        self::assertNull($result->error(IdnaResult::ERROR_BIDI));
    }

    public function testInvalidSyntaxAfterIDNConversion(): void
    {
        try {
            IntlIdna::toAscii('％００.com', IntlIdna::IDNA2008_ASCII);
        } catch (SyntaxError $exception) {
            $result = $exception->fetchIdnaResult();
            self::assertInstanceOf(IdnaResult::class, $result);
            self::assertCount(1, $result->errors());
            self::assertNotEmpty($result->error(IdnaResult::ERROR_DISALLOWED));
        }
    }
}
