# =============================================================================
# MécaTips — Makefile
# =============================================================================

.DEFAULT_GOAL := help

# Colors
GREEN  := \033[0;32m
YELLOW := \033[0;33m
CYAN   := \033[0;36m
RESET  := \033[0m

## —— MécaTips ————————————————————————————————————————————————————————————————

.PHONY: help
help: ## Afficher cette aide
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$|^## )' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "} /^## /{printf "\n$(YELLOW)%s$(RESET)\n", substr($$0,4)} /^[a-zA-Z_-]+/{printf "  $(CYAN)%-20s$(RESET) %s\n", $$1, $$2}'

## —— Installation ————————————————————————————————————————————————————————————

.PHONY: install
install: ## Installer les dépendances (Composer + AssetMapper)
	composer install

## —— Développement (PHP hôte) ————————————————————————————————————————————————

.PHONY: serve
serve: ## Démarrer le serveur de dev (Symfony CLI, http://localhost:8000)
	symfony serve

.PHONY: cache-clear
cache-clear: ## Vider le cache Symfony
	php bin/console cache:clear

.PHONY: lint
lint: ## Vérifier la syntaxe des templates Twig et des fichiers YAML
	php bin/console lint:twig templates
	php bin/console lint:yaml config translations

## —— Docker (infra symfony_env, dev) ——————————————————————————————————————————
# compose.override.yaml (stage dev + bind-mount) est auto-chargé tant qu'on
# ne passe pas -f explicitement — voir la section Production plus bas.

DOCKER_COMP = docker compose

.PHONY: build
build: ## (Re)construire l'image de l'app (dev)
	$(DOCKER_COMP) build

.PHONY: up
up: ## Démarrer le conteneur de l'app (dev)
	$(DOCKER_COMP) up -d

.PHONY: down
down: ## Arrêter le conteneur de l'app
	$(DOCKER_COMP) down

.PHONY: restart
restart: down up ## Redémarrer le conteneur de l'app (dev)

.PHONY: ps
ps: ## Afficher l'état du conteneur
	$(DOCKER_COMP) ps

.PHONY: logs
logs: ## Afficher les logs du conteneur (mode suivi)
	$(DOCKER_COMP) logs -f

.PHONY: sh
sh: ## Ouvrir un shell dans le conteneur de l'app
	$(DOCKER_COMP) exec app sh

.PHONY: console
console: ## Exécuter une commande bin/console dans le conteneur (usage : make console cmd="cache:clear")
	@if [ -z "$(cmd)" ]; then \
		echo "$(YELLOW)Usage: make console cmd=\"cache:clear\"$(RESET)"; \
		exit 1; \
	fi
	$(DOCKER_COMP) exec app php bin/console $(cmd)

.PHONY: traefik-restart
traefik-restart: ## Relancer Traefik (symfony_env) après un up/build — le docker-proxy partagé n'a pas la permission EVENTS
	cd ../symfony_env && docker compose restart traefik

## —— Base de données (PostgreSQL partagé, symfony_env) ————————————————————————

.PHONY: db-create
db-create: ## Créer la base 'mecatips' sur le PostgreSQL partagé
	cd ../symfony_env && $(MAKE) db-create name=mecatips

.PHONY: db-drop
db-drop: ## Supprimer la base 'mecatips' sur le PostgreSQL partagé
	cd ../symfony_env && $(MAKE) db-drop name=mecatips

.PHONY: db-fixtures
db-fixtures: ## ⚠️  VIDE la base puis la repeuple avec un jeu de données complet (comptes de test, tips à tous les statuts) — local uniquement
	$(DOCKER_COMP) exec app php bin/console doctrine:fixtures:load --no-interaction

.PHONY: db-test-create
db-test-create: ## Créer la base 'mecatips_test' (même rôle 'mecatips' que la base de dev, pas un nouveau — juste dbname_suffix "_test")
	cd ../symfony_env && docker compose exec postgresql sh -c "psql -U \$$POSTGRES_USER -d postgres -v ON_ERROR_STOP=1 -c \"CREATE DATABASE mecatips_test OWNER mecatips;\""

.PHONY: test
test: ## Lance la suite de tests (migre d'abord le schéma de la base de test)
	$(DOCKER_COMP) exec -e APP_ENV=test app php bin/console doctrine:migrations:migrate --no-interaction
	$(DOCKER_COMP) exec -e APP_ENV=test app php bin/phpunit
	@echo ""
	@echo "$(GREEN)✅ Base repeuplée — mot de passe pour tous les comptes de test : password123$(RESET)"
	@echo "$(CYAN)   admin@mecatips.test (admin) · amara/bruno/chloe@mecatips.test (comité)$(RESET)"
	@echo "$(CYAN)   julie/thomas/yasmine/paul@mecatips.test (contributeurs)$(RESET)"
	@echo ""

## —— Docker (production, VPS) —————————————————————————————————————————————————
# Les -f explicites désactivent l'auto-chargement de compose.override.yaml
# (dev) — cf. .env.docker.example à copier en .env.docker sur le VPS.

DOCKER_COMP_PROD = docker compose --env-file .env.docker -f compose.yaml -f compose.prod.yaml

.PHONY: build-prod
build-prod: ## (Re)construire l'image de l'app (prod : code intégré, assets compilés)
	$(DOCKER_COMP_PROD) build

.PHONY: up-prod
up-prod: ## Démarrer le conteneur de l'app (prod)
	$(DOCKER_COMP_PROD) up -d

.PHONY: down-prod
down-prod: ## Arrêter le conteneur de l'app (prod)
	$(DOCKER_COMP_PROD) down

.PHONY: logs-prod
logs-prod: ## Afficher les logs du conteneur (prod, mode suivi)
	$(DOCKER_COMP_PROD) logs -f

.PHONY: migrate-prod
migrate-prod: ## Lancer les migrations Doctrine en production (à exécuter avant up-prod)
	$(DOCKER_COMP_PROD) run --rm app php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

.PHONY: deploy-prod
deploy-prod: build-prod migrate-prod up-prod ## Déploiement complet : build → migrate → up
	@echo ""
	@echo "$(GREEN)✅ Déploiement production terminé$(RESET)"
	@echo ""
