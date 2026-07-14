<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsCommand(name: 'app:test-mailer', description: 'TEMPORAIRE — envoie un email de test et affiche l\'erreur complète en cas d\'échec')]
final class TestMailerCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TransportInterface $transport,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Adresse email de destination');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Transport : ' . $this->transport::class . ' (' . (string) $this->transport . ')');

        $email = (new Email())
            ->from(new Address('mecatips@petroslabs.dev', 'MécaTips'))
            ->to((string) $input->getArgument('to'))
            ->subject('MécaTips — test diagnostic mailer')
            ->text('Ceci est un email de test envoyé via app:test-mailer.');

        try {
            $this->mailer->send($email);
            $output->writeln('<info>Envoi réussi, aucune exception levée.</info>');
        } catch (\Throwable $exception) {
            $output->writeln('<error>Échec : ' . $exception::class . '</error>');
            $output->writeln('Message : ' . $exception->getMessage());

            $previous = $exception->getPrevious();
            while ($previous !== null) {
                $output->writeln('Cause précédente (' . $previous::class . ') : ' . $previous->getMessage());
                $previous = $previous->getPrevious();
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
