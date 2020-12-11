<?php

declare(strict_types=1);

namespace Pdp;

use PHPUnit\Framework\TestCase;
use function var_export;

final class IdnaInfoTest extends TestCase
{
    public function testDomainInternalPhpMethod(): void
    {
        $infos = ['result' => 'foo.bar', 'isTransitionalDifferent' => false, 'errors' => 0];
        $result = IdnaInfo::fromIntl($infos);
        $generateResult = eval('return '.var_export($result, true).';');

        self::assertEquals($result, $generateResult);
    }

    public function testItCanBeInstantiatedFromArray(): void
    {
        $infos = ['result' => '', 'isTransitionalDifferent' => false, 'errors' => 0];
        $result = IdnaInfo::fromIntl($infos);

        self::assertSame('', $result->result());
        self::assertFalse($result->isTransitionalDifferent());
        self::assertSame(0, $result->errors());
        self::assertNull($result->error(IdnaInfo::ERROR_BIDI));
        self::assertCount(0, $result->errorList());
    }

    public function testInvalidSyntaxAfterIDNConversion(): void
    {
        try {
            IntlIdna::toAscii('％００.com', IntlIdna::IDNA2008_ASCII);
        } catch (SyntaxError $exception) {
            $result = $exception->fetchIdnaResult();
            self::assertInstanceOf(IdnaInfo::class, $result);
            self::assertSame(IdnaInfo::ERROR_DISALLOWED, $result->errors());
            self::assertIsString($result->error(IdnaInfo::ERROR_DISALLOWED));
            self::assertCount(1, $result->errorList());
        }
    }
}
