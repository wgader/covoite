<?php

namespace App\Command;

use App\Service\PastTripsCloser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:close-past-trips',
    description: 'Termine les trajets partis depuis plus de 6 h et previent les passagers',
)]
class ClosePastTripsCommand extends Command
{
    public function __construct(private readonly PastTripsCloser $closer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->closer->closeAll();
        (new SymfonyStyle($input, $output))->success($count . ' trajet(s) termine(s).');

        return Command::SUCCESS;
    }
}
