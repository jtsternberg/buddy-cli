<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Unit\Output;

use BuddyCli\Output\JsonFormatter;
use BuddyCli\Tests\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class JsonFormatterTest extends TestCase
{
    public function testOutputSimpleArray(): void
    {
        $output = new BufferedOutput();
        JsonFormatter::output($output, ['name' => 'test', 'value' => 123]);

        $result = $output->fetch();
        $this->assertJson($result);

        $decoded = json_decode($result, true);
        $this->assertSame('test', $decoded['name']);
        $this->assertSame(123, $decoded['value']);
    }

    public function testOutputPrettyPrint(): void
    {
        $output = new BufferedOutput();
        JsonFormatter::output($output, ['key' => 'value']);

        $result = $output->fetch();
        // Pretty print includes newlines
        $this->assertStringContainsString("\n", $result);
    }

    public function testOutputUnescapedSlashes(): void
    {
        $output = new BufferedOutput();
        JsonFormatter::output($output, ['url' => 'https://example.com/path']);

        $result = $output->fetch();
        // Slashes should not be escaped
        $this->assertStringContainsString('https://example.com/path', $result);
        $this->assertStringNotContainsString('\/', $result);
    }

    public function testOutputNestedArray(): void
    {
        $output = new BufferedOutput();
        $data = [
            'projects' => [
                ['name' => 'project1', 'id' => 1],
                ['name' => 'project2', 'id' => 2],
            ],
        ];
        JsonFormatter::output($output, $data);

        $result = $output->fetch();
        $decoded = json_decode($result, true);
        $this->assertCount(2, $decoded['projects']);
    }
}
