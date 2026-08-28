<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Command\Technology\RefreshTechnologySupportCommand;
use App\Repository\TechnologyReleaseCycleRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Run once at container boot (see frankenphp/docker-entrypoint.sh), after migrations.
 * The scheduler only refreshes technology support data weekly (src/Schedule.php), so a
 * fresh self-hosted install would otherwise show every technology as "Unknown" for up to
 * a week before its first scheduled run. Skips entirely once any data exists, so routine
 * restarts don't re-dispatch on every boot.
 */
#[AsCommand(name: 'app:technology:warmup', description: 'Queues a technology release-cycle refresh on first launch, if none is cached yet.')]
final class WarmupTechnologySupportCommand extends Command
{
    public function __construct(
        private readonly TechnologyReleaseCycleRepository $technologyReleaseCycleRepository,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->technologyReleaseCycleRepository->count() > 0) {
            $output->writeln('Technology release-cycle data already present, skipping.');

            return Command::SUCCESS;
        }

        $output->writeln('No technology release-cycle data yet, queuing a refresh...');

        $this->bus->dispatch(new RefreshTechnologySupportCommand());

        return Command::SUCCESS;
    }
}
