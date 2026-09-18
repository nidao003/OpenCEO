<?php

namespace Leantime\Domain\OpenCEO\Command;

use Illuminate\Console\Command;
use Leantime\Domain\OpenCEO\Services\Schema;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'openceo:status', description: 'Show OpenCEO foundation table status')]
class Status extends Command
{
    protected function execute($input, $output): int
    {
        foreach (app(Schema::class)->status() as $table => $exists) {
            $output->writeln(sprintf('%-40s %s', $table, $exists ? 'OK' : 'MISSING'));
        }
        return self::SUCCESS;
    }
}
