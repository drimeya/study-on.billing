COMPOSE=docker-compose
PHP=$(COMPOSE) exec php
CONSOLE=$(PHP) bin/console
COMPOSER=$(PHP) composer

full-up: up migrate fixtload

up:
	@${COMPOSE} up -d

down:
	@${COMPOSE} down

clear:
	@${CONSOLE} cache:clear

migration:
	@${CONSOLE} make:migration

migrate:
	@${CONSOLE} doctrine:migrations:migrate

fixtload:
	@${CONSOLE} doctrine:fixtures:load

require:
	@${COMPOSER} require $2

encore_dev:
	@${COMPOSE} run node yarn encore dev --watch

encore_prod:
	@${COMPOSE} run node yarn encore production

phpunit:
	@${PHP} bin/phpunit

test: reset-test-db
	@${PHP} bin/phpunit

reset-test-db:
	@${CONSOLE} doctrine:database:drop --env=test --force
	@${CONSOLE} doctrine:database:create --env=test
	@${CONSOLE} doctrine:migrations:migrate --env=test --no-interaction

# В файл local.mk можно добавлять дополнительные make-команды,
# которые требуются лично вам, но не нужны на проекте в целом
-include local.mk