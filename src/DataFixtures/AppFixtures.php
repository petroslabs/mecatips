<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\CommitteeVote;
use App\Entity\Tag;
use App\Entity\Tip;
use App\Entity\TipRevision;
use App\Entity\UsefulVote;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\RevisionStatus;
use App\Enum\TipStatus;
use App\Enum\TipType;
use App\Enum\VehicleStatus;
use App\Enum\VoteDecision;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Jeu de données complet pour le développement local : un exemplaire de
 * chaque état que l'appli peut produire (comptes de tous les rôles, tips en
 * attente / publiés / refusés, y compris une modification en cours et une
 * modification refusée sur des tips déjà publiés — le seul moyen de tester
 * ce chemin tant qu'il n'y a pas d'écran d'édition).
 *
 * `make db-fixtures` VIDE la base avant de la repeupler (comportement par
 * défaut de doctrine:fixtures:load) — réservé au local, jamais en prod.
 *
 * Mot de passe unique pour tous les comptes : voir PASSWORD ci-dessous.
 */
final class AppFixtures extends Fixture
{
    private const string PASSWORD = 'password123';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $categories = $this->loadCategories($manager);
        $users = $this->loadUsers($manager);
        $vehicles = $this->loadVehicles($manager, $users);
        $tags = $this->loadTags($manager);

        $this->loadTips($manager, $categories, $users, $vehicles, $tags);

        $manager->flush();
    }

    /** @return array<string, Category> slug => Category */
    private function loadCategories(ObjectManager $manager): array
    {
        $definitions = [
            ['Moteur', 'engine'],
            ['Freinage', 'brakes'],
            ['Suspension / Direction', 'suspension-steering'],
            ['Transmission / Boîte', 'transmission-gearbox'],
            ['Électrique / Électronique', 'electrical-electronics'],
            ['Climatisation', 'air-conditioning'],
            ['Carrosserie', 'bodywork'],
            ['Outillage / Méthode générale', 'tooling-general-methods'],
        ];

        $categories = [];
        foreach ($definitions as [$name, $slug]) {
            $category = (new Category())->setName($name)->setSlug($slug);
            $manager->persist($category);
            $categories[$slug] = $category;
        }

        return $categories;
    }

    /** @return array<string, User> username => User */
    private function loadUsers(ObjectManager $manager): array
    {
        // username => [email, roles]
        $definitions = [
            'admin' => ['admin@mecatips.test', ['ROLE_ADMIN']],
            'amara_comite' => ['amara@mecatips.test', ['ROLE_COMMITTEE']],
            'bruno_comite' => ['bruno@mecatips.test', ['ROLE_COMMITTEE']],
            'chloe_comite' => ['chloe@mecatips.test', ['ROLE_COMMITTEE']],
            // Contributrice prolifique : plusieurs tips publiés, dont un avec
            // une modification en cours et un avec une modification refusée.
            'julie_mecano' => ['julie@mecatips.test', []],
            // Un tip publié, un refusé (première soumission), un en attente.
            'thomas_garage' => ['thomas@mecatips.test', []],
            // Un seul tip, encore en attente — jamais passé devant le comité.
            'yasmine_atelier' => ['yasmine@mecatips.test', []],
            // Compte tout neuf, aucune activité — pour tester les états vides.
            'paul_debutant' => ['paul@mecatips.test', []],
        ];

        $users = [];
        foreach ($definitions as $username => [$email, $roles]) {
            $user = (new User())
                ->setUsername($username)
                ->setEmail($email)
                ->setRoles($roles);
            $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));
            $manager->persist($user);
            $users[$username] = $user;
        }

        return $users;
    }

    /**
     * @param array<string, User> $users
     *
     * @return array<string, Vehicle>
     */
    private function loadVehicles(ObjectManager $manager, array $users): array
    {
        $definitions = [
            'golf4-tdi' => ['Volkswagen Golf 4 1.9 TDI PD', VehicleStatus::VALIDATED],
            'clio4-dci' => ['Renault Clio 4 1.5 dCi', VehicleStatus::VALIDATED],
            '308-hdi' => ['Peugeot 308 1.6 HDi', VehicleStatus::PENDING],
        ];

        $vehicles = [];
        foreach ($definitions as $key => [$label, $status]) {
            $vehicle = (new Vehicle())
                ->setLabel($label)
                ->setStatus($status)
                ->setProposedBy($users['julie_mecano']);
            $manager->persist($vehicle);
            $vehicles[$key] = $vehicle;
        }

        return $vehicles;
    }

    /** @return array<string, Tag> */
    private function loadTags(ObjectManager $manager): array
    {
        $labels = ['distribution', 'vidange', 'freins', 'démarrage à froid', 'climatisation'];

        $tags = [];
        foreach ($labels as $label) {
            $slug = strtolower((string) $this->slugger->slug($label));
            $tag = (new Tag())->setLabel($label)->setSlug($slug);
            $manager->persist($tag);
            $tags[$label] = $tag;
        }

        return $tags;
    }

    /**
     * @param array<string, Category> $categories
     * @param array<string, User>     $users
     * @param array<string, Vehicle>  $vehicles
     * @param array<string, Tag>      $tags
     */
    private function loadTips(ObjectManager $manager, array $categories, array $users, array $vehicles, array $tags): void
    {
        // ---- Tip 1 : publié, + une modification en cours (pas encore tranchée) ----
        $tip1 = $this->publishedTip(
            manager: $manager,
            users: $users,
            author: 'julie_mecano',
            category: $categories['engine'],
            type: TipType::PITFALL,
            vehicle: $vehicles['golf4-tdi'],
            title: 'Toujours piger le moteur avant la distribution',
            content: "Cale le moteur au point mort haut du cylindre 1 avec des piges (ou l'outillage constructeur) avant de démonter la courroie ou la chaîne. Sans ça, la synchronisation arbre à cames / vilebrequin peut décaler au remontage — sur un moteur à interférence, ça plie des soupapes.",
            tags: [$tags['distribution']],
            approvers: ['amara_comite', 'bruno_comite', 'chloe_comite'],
            usefulBy: ['paul_debutant', 'thomas_garage', 'yasmine_atelier'],
            notUsefulBy: [],
        );
        $this->addPendingRevision(
            manager: $manager,
            users: $users,
            tip: $tip1,
            title: 'Toujours piger le moteur avant la distribution (précision calage)',
            content: "Cale le moteur au point mort haut du cylindre 1 avec des piges avant de démonter la courroie ou la chaîne. Précision utile : sur ce moteur, le repère de calage arbre à cames se trouve sur la poulie côté volant moteur, pas sur le carter — beaucoup le cherchent au mauvais endroit.",
            votesFor: ['bruno_comite'],
            votesAgainst: [],
        );

        // ---- Tip 2 : publié, + une modification refusée (la version publiée ne bouge pas) ----
        $tip2 = $this->publishedTip(
            manager: $manager,
            users: $users,
            author: 'julie_mecano',
            category: $categories['engine'],
            type: TipType::PREVENTION,
            vehicle: null,
            title: 'Change la pompe à eau tant que tu y es',
            content: "Sur une distribution, changer la pompe à eau en même temps ne coûte presque rien et évite de tout redémonter si elle lâche plus tard.",
            tags: [$tags['distribution'], $tags['vidange']],
            approvers: ['amara_comite', 'bruno_comite', 'chloe_comite'],
            usefulBy: ['paul_debutant', 'yasmine_atelier'],
            notUsefulBy: [],
        );
        $this->addRejectedRevision(
            manager: $manager,
            users: $users,
            tip: $tip2,
            title: 'Change la pompe à eau et le galet tendeur',
            content: "Ajout : change aussi le galet tendeur en même temps, il s'use au même rythme que la pompe à eau.",
            comments: [
                'amara_comite' => 'Déjà sous-entendu dans le tip original, pas assez nouveau pour justifier une révision.',
                'bruno_comite' => "Le tip d'origine est plus court et va à l'essentiel, cet ajout le dilue.",
                'chloe_comite' => 'À proposer plutôt comme un tip séparé sur le galet tendeur.',
            ],
        );

        // ---- Tip 3 : publié, avis mitigé (score d'utilité net = 0) ----
        $this->publishedTip(
            manager: $manager,
            users: $users,
            author: 'julie_mecano',
            category: $categories['air-conditioning'],
            type: TipType::ADVICE,
            vehicle: null,
            title: "Recharge la clim avant l'été",
            content: "Fais vérifier et recharger le circuit de clim au printemps plutôt qu'en pleine canicule : les garages sont moins débordés et tu évites l'attente.",
            tags: [$tags['climatisation']],
            approvers: ['amara_comite', 'bruno_comite', 'chloe_comite'],
            usefulBy: ['thomas_garage'],
            notUsefulBy: ['paul_debutant'],
        );

        // ---- Tip 4 : publié (Thomas) ----
        $this->publishedTip(
            manager: $manager,
            users: $users,
            author: 'thomas_garage',
            category: $categories['brakes'],
            type: TipType::PITFALL,
            vehicle: $vehicles['clio4-dci'],
            title: 'Ne jamais réutiliser les vis de disque de frein',
            content: "Les vis de fixation des disques de frein sont à usage unique sur la plupart des véhicules récents — le couple de serrage n'est plus garanti si tu les réutilises telles quelles.",
            tags: [$tags['freins']],
            approvers: ['amara_comite', 'bruno_comite', 'chloe_comite'],
            usefulBy: ['julie_mecano'],
            notUsefulBy: [],
        );

        // ---- Tip 5 : refusé dès la première soumission (Thomas) ----
        $this->rejectedTip(
            manager: $manager,
            users: $users,
            author: 'thomas_garage',
            category: $categories['bodywork'],
            type: TipType::ADVICE,
            vehicle: null,
            title: 'Poncer la rouille avant de repeindre',
            content: 'Un coup de ponceuse rapide suffit avant de repeindre une zone rouillée.',
            comments: [
                'amara_comite' => 'Trop vague : un ponçage rapide ne suffit pas si la rouille est profonde, risque de repeindre sur de la corrosion active.',
                'bruno_comite' => 'Manque un traitement anti-rouille avant peinture, sinon ça revient vite.',
                'chloe_comite' => 'Pas assez détaillé pour être vraiment utile tel quel.',
            ],
        );

        // ---- Tip 6 : en attente, aucun vote encore (Thomas) ----
        $this->pendingTip(
            manager: $manager,
            users: $users,
            author: 'thomas_garage',
            category: $categories['electrical-electronics'],
            type: TipType::TOOLING,
            vehicle: null,
            title: 'Utiliser un multimètre pour tester la batterie avant de la changer',
            content: "Avant d'acheter une batterie neuve, teste la tension à froid avec un multimètre : en dessous de 12.4V à l'arrêt, il y a un vrai doute, mais ça vaut le coup de vérifier la charge avant de dépenser.",
            votesFor: [],
        );

        // ---- Tip 7 : en attente, un vote pour déjà exprimé (Yasmine) ----
        $this->pendingTip(
            manager: $manager,
            users: $users,
            author: 'yasmine_atelier',
            category: $categories['transmission-gearbox'],
            type: TipType::ADVICE,
            vehicle: $vehicles['308-hdi'],
            title: 'Vérifier le niveau de boîte tous les 60 000 km',
            content: "Sur ce modèle, le niveau d'huile de boîte manuelle est rarement contrôlé en révision standard — à vérifier soi-même tous les 60 000 km.",
            votesFor: ['amara_comite'],
        );
    }

    /**
     * @param array<string, User> $users
     * @param list<Tag>           $tags
     * @param list<string>        $approvers   usernames comité, votent tous "pour"
     * @param list<string>        $usefulBy    usernames, votent utile
     * @param list<string>        $notUsefulBy usernames, votent pas utile
     */
    private function publishedTip(
        ObjectManager $manager,
        array $users,
        string $author,
        Category $category,
        TipType $type,
        ?Vehicle $vehicle,
        string $title,
        string $content,
        array $tags,
        array $approvers,
        array $usefulBy,
        array $notUsefulBy,
    ): Tip {
        $tip = (new Tip())
            ->setAuthor($users[$author])
            ->setCategory($category)
            ->setType($type)
            ->setVehicle($vehicle)
            ->setPublishedTitle($title)
            ->setPublishedContent($content)
            ->setStatus(TipStatus::PUBLISHED)
            ->setPublishedAt(new \DateTimeImmutable());

        foreach ($tags as $tag) {
            $tip->addTag($tag);
        }

        $revision = (new TipRevision())
            ->setTitle($title)
            ->setContent($content)
            ->setStatus(RevisionStatus::APPROVED)
            ->setReviewedAt(new \DateTimeImmutable());
        $tip->addRevision($revision);

        $manager->persist($tip);
        $manager->persist($revision);

        foreach ($approvers as $username) {
            $vote = (new CommitteeVote())->setMember($users[$username])->setDecision(VoteDecision::FOR);
            $revision->addVote($vote);
            $manager->persist($vote);
        }

        foreach ($usefulBy as $username) {
            $manager->persist((new UsefulVote())->setTip($tip)->setUser($users[$username])->setUseful(true));
        }

        foreach ($notUsefulBy as $username) {
            $manager->persist((new UsefulVote())->setTip($tip)->setUser($users[$username])->setUseful(false));
        }

        return $tip;
    }

    /** @param array<string, User> $users */
    private function rejectedTip(
        ObjectManager $manager,
        array $users,
        string $author,
        Category $category,
        TipType $type,
        ?Vehicle $vehicle,
        string $title,
        string $content,
        array $comments,
    ): Tip {
        $tip = (new Tip())
            ->setAuthor($users[$author])
            ->setCategory($category)
            ->setType($type)
            ->setVehicle($vehicle)
            ->setStatus(TipStatus::REJECTED);

        $revision = (new TipRevision())
            ->setTitle($title)
            ->setContent($content)
            ->setStatus(RevisionStatus::REJECTED)
            ->setReviewedAt(new \DateTimeImmutable());
        $tip->addRevision($revision);

        $manager->persist($tip);
        $manager->persist($revision);

        foreach ($comments as $username => $comment) {
            $vote = (new CommitteeVote())
                ->setMember($users[$username])
                ->setDecision(VoteDecision::AGAINST)
                ->setComment($comment);
            $revision->addVote($vote);
            $manager->persist($vote);
        }

        return $tip;
    }

    /**
     * @param array<string, User> $users
     * @param list<string>        $votesFor usernames comité, votent "pour" (quorum pas forcément atteint)
     */
    private function pendingTip(
        ObjectManager $manager,
        array $users,
        string $author,
        Category $category,
        TipType $type,
        ?Vehicle $vehicle,
        string $title,
        string $content,
        array $votesFor,
    ): Tip {
        $tip = (new Tip())
            ->setAuthor($users[$author])
            ->setCategory($category)
            ->setType($type)
            ->setVehicle($vehicle)
            ->setStatus(TipStatus::PENDING);

        $revision = (new TipRevision())
            ->setTitle($title)
            ->setContent($content)
            ->setStatus(RevisionStatus::PENDING);
        $tip->addRevision($revision);

        $manager->persist($tip);
        $manager->persist($revision);

        foreach ($votesFor as $username) {
            $vote = (new CommitteeVote())->setMember($users[$username])->setDecision(VoteDecision::FOR);
            $revision->addVote($vote);
            $manager->persist($vote);
        }

        return $tip;
    }

    /**
     * Ajoute une révision "modification" en attente sur un tip déjà publié —
     * la version publiée reste inchangée tant que celle-ci n'est pas tranchée.
     *
     * @param array<string, User> $users
     * @param list<string>        $votesFor
     * @param list<string>        $votesAgainst
     */
    private function addPendingRevision(
        ObjectManager $manager,
        array $users,
        Tip $tip,
        string $title,
        string $content,
        array $votesFor,
        array $votesAgainst,
    ): void {
        $revision = (new TipRevision())
            ->setTitle($title)
            ->setContent($content)
            ->setStatus(RevisionStatus::PENDING);
        $tip->addRevision($revision);

        $manager->persist($revision);

        foreach ($votesFor as $username) {
            $vote = (new CommitteeVote())->setMember($users[$username])->setDecision(VoteDecision::FOR);
            $revision->addVote($vote);
            $manager->persist($vote);
        }

        foreach ($votesAgainst as $username) {
            $vote = (new CommitteeVote())->setMember($users[$username])->setDecision(VoteDecision::AGAINST);
            $revision->addVote($vote);
            $manager->persist($vote);
        }
    }

    /**
     * Ajoute une révision "modification" refusée sur un tip déjà publié —
     * démontre que le rejet d'une modification laisse la version publiée
     * intacte.
     *
     * @param array<string, User>   $users
     * @param array<string, string> $comments username => commentaire (vote contre)
     */
    private function addRejectedRevision(
        ObjectManager $manager,
        array $users,
        Tip $tip,
        string $title,
        string $content,
        array $comments,
    ): void {
        $revision = (new TipRevision())
            ->setTitle($title)
            ->setContent($content)
            ->setStatus(RevisionStatus::REJECTED)
            ->setReviewedAt(new \DateTimeImmutable());
        $tip->addRevision($revision);

        $manager->persist($revision);

        foreach ($comments as $username => $comment) {
            $vote = (new CommitteeVote())
                ->setMember($users[$username])
                ->setDecision(VoteDecision::AGAINST)
                ->setComment($comment);
            $revision->addVote($vote);
            $manager->persist($vote);
        }
    }
}
