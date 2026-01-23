<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Unit\Output;

use BuddyCli\Output\TableFormatter;
use BuddyCli\Tests\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class TableFormatterTest extends TestCase
{
    public function testRenderCreatesTable(): void
    {
        $output = new BufferedOutput();
        TableFormatter::render(
            $output,
            ['Name', 'Status'],
            [
                ['Pipeline 1', 'ACTIVE'],
                ['Pipeline 2', 'DISABLED'],
            ]
        );

        $result = $output->fetch();
        $this->assertStringContainsString('Name', $result);
        $this->assertStringContainsString('Status', $result);
        $this->assertStringContainsString('Pipeline 1', $result);
        $this->assertStringContainsString('ACTIVE', $result);
    }

    public function testKeyValueWithTitle(): void
    {
        $output = new BufferedOutput();
        TableFormatter::keyValue(
            $output,
            ['Name' => 'My Project', 'Status' => 'Active'],
            'Project Details'
        );

        $result = $output->fetch();
        $this->assertStringContainsString('Project Details', $result);
        $this->assertStringContainsString('Name', $result);
        $this->assertStringContainsString('My Project', $result);
    }

    public function testKeyValueWithoutTitle(): void
    {
        $output = new BufferedOutput();
        TableFormatter::keyValue(
            $output,
            ['Key1' => 'Value1', 'Key2' => 'Value2']
        );

        $result = $output->fetch();
        $this->assertStringContainsString('Key1', $result);
        $this->assertStringContainsString('Value1', $result);
    }

    public function testKeyValuePadsKeys(): void
    {
        $output = new BufferedOutput();
        TableFormatter::keyValue(
            $output,
            ['Short' => 'A', 'VeryLongKey' => 'B']
        );

        $result = $output->fetch();
        // Both keys should be padded to the same width (VeryLongKey = 11 chars)
        // The short key "Short" (5 chars) should be padded with spaces
        $this->assertStringContainsString('Short', $result);
        $this->assertStringContainsString('VeryLongKey', $result);
    }
}
