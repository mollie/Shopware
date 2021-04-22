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
	@composer install --no-dev --no-scripts

dev: ## Installs all dev dependencies
	@composer install

clean: ## Cleans all dependencies
	@rm -rf vendor
	@rm -rf composer.lock
	@rm -rf .reports | true

# ------------------------------------------------------------------------------------------------------------

test: ## Starts all Tests
	@XDEBUG_MODE=coverage php vendor/bin/phpunit --configuration=phpunit.xml

phpcheck: ## Starts the PHP syntax checks
	@find . -name '*.php' -not -path "./vendor/*" -not -path "./Tests/*" | xargs -n 1 -P4 php -l

phpmin: ## Starts the PHP compatibility checks
	@php vendor/bin/phpcs -p --ignore=*/Client/*,*/Resources/*,*/Tests*,*/vendor/* --standard=PHPCompatibility --extensions=php --runtime-set testVersion 5.6 .

csfix: ## Starts the PHP Coding Standard Analyser
	@php vendor/bin/php-cs-fixer fix --config=./.php_cs.php --dry-run

stan: ## Starts the PHPStan Analyser
	@php vendor/bin/phpstan analyse -c phpstan.neon

metrics: ## Starts the PHPMetrics Analyser
	@php vendor/bin/phpmetrics --config=.phpmetrics.json

# ------------------------------------------------------------------------------------------------------------

pr: ## Prepares everything for a Pull Request
	@php vendor/bin/php-cs-fixer fix --config=./.php_cs.php
	@make phpcheck -B
	@make phpmin -B
	@make test -B
	@make stan -B

# ------------------------------------------------------------------------------------------------------------

release: ## Creates a new ZIP package
	@cd .. && rm -rf MollieShopware-v$(PLUGIN_VERSION).zip
	@cd .. && zip -qq -r -0 MollieShopware-v$(PLUGIN_VERSION).zip MollieShopware/ -x '*.git*' '*.github' '*.reports*' '*/Tests*' '*/phpunit.xml' '*/phpstan.neon' '*/.phpmetrics.json' '*/.php_cs.php' '*/makefile' '*.DS_Store'
