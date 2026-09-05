.PHONY: validate test-php typecheck-js

validate:
	python scripts/validate.py

test-php:
	cd packages/laravel && composer test

typecheck-js:
	cd packages/browser-runtime && npm run typecheck
