.PHONY: qa lint cs csf phpstan tests coverage

all:
	@$(MAKE) -pRrq -f $(lastword $(MAKEFILE_LIST)) : 2>/dev/null | awk -v RS= -F: '/^# File/,/^# Finished Make data base/ {if ($$1 !~ "^[#.]") {print $$1}}' | sort | egrep -v -e '^[^[:alnum:]]' -e '^$@$$' | xargs

install:
	composer update

qa: lint phpstan cs

lint: vendor
	vendor/bin/linter src tests

cs: vendor
	vendor/bin/codesniffer --extensions=php src tests

csf: vendor
	vendor/bin/codefixer --extensions=php src tests

phpstan: vendor
	vendor/bin/phpstan analyse -l max -c phpstan.neon src

tests: vendor
	vendor/bin/phpunit

coverage: vendor
	XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-clover=coverage.xml
