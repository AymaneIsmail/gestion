.PHONY: help build up down shell composer-install db-reset fixtures

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

build: ## Build les images Docker
	docker compose build

up: ## Démarre les conteneurs
	docker compose up -d

down: ## Arrête les conteneurs
	docker compose down

shell: ## Ouvre un shell dans le conteneur PHP
	docker compose exec php sh

composer-install: ## Installe les dépendances Composer
	docker compose exec php composer install

db-create: ## Crée la base de données
	docker compose exec php php bin/console doctrine:database:create --if-not-exists

db-migrate: ## Exécute les migrations
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

db-reset: ## Recrée la BDD et exécute les migrations
	docker compose exec php php bin/console doctrine:database:drop --force --if-exists
	docker compose exec php php bin/console doctrine:database:create
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

fixtures: ## Charge les fixtures (données de test)
	docker compose exec php php bin/console doctrine:fixtures:load --no-interaction

setup: ## Installation complète (build + up + composer + BDD + fixtures)
	$(MAKE) build
	$(MAKE) up
	$(MAKE) composer-install
	$(MAKE) db-create
	$(MAKE) db-migrate
	$(MAKE) fixtures

cache-clear: ## Vide le cache Symfony
	docker compose exec php php bin/console cache:clear

logs: ## Affiche les logs en temps réel
	docker compose logs -f php

mailpit: ## Ouvre Mailpit (interface e-mail de test)
	@echo "Mailpit disponible sur http://localhost:8025"
