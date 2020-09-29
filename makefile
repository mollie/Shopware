#
# Makefile
#

.PHONY: help
.DEFAULT_GOAL := help

PLUGIN_VERSION=`cat MollieShopware/plugin.xml | grep -oPm1 "(?<=<version>)[^<]+"`

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

# ----------------------------------------------------------------------------------------------------------------

install: ## Installs all dependencies
	@composer install

release: ## Creates a new ZIP package
	@cd .. && zip -qq -r MollieShopware-v$(PLUGIN_VERSION).zip MollieShopware/ -x '*.git*' '*/makefile' '*.DS_Store'
