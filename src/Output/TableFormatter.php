<?php

declare(strict_types=1);

namespace BuddyCli\Output;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class TableFormatter
{
    public static function render(OutputInterface $output, array $headers, array $rows): void
    {
        $table = new Table($output);
        $table->setHeaders($headers);
        $table->setRows($rows);
        $table->render();
    }

    public static function keyValue(OutputInterface $output, array $data, string $title = ''): void
    {
        if ($title !== '') {
            $output->writeln("<info>{$title}</info>");
            $output->writeln('');
        }

        $maxKeyLength = 0;
        foreach (array_keys($data) as $key) {
            $maxKeyLength = max($maxKeyLength, strlen((string) $key));
        }

        foreach ($data as $key => $value) {
            $paddedKey = str_pad((string) $key, $maxKeyLength);
            $output->writeln("  <comment>{$paddedKey}</comment>  {$value}");
        }
    }
}
