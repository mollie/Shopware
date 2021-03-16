.PHONY: help
.DEFAULT_GOAL := help


help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

# ---------------------------------------------------------------------------------------------
install: ## Installs Cypress
	npm install cypress --save-dev --save .
	npm install webpack --save-dev --save .
	npm install @cypress/webpack-preprocessor --save-dev --save .
	npm install @cucumber/tag-expressions --save-dev --save .

open-ui: ## Opens Cypress UI
	CYPRESS_BASE_URL=$(url) ./node_modules/.bin/cypress open --env conf=$(conf)

# ---------------------------------------------------------------------------------------------

run: ## Runs all E2E tests
	CYPRESS_BASE_URL=$(url) ./node_modules/.bin/cypress run --headless
