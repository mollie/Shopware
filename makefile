#
# Makefile
#

.PHONY: help
.DEFAULT_GOAL := help


help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

# ------------------------------------------------------------------------------------------------------------

install: ## Installs all production dependencies
	@make clean -B
	@composer install --no-dev
	@npm install --production

dev: ## Installs all dev dependencies
	@make clean -B
	@composer install
	@npm install

clean: ## Cleans all dependencies
	@rm -rf vendor
	@rm -rf node_modules
	@rm -rf composer.lock
	@rm -rf package-lock.json
	@rm -rf .reports | true

# ------------------------------------------------------------------------------------------------------------

test: ## Starts all Tests
	@XDEBUG_MODE=coverage php vendor/bin/phpunit --configuration=phpunit.xml

phpcheck: ## Starts the PHP syntax checks
	@find . -name '*.php' -not -path "./vendor/*" -not -path "./Tests/*" | xargs -n 1 -P4 php -l

phpmin: ## Starts the PHP compatibility checks
	@php vendor/bin/phpcs -p --ignore=*/Resources/*,*/Tests*,*/vendor/* --standard=PHPCompatibility --extensions=php --runtime-set testVersion 5.6 .

csfix: ## Starts the PHP Coding Standard Analyser
	@php vendor/bin/php-cs-fixer fix --config=./.php_cs.php --dry-run

stan: ## Starts the PHPStan Analyser
	@php vendor/bin/phpstan analyse -c .phpstan.neon
	@php vendor/bin/phpstan analyse -c .phpstan.lvl8.neon

metrics: ## Starts the PHPMetrics Analyser
	@php vendor/bin/phpmetrics --config=.phpmetrics.json

jest: ## Starts all Jest tests
	./node_modules/.bin/jest --config=.jest.config.js

eslint: ## Starts ESLint
	./node_modules/eslint/bin/eslint.js --config ./.eslintrc.json ./Resources/views/frontend

stylelint: ## Starts the Stylelinter
	./node_modules/.bin/stylelint --allow-empty-input ./Resources/**/*.less

snippetcheck: ## Validates the Snippets (requires Shopware)
	cd ./Tests/Snippets && php validate.php
	cd ../../.. && php bin/console sw:snippets:validate custom/plugins/MollieShopware

# ------------------------------------------------------------------------------------------------------------

pr: ## Prepares everything for a Pull Request
	@php vendor/bin/php-cs-fixer fix --config=./.php_cs.php
	@make phpcheck -B
	@make phpmin -B
	@make test -B
	@make stan -B
	@make jest -B
	@make eslint -B
	@make snippetcheck -B

# ------------------------------------------------------------------------------------------------------------

release: ## Creates a new ZIP package
	cd .. && mkdir -p ./.release
	cd .. && cd ./.release && rm -rf MollieShopware.zip
	cd .. && zip -qq -r -0 ./.release/MollieShopware.zip MollieShopware/* -x 'MollieShopware/.*' 'MollieShopware/Tests*' 'MollieShopware/phpstan.neon' 'MollieShopware/makefile' '*.DS_Store' '*node_modules'
