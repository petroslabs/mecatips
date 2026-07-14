<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Notification;
use App\Entity\Tip;
use App\Enum\NotificationType;
use App\Enum\RevisionStatus;
use App\Enum\TipStatus;
use App\Repository\TipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * TEMPORAIRE — publie un tip PENDING sans passer par le vote du comité, pour
 * peupler le site au lancement (pas de fonctionnalité permanente de bypass,
 * voir CHANGELOG). Reproduit exactement la branche "majorité pour" de
 * TipReviewService::resolveIfDecided() : mêmes champs, même génération de
 * slug, même notification.
 */
#[AsCommand(name: 'app:publish-tip-directly', description: 'TEMPORAIRE — publie un tip en attente sans vote du comité')]
final class PublishTipDirectlyCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TipRepository $tipRepository,
        private readonly SluggerInterface $slugger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('tipId', InputArgument::REQUIRED, 'ID du tip à publier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tip = $this->tipRepository->find((int) $input->getArgument('tipId'));

        if ($tip === null) {
            $output->writeln('<error>Tip introuvable.</error>');

            return Command::FAILURE;
        }

        if ($tip->getStatus() !== TipStatus::PENDING) {
            $output->writeln('<error>Ce tip n\'est pas en attente (statut : ' . $tip->getStatus()->name . ').</error>');

            return Command::FAILURE;
        }

        $revision = $tip->getRevisions()->last();

        if ($revision === false) {
            $output->writeln('<error>Aucune révision trouvée pour ce tip.</error>');

            return Command::FAILURE;
        }

        $now = new \DateTimeImmutable();
        $revision->setStatus(RevisionStatus::APPROVED);
        $revision->setReviewedAt($now);

        $tip->setPublishedTitle($revision->getTitle());
        $tip->setPublishedContent($revision->getContent());
        $tip->setStatus(TipStatus::PUBLISHED);
        $tip->setPublishedAt($now);
        $tip->setSlug($this->generateUniqueSlug($revision->getTitle()));

        $notification = (new Notification())
            ->setRecipient($tip->getAuthor())
            ->setTip($tip)
            ->setType(NotificationType::TIP_PUBLISHED);
        $this->entityManager->persist($notification);

        $this->entityManager->flush();

        $output->writeln('<info>Publié : ' . $tip->getPublishedTitle() . ' (slug : ' . $tip->getSlug() . ')</info>');

        return Command::SUCCESS;
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = strtolower((string) $this->slugger->slug($title));
        $slug = $base;
        $suffix = 2;

        while ($this->tipRepository->findOneBy(['slug' => $slug]) !== null) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
