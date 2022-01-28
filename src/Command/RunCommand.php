<?php

namespace App\Command;

use App\Action\SendMessageAction;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RunCommand extends Command
{
    protected static $defaultName = 'run';
    protected static $defaultDescription = 'Load history, check if message has to be sent and send it';

    private SendMessageAction $action;

    public function __construct(SendMessageAction $action)
    {
        $this->action = $action;

        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->action->run();

        $io->success('Done');

        return Command::SUCCESS;
        return Command::FAILURE;
    }
}
