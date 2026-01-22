# Symfony Console Reference

## Defining Commands

```php
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MyCommand extends Command
{
    protected static $defaultName = 'app:my-command';

    protected function configure()
    {
        $this
            ->setDescription('Command description')
            ->setHelp('Help text for the command')
            ->addArgument('name', InputArgument::REQUIRED, 'Argument description')
            ->addOption('option_name', 'o', InputOption::VALUE_OPTIONAL, 'Option description', 'default');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $option = $input->getOption('option_name');

        $output->writeln('Output text');

        return Command::SUCCESS;
    }
}
```

## Argument Types

- `InputArgument::REQUIRED` - Must be provided
- `InputArgument::OPTIONAL` - Can be omitted
- `InputArgument::IS_ARRAY` - Accepts multiple values

## Option Types

- `InputOption::VALUE_NONE` - Boolean flag (no value)
- `InputOption::VALUE_REQUIRED` - Must have a value if used
- `InputOption::VALUE_OPTIONAL` - Value is optional
- `InputOption::VALUE_IS_ARRAY` - Can be used multiple times

## Return Codes

- `Command::SUCCESS` (0) - Command executed successfully
- `Command::FAILURE` (1) - Command failed
- `Command::INVALID` (2) - Invalid input

## Global Options

All commands inherit these options:
- `--help|-h` - Display help
- `--quiet|-q` - Suppress output
- `--verbose|-v|-vv|-vvv` - Increase verbosity
- `--version|-V` - Display version
- `--ansi|--no-ansi` - Force/disable ANSI output
- `--no-interaction|-n` - Disable interactive questions
