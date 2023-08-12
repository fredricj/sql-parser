<?php

declare(strict_types=1);

namespace PhpMyAdmin\SqlParser\Tests\Parser;

use PhpMyAdmin\SqlParser\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PurgeStatementTest extends TestCase
{
    #[DataProvider('purgeProvider')]
    public function testPurge(string $test): void
    {
        $this->runParserTest($test);
    }

    /**
     * @return string[][]
     */
    public static function purgeProvider(): array
    {
        return [
            ['parser/parsePurge'],
            ['parser/parsePurge2'],
            ['parser/parsePurge3'],
            ['parser/parsePurge4'],
            ['parser/parsePurgeErr'],
            ['parser/parsePurgeErr2'],
            ['parser/parsePurgeErr3'],
        ];
    }
}
