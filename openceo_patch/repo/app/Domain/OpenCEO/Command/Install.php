<?php

namespace Leantime\Domain\OpenCEO\Command;

use Illuminate\Console\Command;
use Leantime\Domain\OpenCEO\Services\Schema;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'openceo:install', description: 'Install or upgrade OpenCEO foundation tables')]
class Install extends Command
{
    protected function execute($input, $output): int
    {
        $schema = app(Schema::class);
        $created = $schema->install();
        $output->writeln($created ? 'Created: '.implode(', ', $created) : 'OpenCEO schema already installed.');
        return self::SUCCESS;
    }
}
