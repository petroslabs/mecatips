<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tag;
use App\Entity\Tip;
use App\Entity\TipRevision;
use App\Entity\User;
use App\Entity\UsefulVote;
use App\Entity\Vehicle;
use App\Enum\TipStatus;
use App\Enum\VehicleStatus;
use App\Form\TipFormType;
use App\Repository\TagRepository;
use App\Repository\TipRepository;
use App\Repository\UsefulVoteRepository;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

final class TipController extends AbstractController
{
    #[Route('/tips', name: 'tip_index', methods: ['GET'])]
    public function index(TipRepository $tipRepository): Response
    {
        return $this->render('tip/index.html.twig', [
            'tips' => $tipRepository->findPublished(),
        ]);
    }

    #[Route('/tips/{id}', name: 'tip_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Tip $tip, UsefulVoteRepository $usefulVoteRepository): Response
    {
        if ($tip->getStatus() !== TipStatus::PUBLISHED) {
            throw $this->createNotFoundException();
        }

        $myVote = null;
        $user = $this->getUser();
        if ($user instanceof User) {
            $vote = $usefulVoteRepository->findOneBy(['tip' => $tip, 'user' => $user]);
            $myVote = $vote?->isUseful();
        }

        return $this->render('tip/show.html.twig', [
            'tip' => $tip,
            'usefulCount' => $usefulVoteRepository->count(['tip' => $tip, 'useful' => true]),
            'notUsefulCount' => $usefulVoteRepository->count(['tip' => $tip, 'useful' => false]),
            'myVote' => $myVote,
        ]);
    }

    #[Route('/tips/{id}/vote-utile', name: 'tip_vote_useful', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function voteUseful(
        Tip $tip,
        Request $request,
        EntityManagerInterface $entityManager,
        UsefulVoteRepository $usefulVoteRepository,
    ): Response {
        if ($tip->getStatus() !== TipStatus::PUBLISHED) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('useful-vote-' . $tip->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, réessaie.');

            return $this->redirectToRoute('tip_show', ['id' => $tip->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();
        $useful = $request->request->get('useful') === '1';

        $vote = $usefulVoteRepository->findOneBy(['tip' => $tip, 'user' => $user]);

        if ($vote && $vote->isUseful() === $useful) {
            // Revoter la même valeur annule le vote (bascule, comme sur Stack Overflow).
            $entityManager->remove($vote);
        } elseif ($vote) {
            $vote->setUseful($useful);
            $vote->setVotedAt(new \DateTimeImmutable());
        } else {
            $vote = (new UsefulVote())
                ->setTip($tip)
                ->setUser($user)
                ->setUseful($useful);
            $entityManager->persist($vote);
        }

        $entityManager->flush();

        return $this->redirectToRoute('tip_show', ['id' => $tip->getId()]);
    }

    #[Route('/tips/nouveau', name: 'tip_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        VehicleRepository $vehicleRepository,
        TagRepository $tagRepository,
        SluggerInterface $slugger,
    ): Response {
        $form = $this->createForm(TipFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $allVehicles = (bool) $form->get('allVehicles')->getData();
            $vehicleLabel = trim((string) $form->get('vehicleLabel')->getData());

            if (!$allVehicles && $vehicleLabel === '') {
                $form->get('vehicleLabel')->addError(new FormError(
                    'Précise un véhicule, ou coche "Valable pour tous véhicules".'
                ));
            }

            if ($form->isValid()) {
                /** @var User $user */
                $user = $this->getUser();

                $vehicle = $allVehicles ? null : $this->resolveVehicle($vehicleLabel, $user, $vehicleRepository, $entityManager);

                $tip = (new Tip())
                    ->setAuthor($user)
                    ->setCategory($form->get('category')->getData())
                    ->setType($form->get('type')->getData())
                    ->setVehicle($vehicle);

                foreach ($this->resolveTags((string) $form->get('tagsInput')->getData(), $tagRepository, $entityManager, $slugger) as $tag) {
                    $tip->addTag($tag);
                }

                $revision = (new TipRevision())
                    ->setTitle((string) $form->get('title')->getData())
                    ->setContent((string) $form->get('content')->getData());

                $tip->addRevision($revision);

                $entityManager->persist($tip);
                $entityManager->persist($revision);
                $entityManager->flush();

                $this->addFlash('success', 'Ton tip a été envoyé au comité de validation. Merci pour ta contribution !');

                return $this->redirectToRoute('app_home');
            }
        }

        return $this->render('tip/new.html.twig', [
            'tipForm' => $form,
            'vehicleLabels' => $vehicleRepository->findAllLabels(),
        ]);
    }

    private function resolveVehicle(
        string $label,
        User $proposedBy,
        VehicleRepository $vehicleRepository,
        EntityManagerInterface $entityManager,
    ): Vehicle {
        $vehicle = $vehicleRepository->findOneByLabel($label);

        if ($vehicle) {
            return $vehicle;
        }

        $vehicle = (new Vehicle())
            ->setLabel($label)
            ->setStatus(VehicleStatus::PENDING)
            ->setProposedBy($proposedBy);

        $entityManager->persist($vehicle);

        return $vehicle;
    }

    /** @return list<Tag> */
    private function resolveTags(
        string $tagsInput,
        TagRepository $tagRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
    ): array {
        $labels = array_unique(array_filter(array_map('trim', explode(',', $tagsInput))));

        $tags = [];
        foreach ($labels as $label) {
            $slug = strtolower((string) $slugger->slug($label));

            $tag = $tagRepository->findOneBy(['slug' => $slug]);
            if (!$tag) {
                $tag = (new Tag())->setLabel($label)->setSlug($slug);
                $entityManager->persist($tag);
            }

            $tags[] = $tag;
        }

        return $tags;
    }
}
