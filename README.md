# MécaTips

MécaTips n'est pas un énième site de tutos mécaniques. Ce qu'il recense, ce
sont les **tips de terrain** : les conseils qui se transmettent à l'oral dans
un garage, entre un ancien du métier et un apprenti — le genre d'info qu'on
ne trouve jamais dans un tuto structuré, mais qui fait toute la différence
sur une intervention. Chaque tip soumis passe par un comité de mécaniciens
de confiance avant publication.

**Direction artistique** : ton "brut / vécu" — fiche d'atelier légèrement
inclinée, papier grainé, tampon encreur pour les badges de progression,
annotation manuscrite réservée à de courts fragments. Vision produit
complète, modèle collaboratif et détails de la DA dans
[`ROADMAP.md`](ROADMAP.md).

## État du projet

- ✅ Authentification, rôles (`ROLE_USER`/`ROLE_COMMITTEE`/`ROLE_ADMIN`), mot de passe oublié
- ✅ Soumission de tip (autocomplete opération/véhicule, anti-spam) et vote à quorum du comité
- ✅ Modification d'un tip publié — repasse en validation, ancienne version visible entre-temps
- ✅ Parcours par tuiles (véhicule → catégorie → opération) et recherche libre à facettes
- ✅ Fiche tip, page véhicule, profil public contributeur, classement
- ✅ Signalement d'un tip publié + file de traitement comité
- ✅ Gestion de la base véhicules (validation, dédoublonnage/fusion)
- ✅ Favoris, notifications in-app, SEO (sitemap, robots, meta, Open Graph)
- ✅ Suite de tests automatisés (PHPUnit) + CI GitHub Actions

Voir [`CHANGELOG.md`](CHANGELOG.md) pour le détail des évolutions.

## Stack

- Symfony 8.1, PHP 8.4, PostgreSQL 17, Redis, FrankenPHP en cible d'exécution
- Twig + AssetMapper (pas de bundler npm/Node), Stimulus/Turbo (Symfony UX)
- [`symfony/ux-autocomplete`](https://symfony.com/bundles/ux-autocomplete/current/index.html) (TomSelect) pour les champs opération/véhicule du formulaire de tip
- **Convention de code** : entités/colonnes/enums/URIs en anglais, seuls les écrans visibles par les utilisateurs sont en français.

## Prérequis

- Docker et Docker Compose
- L'infra Docker partagée [`symfony_env`](../symfony_env) (Traefik + PostgreSQL + Redis + Mailpit), utilisée par plusieurs projets Symfony

> Le `DATABASE_URL`/`REDIS_URL` de dev pointent sur les hostnames internes
> du réseau Docker `symfony_env` (`symfony_env_postgresql`, ...) —
> contrairement à d'autres projets de la stack, MécaTips ne peut donc pas
> tourner en PHP hôte nu (`symfony serve`) sans passer par Docker.

## Développement

```bash
# 1. Démarrer symfony_env si ce n'est pas déjà fait (depuis son propre dossier)
cd ../symfony_env && make up

# 2. Créer la base dédiée à ce projet (une fois)
make db-create

# 3. Créer .env.local (non versionné) avec les DSN partagés
#    DATABASE_URL="postgresql://<POSTGRES_USER>:<POSTGRES_PASSWORD>@symfony_env_postgresql:5432/mecatips?serverVersion=17&charset=utf8"
#    REDIS_URL="redis://:<REDIS_PASSWORD>@symfony_env_redis:6379"
#    MAILER_DSN="smtp://symfony_env_mailpit:1025"

# 4. Démarrer le conteneur de l'app (construit l'image au besoin)
make build
make up
```

Le site est alors accessible sur https://mecatips.localhost (domaine par
défaut si `APP_DOMAIN` n'est pas défini — inutile de créer un `.env.docker`
en local, seule la production a besoin d'un vrai domaine).

> ⚠️ Le `docker-proxy` de `symfony_env` n'a pas la permission `EVENTS` :
> Traefik ne détecte pas à chaud la création/recréation du conteneur
> `mecatips_app`. Après un `make build`/`up`, relancer Traefik une fois :
> `make traefik-restart`.

Un `Makefile` centralise les commandes courantes — `make help` liste tout.
Utiles au quotidien : `make sh` (shell dans le conteneur), `make console
cmd="..."` (`bin/console` dans le conteneur), `make db-fixtures` (vide puis
repeuple la base avec un jeu de données complet — comptes de test à tous les
rôles, tips à tous les statuts, mot de passe unique `password123`),
`make logs`.

## Tests

```bash
make db-test-create   # une fois : crée la base mecatips_test
make test             # migre le schéma de test puis lance PHPUnit
```

La suite tourne en isolation transactionnelle
(`dama/doctrine-test-bundle` : rollback automatique après chaque test, la
base `mecatips_test` reste propre d'un test à l'autre). `make test` force
`APP_ENV=test` comme variable d'environnement réelle du conteneur — le
`Dockerfile` fige `APP_ENV=dev` au niveau de l'image, ce qui prime
silencieusement sur la seule directive `<server>` de `phpunit.dist.xml`
sinon.

La CI (`.github/workflows/ci.yml`) reproduit ce même scénario contre des
services Postgres 17/Redis éphémères à chaque push/PR sur `main`, en plus
d'un job de lint (syntaxe PHP, Twig, YAML, container Symfony,
`composer validate`).

## Déploiement en production (VPS, infra `symfony_env`)

Le `Dockerfile` est multi-stage : `frankenphp_prod` intègre le code, les
dépendances Composer (`--no-dev`) et les assets compilés directement dans
l'image au build — pas de bind-mount, exécution en utilisateur non-root.
`compose.yaml` seul (sans `compose.override.yaml`, qui bascule sur le stage
dev) est donc déjà prod-safe par défaut ; `compose.prod.yaml` ajoute les
limites de ressources et injecte `.env.local` (non versionné, présent sur le
VPS mais jamais copié dans l'image) comme variables d'environnement du
conteneur.

### Prérequis

- `symfony_env` déjà déployé **en mode production** sur le VPS (domaine
  réel, Let's Encrypt, réseau Docker `symfony_env` créé) — voir son propre
  README. Ce projet ne fait que rejoindre ce réseau, il ne démarre pas
  l'infra partagée.
- Un sous-domaine DNS (`mecatips.petroslabs.dev`) qui pointe vers l'IP du VPS.

### Premier déploiement

```bash
# Sur le VPS
git clone <url-du-repo> mecatips
cd mecatips

# 1. Créer la base dédiée (une fois, depuis symfony_env)
cd ../symfony_env && make db-create name=mecatips
cd ../mecatips

# 2. .env.local (non versionné) avec les vraies valeurs
#    DATABASE_URL="postgresql://<user>:<mdp affiché par db-create>@symfony_env_postgresql:5432/mecatips?serverVersion=17&charset=utf8"
#    REDIS_URL="redis://:<REDIS_PASSWORD>@symfony_env_redis:6379"
#    MAILER_DSN="<vrai SMTP>"
#    APP_SECRET=<valeur forte, ex: openssl rand -hex 16>

# 3. .env.docker (non versionné, copié depuis .env.docker.example) avec le vrai domaine
cp .env.docker.example .env.docker
#    APP_DOMAIN=mecatips.petroslabs.dev

# 4. Build → migrations → démarrage
make deploy-prod

# 5. Premier démarrage : Traefik ne détecte pas le nouveau conteneur à chaud
#    (limitation docker-proxy de symfony_env, cf. plus haut)
make traefik-restart
```

Le site est alors accessible sur `https://mecatips.petroslabs.dev`.

### Devenir administrateur

Pas d'interface pour attribuer `ROLE_ADMIN` (volontairement — trop
sensible pour une UI, contrairement à `ROLE_COMMITTEE` qui se gère depuis
`/admin/committee` une fois un premier admin en place). Créer un compte
normal via `/register`, puis en base :

```sql
UPDATE app_user SET roles = '["ROLE_ADMIN"]' WHERE email = 'toi@exemple.com';
```

### Déploiements suivants

```bash
git pull
make deploy-prod   # build (nouvelle image) → migrations → redémarrage du conteneur
```

### Autres commandes

`make build-prod` / `make up-prod` / `make down-prod` / `make logs-prod` /
`make migrate-prod` (déploiement étape par étape plutôt que
`make deploy-prod`).

## Structure

```
Makefile                       # Commandes de dev/build/Docker/test/prod (make help)
Dockerfile                     # Image multi-stage (frankenphp_prod / frankenphp_dev)
compose.yaml                   # Service app (prod par défaut) + intégration Traefik
compose.override.yaml          # Overrides dev (auto-chargés) : stage dev + bind-mount du code
compose.prod.yaml              # Overrides production : limites de ressources + injection .env.local
migrations/                    # Migrations Doctrine (schéma + données : catégories système, slugs...)
src/Entity/                    # Tip, TipRevision, CommitteeVote, User, Category, Vehicle, Tag, Report, Favorite, UsefulVote, Notification, ResetPasswordRequest
src/Enum/                      # TipType, TipStatus, RevisionStatus, VoteDecision, VehicleStatus, ReportStatus, NotificationType, ContributorBadge
src/Doctrine/Types/            # Types Doctrine custom : persistent le name (anglais) des enums, pas leur value (libellé français)
src/Repository/                # Un repository par entité
src/Service/                   # TipReviewService (vote à quorum), ContributorRankingService (score + badges)
src/Form/                      # TipFormType, RegistrationFormType, ResetPasswordRequestFormType, ChangePasswordFormType
src/Controller/                # HomeController, TipController, CommitteeController, VehicleController, VehicleModerationController, ReportController, FavoriteController, NotificationController, RankingController, ContributorController, RegistrationController, SecurityController, ResetPasswordController, SeoController
src/Controller/Admin/          # CommitteeMemberController (attribution/retrait de ROLE_COMMITTEE)
src/DataFixtures/              # Jeu de données de dev (make db-fixtures)
src/Twig/                      # NotificationExtension (compteur de non-lues, disponible sur toutes les pages)
tests/Service/                 # Tests unitaires (vote à quorum)
tests/Functional/              # Tests fonctionnels HTTP (auth, soumission, contrôle d'accès, favoris/signalement/fusion)
templates/
├── base.html.twig             # Layout commun, nav, thème clair/sombre, meta SEO
├── tip/                       # Parcours par tuiles, recherche, fiche détail, soumission, mes tips
├── committee/                 # File de validation, signalements, gestion véhicules
├── vehicle/                   # Page véhicule
├── contributor/               # Profil public
├── ranking/                   # Classement
├── favorite/                  # Liste des favoris
├── notification/              # Liste des notifications
├── security/                  # Connexion, inscription
├── reset_password/            # Mot de passe oublié
├── admin/                     # Gestion des membres du comité
└── sitemap/                   # Template XML du sitemap
assets/styles/                 # Tokens DA "brut/vécu" (palette, polices, textures)
assets/controllers/            # Contrôleurs Stimulus (thème, autocomplete...)
public/robots.txt              # Autorise l'indexation, référence le sitemap
```

## SEO

- `/robots.txt` et `/sitemap.xml` générés dynamiquement (`SeoController`,
  jamais de domaine en dur — construits depuis la requête courante). Le
  sitemap dérive les opérations/véhicules/contributeurs à lister
  directement des tips publiés.
- Meta description, `<link rel="canonical">` et Open Graph sur chaque page,
  avec description spécifique sur fiche tip, page d'opération, page
  véhicule et profil contributeur. Le canonical ignore la querystring
  (recherche libre, filtres).
- Pages transactionnelles (connexion, inscription, soumission, mes tips,
  comité, admin) en `noindex, nofollow`.

## Modèle collaboratif

- **Lecture** : accès libre à tous les tips publiés, sans compte.
- **Soumission** : réservée aux comptes connectés. Passe par le comité
  (`ROLE_COMMITTEE`, invités nommément — pas de candidature ouverte) :
  vote pour/contre par membre, commentaire obligatoire en cas de contre,
  quorum de 3 votes avant de trancher (égalité = on attend un vote de
  plus).
- **Modification d'un tip publié** : repasse en validation, l'ancienne
  version reste visible pendant le vote — jamais de dépublication le temps
  de la révision.
- **Signalement** : tout compte connecté peut signaler un tip publié
  (obsolète, erroné, dangereux), traité par le comité.

Détail complet du modèle et de la vision produit dans
[`ROADMAP.md`](ROADMAP.md).

## Licence

Propriétaire.
