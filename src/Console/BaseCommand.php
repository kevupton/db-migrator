<?php

namespace Kevupton\DBMigrator\Console;

use Dotenv\Dotenv;
use Exception;
use Kevupton\DBMigrator\DBManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    protected static $defaultName = 'apply-snapshot';

    /** @var DBManager */
    protected $manager = null;
    /** @var InputInterface */
    protected $input = null;
    /** @var OutputInterface */
    protected $output = null;

    protected function configure()
    {
        $this->addOption('database_path', ['db'], InputOption::VALUE_OPTIONAL, 'The path to the Database directory', './')
            ->addOption('env_path', ['env'], InputOption::VALUE_OPTIONAL, 'The path to the .env file directory', './');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dotenv = Dotenv::create(getcwd() . "/" . $input->getOption('env_path'));

        try {
            $dotenv->load();
        } catch (Exception $e) {
            $output->writeln('Could not find .env file.');
        }
        $output->writeln('Environment Loaded.');

        $database_path = getcwd() . "/" . $input->getOption('database_path');
        $this->manager = create_db_manager($database_path);

        $output->writeln('Running in context: ' . $database_path);

        $this->handle($input, $output);
    }

    abstract protected function handle(InputInterface $input, OutputInterface $output);
}