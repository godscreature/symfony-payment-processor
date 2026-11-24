.DEFAULT_GOAL := help

DC      := docker compose
# Without a terminal (CI, scripts) exec needs -T, or its output is lost.
TTY     := $(shell test -t 0 || echo -T)
PHP     := $(DC) exec $(TTY) php
CONSOLE := $(PHP) bin/console

.PHONY: help init build up down restart ps logs sh install migrate migration db-test test stan cs-fix cs-check check cc

help: ## Show available commands
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

## —— Docker ————————————————————————————————————————————
init: build up install migrate ## First-time setup: build, start, install deps, migrate

build: ## Build the PHP image
	$(DC) build

up: ## Start all containers
	$(DC) up -d

down: ## Stop and remove containers
	$(DC) down

restart: down up ## Restart all containers

ps: ## Show container status
	$(DC) ps

logs: ## Follow container logs
	$(DC) logs -f

sh: ## Open a shell in the PHP container
	$(PHP) bash

## —— App ———————————————————————————————————————————————
install: ## Install Composer dependencies
	$(PHP) composer install

migrate: ## Run database migrations
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

migration: ## Generate a migration from entity changes
	$(CONSOLE) doctrine:migrations:diff

cc: ## Clear the Symfony cache
	$(CONSOLE) cache:clear

## —— Quality ———————————————————————————————————————————
db-test: ## Create and migrate the test database
	$(CONSOLE) doctrine:database:create --env=test --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate --env=test --no-interaction --allow-no-migration

test: db-test ## Run PHPUnit
	$(PHP) bin/phpunit

stan: ## Run PHPStan (level 8)
	$(CONSOLE) cache:warmup --env=dev
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

cs-fix: ## Fix code style
	$(PHP) vendor/bin/php-cs-fixer fix

cs-check: ## Check code style without changing files
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

check: cs-check stan test ## Run all quality checks
