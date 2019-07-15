<?php

namespace Kevupton\DBMigrator\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ApplySnapshotCommand extends BaseCommand
{
    protected static $defaultName = 'apply-snapshot';

    protected function configure()
    {
        $this->setDescription('Applies a snapshot to the current database')
            ->addArgument('snapshot_name', InputArgument::REQUIRED, 'The full name of the snapshot (2019-01-01_11:11:11.sql.gz)');
    }

    protected function handle(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Applying Snapshot...');
        $this->manager->snapshots()->applySnapshot($input->getArgument('snapshot_name'));
        $output->writeln('Complete.');
    }
}