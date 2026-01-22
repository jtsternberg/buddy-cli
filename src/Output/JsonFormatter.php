<?php

declare(strict_types=1);

namespace BuddyCli\Output;

use Symfony\Component\Console\Output\OutputInterface;

class JsonFormatter
{
    public static function output(OutputInterface $output, mixed $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $output->writeln('{"error": "Failed to encode JSON"}');
            return;
        }
        $output->writeln($json);
    }
}
