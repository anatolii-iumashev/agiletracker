.PHONY: help dev serve test pint lint db db-fresh db-seed build setup

# ─── AgileTracker Makefile ──────────────────────────────────────────────────
# Default target
help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

# ─── Development ───────────────────────────────────────────────────────────

dev: ## Start dev server (artisan serve + queue + pail + vite)
	composer run dev

serve: ## Start only artisan serve
	php artisan serve

# ─── Code Quality ──────────────────────────────────────────────────────────

pint: ## Run Laravel Pint (PSR-12 fixer)
	./vendor/bin/pint

pint-dirty: ## Run Pint only on changed files
	./vendor/bin/pint --dirty

pint-test: ## Run Pint in dry-run (show issues without fixing)
	./vendor/bin/pint --test

lint: pint ## Alias for pint

# ─── Testing ────────────────────────────────────────────────────────────────

test: ## Run all tests
	php artisan test --compact

test-filter: ## Run tests matching filter (usage: make test-filter F=ClassName)
	php artisan test --compact --filter=$(F)

test-unit: ## Run unit tests only
	php artisan test --compact --testsuite=Unit

test-feature: ## Run feature tests only
	php artisan test --compact --testsuite=Feature

# ─── Database ───────────────────────────────────────────────────────────────

db: ## Run all outstanding migrations
	php artisan migrate --force

db-fresh: ## Drop all tables and re-run migrations
	php artisan migrate:fresh --force

db-seed: ## Run seeders
	php artisan db:seed

db-fresh-seed: ## Fresh migrate + seed
	php artisan migrate:fresh --force --seed

# ─── Frontend ───────────────────────────────────────────────────────────────

build: ## Build frontend assets for production
	npm run build

watch: ## Start Vite dev server (HMR)
	npm run dev

# ─── Setup ──────────────────────────────────────────────────────────────────

setup: ## Full project setup (composer + .env + key + migrate + npm)
	composer run setup

# ─── Quick checks before commit ─────────────────────────────────────────────

check: pint test ## Run pint + tests (do this before committing)
