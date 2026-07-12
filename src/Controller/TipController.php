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
use App\Enum\TipType;
use App\Enum\VehicleStatus;
use App\Enum\VoteDecision;
use App\Form\TipFormType;
use App\Repository\CategoryRepository;
use App\Repository\FavoriteRepository;
use App\Repository\TagRepository;
use App\Repository\TipRepository;
use App\Repository\UsefulVoteRepository;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

final class TipController extends AbstractController
{
    /**
     * Écran d'entrée : tuiles par type de véhicule. Un seul type existe pour
     * l'instant (auto) — moto est affiché mais inerte, cf ROADMAP.md (v2).
     */
    #[Route('/tips', name: 'tip_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('tip/browse_vehicle_type.html.twig');
    }

    /** Tuiles des catégories primaires (moteur, freinage...) pour le véhicule "auto". */
    #[Route('/tips/auto', name: 'tip_browse_category', methods: ['GET'])]
    public function browseCategory(CategoryRepository $categoryRepository): Response
    {
        return $this->render('tip/browse_category.html.twig', [
            'categories' => $categoryRepository->findTopLevel(),
        ]);
    }

    /** Tuiles des opérations (distrib, purge de frein...) d'une catégorie primaire. */
    #[Route('/tips/auto/{category}', name: 'tip_browse_operation', methods: ['GET'])]
    public function browseOperation(string $category, CategoryRepository $categoryRepository): Response
    {
        $categoryEntity = $categoryRepository->findOneBySlug($category);

        if ($categoryEntity === null || $categoryEntity->getParent() !== null) {
            throw $this->createNotFoundException();
        }

        return $this->render('tip/browse_operation.html.twig', [
            'category' => $categoryEntity,
            'operations' => $categoryRepository->findChildren($categoryEntity),
        ]);
    }

    /** Liste des tips d'une opération précise, avec filtres secondaires (véhicule/type/tri). */
    #[Route('/tips/auto/{category}/{operation}', name: 'tip_list', methods: ['GET'])]
    public function list(
        string $category,
        string $operation,
        Request $request,
        TipRepository $tipRepository,
        CategoryRepository $categoryRepository,
        VehicleRepository $vehicleRepository,
    ): Response {
        $operationEntity = $categoryRepository->findOneBySlug($operation);

        if ($operationEntity === null || $operationEntity->getParent()?->getSlug() !== $category) {
            throw $this->createNotFoundException();
        }

        $vehicle = $this->findEntityFromQuery($request, 'vehicle', $vehicleRepository);
        $type = TipType::tryFromName((string) $request->query->get('type', ''));
        $sort = $request->query->get('sort') === 'useful' ? 'useful' : 'recent';

        return $this->render('tip/list.html.twig', [
            'category' => $operationEntity->getParent(),
            'operation' => $operationEntity,
            'tips' => $tipRepository->search($operationEntity, $vehicle, $type, null, $sort),
            'vehicles' => $vehicleRepository->findWithPublishedTips(),
            'types' => TipType::cases(),
            'filters' => [
                'vehicle' => $vehicle,
                'type' => $type,
                'sort' => $sort,
            ],
        ]);
    }

    /** Recherche libre à facettes, pour qui sait déjà ce qu'il cherche plutôt que de suivre le parcours par tuiles. */
    #[Route('/tips/recherche', name: 'tip_search', methods: ['GET'])]
    public function search(
        Request $request,
        TipRepository $tipRepository,
        CategoryRepository $categoryRepository,
        VehicleRepository $vehicleRepository,
    ): Response {
        $category = $this->findEntityFromQuery($request, 'category', $categoryRepository);
        $vehicle = $this->findEntityFromQuery($request, 'vehicle', $vehicleRepository);
        $type = TipType::tryFromName((string) $request->query->get('type', ''));
        $query = trim((string) $request->query->get('q', ''));
        $sort = $request->query->get('sort') === 'useful' ? 'useful' : 'recent';

        $hasFilters = $category !== null || $vehicle !== null || $type !== null || $query !== '';

        return $this->render('tip/search.html.twig', [
            'tips' => $tipRepository->search($category, $vehicle, $type, $query !== '' ? $query : null, $sort),
            'categories' => $categoryRepository->findOperations(),
            'vehicles' => $vehicleRepository->findWithPublishedTips(),
            'types' => TipType::cases(),
            'filters' => [
                'category' => $category,
                'vehicle' => $vehicle,
                'type' => $type,
                'q' => $query,
                'sort' => $sort,
            ],
            'hasFilters' => $hasFilters,
        ]);
    }

    /** Résout un filtre id= en entité, en ignorant silencieusement une valeur invalide plutôt que de planter la recherche. */
    private function findEntityFromQuery(Request $request, string $param, object $repository): ?object
    {
        $id = $request->query->get($param);

        if ($id === null || !ctype_digit((string) $id)) {
            return null;
        }

        return $repository->find((int) $id);
    }

    // priority plus basse que les routes littérales sous /tips/ (mine, new,
    // recherche, auto...) : sans ça, "/tips/mine" matcherait {slug} en
    // premier puisque cette route est déclarée avant elles dans la classe.
    #[Route('/tips/{slug}', name: 'tip_show', methods: ['GET'], priority: -1)]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])] Tip $tip,
        UsefulVoteRepository $usefulVoteRepository,
        FavoriteRepository $favoriteRepository,
    ): Response {
        if ($tip->getStatus() !== TipStatus::PUBLISHED) {
            throw $this->createNotFoundException();
        }

        $myVote = null;
        $isFavorited = false;
        $user = $this->getUser();
        if ($user instanceof User) {
            $vote = $usefulVoteRepository->findOneBy(['tip' => $tip, 'user' => $user]);
            $myVote = $vote?->isUseful();
            $isFavorited = $favoriteRepository->findOneBy(['tip' => $tip, 'user' => $user]) !== null;
        }

        return $this->render('tip/show.html.twig', [
            'tip' => $tip,
            'usefulCount' => $usefulVoteRepository->count(['tip' => $tip, 'useful' => true]),
            'notUsefulCount' => $usefulVoteRepository->count(['tip' => $tip, 'useful' => false]),
            'myVote' => $myVote,
            'isFavorited' => $isFavorited,
        ]);
    }

    #[Route('/tips/{id}/vote-useful', name: 'tip_vote_useful', methods: ['POST'], requirements: ['id' => '\d+'])]
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

            return $this->redirectToRoute('tip_show', ['slug' => $tip->getSlug()]);
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

        return $this->redirectToRoute('tip_show', ['slug' => $tip->getSlug()]);
    }

    #[Route('/tips/mine', name: 'tip_mine', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function mine(TipRepository $tipRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $items = [];
        foreach ($tipRepository->findByAuthor($user) as $tip) {
            $latestRevision = $tip->getRevisions()->last() ?: null;

            $rejectionReasons = [];
            if ($tip->getStatus() === TipStatus::REJECTED && $latestRevision) {
                foreach ($latestRevision->getVotes() as $vote) {
                    if ($vote->getDecision() === VoteDecision::AGAINST && $vote->getComment()) {
                        $rejectionReasons[] = $vote->getComment();
                    }
                }
            }

            $items[] = [
                'tip' => $tip,
                'title' => $tip->getPublishedTitle() ?? $latestRevision?->getTitle() ?? '(sans titre)',
                'rejectionReasons' => $rejectionReasons,
            ];
        }

        return $this->render('tip/mine.html.twig', [
            'items' => $items,
        ]);
    }

    #[Route('/tips/new', name: 'tip_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        VehicleRepository $vehicleRepository,
        TagRepository $tagRepository,
        SluggerInterface $slugger,
        #[Target('tip_submission')] RateLimiterFactoryInterface $tipSubmissionLimiter,
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

                // Anti-spam (ROADMAP.md) : un jeton consommé par soumission
                // effectivement créée, pas par tentative — une erreur de
                // validation corrigée puis renvoyée ne doit pas coûter de
                // quota, seule la création d'un Tip compte comme soumission.
                if (!$tipSubmissionLimiter->create((string) $user->getId())->consume(1)->isAccepted()) {
                    $this->addFlash('error', 'Tu as atteint la limite de soumissions par heure, réessaie un peu plus tard.');

                    return $this->render('tip/new.html.twig', [
                        'tipForm' => $form,
                    ]);
                }

                // Un véhicule saisi fait foi même si la case "tous véhicules"
                // est restée cochée (elle est cochée par défaut et masque le
                // champ tant qu'on ne la décoche pas : un texte présent est un
                // signal non ambigu, contrairement à l'inverse).
                $vehicle = $vehicleLabel !== '' ? $this->resolveVehicle($vehicleLabel, $user, $vehicleRepository, $entityManager) : null;

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
