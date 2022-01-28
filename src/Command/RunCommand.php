<?php

namespace App\Command;

use App\Action\SendMessageAction;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RunCommand extends Command
{
    protected static $defaultName = 'run';

    protected static $defaultDescription = 'Load history, check if message has to be sent and send it';

    private SendMessageAction $action;

    private LoggerInterface $logger;

    public function __construct(SendMessageAction $action, LoggerInterface $logger)
    {
        $this->action = $action;
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info(__METHOD__ . ': ' . 'Start');

        $io = new SymfonyStyle($input, $output);

        $result = $this->action->runPlease();

        if ($result) {
            $this->logger->info(__METHOD__ . ': ' . 'Done');
            $io->success('Done');

            return Command::SUCCESS;
        }

        $this->logger->warning(__METHOD__ . ': ' . 'Completed with errors');
        $io->error('Completed with errors - see logs');

        return Command::FAILURE;
    }
}
