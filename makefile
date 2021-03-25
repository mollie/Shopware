#
# Makefile
#

.PHONY: help
.DEFAULT_GOAL := help

PLUGIN_VERSION=`cat MollieShopware/plugin.xml | grep -oPm1 "(?<=<version>)[^<]+"`

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

# ------------------------------------------------------------------------------------------------------------

install: ## Installs all production dependencies
	@composer install --no-dev

dev: ## Installs all dev dependencies
	@composer install

clean: ## Cleans all dependencies
	@rm -rf vendor
	@rm -rf composer.lock
	@rm -rf .reports | true

# ------------------------------------------------------------------------------------------------------------

test: ## Starts all Tests
	@php vendor/bin/phpunit --configuration=phpunit.xml

csfix: ## Starts the PHP CS Fixer
	@php vendor/bin/php-cs-fixer fix --config=./.php_cs.php --dry-run

stan: ## Starts the PHPStan Analyser
	@php vendor/bin/phpstan analyse -c phpstan.neon

# ------------------------------------------------------------------------------------------------------------

pr: ## Prepares everything for a Pull Request
	@php vendor/bin/php-cs-fixer fix --config=./.php_cs.php
	@make test -B
	@make stan -B

# ------------------------------------------------------------------------------------------------------------

release: ## Creates a new ZIP package
	@cd .. && rm -rf MollieShopware-v$(PLUGIN_VERSION).zip
	@cd .. && zip -qq -r -0 MollieShopware-v$(PLUGIN_VERSION).zip MollieShopware/ -x '*.git*' '*.reports*' '*/Tests*' '*/phpunit.xml' '*/phpstan.neon' '*/makefile' '*.DS_Store' '*.github'
