.PHONY: dev test test-backend test-frontend clean

dev:
	php -S localhost:8080 -t backend/public backend/public/index.php

test: test-backend test-frontend

test-backend:
	cd backend && composer test

test-frontend:
	cd frontend && node --test tests/

clean:
	rm -f backend/data/*.sqlite backend/data/*.sqlite-journal
