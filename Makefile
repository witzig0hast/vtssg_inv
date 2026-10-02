COMPOSE = docker compose --env-file .env.docker
.DEFAULT_GOAL := help

help: ## Diese Hilfe
	@grep -E '^[a-z-]+:.*##' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*## "}{printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

init: ## Geheimnisse und .env.docker erzeugen
	./scripts/init.sh

build: ## Images bauen
	$(COMPOSE) build

up: ## Stack starten (baut bei Bedarf)
	$(COMPOSE) up -d --build

down: ## Stack stoppen (Daten bleiben erhalten)
	$(COMPOSE) down

ps: ## Status/Health aller Container
	$(COMPOSE) ps

logs: ## Logs verfolgen
	$(COMPOSE) logs -f --tail=100

shell: ## Shell im App-Container
	docker exec -it vtssg_inv02_app sh

artisan: ## Artisan ausführen: make artisan c="inv:audit-verify"
	docker exec -it vtssg_inv02_app php artisan $(c)

admin: ## Admin anlegen/zurücksetzen: make admin email=a@b.de
	docker exec -it vtssg_inv02_app php artisan inv:create-admin $(email)

backup: ## Sofort-Backup erstellen
	docker exec vtssg_inv02_backup backup now

restore: ## Backup einspielen: make restore f=vtssg_inv02-20260101-020000.tar.enc
	$(COMPOSE) stop app queue scheduler web
	docker exec vtssg_inv02_backup restore /backups/$(f)
	$(COMPOSE) start app queue scheduler web

update: ## Neuen Code ziehen, Backup, neu bauen und starten
	git pull --ff-only
	-$(MAKE) backup
	$(COMPOSE) up -d --build

test: ## Tests lokal ausführen
	php artisan test
