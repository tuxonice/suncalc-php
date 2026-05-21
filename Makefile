.PHONY: help build test phpstan phpcs phpcbf shell clean

help: ## Show this help message
	@echo "Available commands:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

build: ## Build Docker image
	docker-compose build

test: ## Run PHPUnit tests
	docker-compose run --rm tests

phpstan: ## Run PHPStan static analysis
	docker-compose run --rm phpstan

phpcs: ## Run PHP_CodeSniffer
	docker-compose run --rm phpcs

phpcbf: ## Run PHP_CodeBeautifier and Fixer
	docker-compose run --rm phpcbf

shell: ## Open shell in container
	docker-compose run --rm tests bash

clean: ## Remove Docker containers and images
	docker-compose down --rmi local
