<?php

declare(strict_types=1);

namespace BuddyCli\Output;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class YamlFormatter
{
    public static function output(OutputInterface $output, mixed $data): void
    {
        $yaml = Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        $output->writeln($yaml);
    }

    public static function dump(mixed $data): string
    {
        return Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    public static function parse(string $yaml): array
    {
        return Yaml::parse($yaml);
    }
}
