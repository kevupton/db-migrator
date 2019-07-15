<?php

namespace Kevupton\DBMigrator\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TakeSnapshotCommand extends BaseCommand
{
    protected static $defaultName = 'take-snapshot';

    protected function configure()
    {
        parent::configure();
        $this->setDescription('Takes a snapshot of the current database');
    }

    protected function handle(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Taking Snapshot... Please wait.');
        $name = $this->manager->snapshots()->takeSnapshot();
        $output->writeln('Snapshot Complete!');
        $output->writeln('Output: ' . $name);
    }
}