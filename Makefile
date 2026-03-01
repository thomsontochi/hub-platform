.PHONY: test test-hub test-hr lint fmt setup-tests

COMPOSER?=composer

HUB_SERVICE_DIR=hub-service
HR_SERVICE_DIR=hr-service

TEST_FLAGS?=

ifdef COVERAGE
TEST_FLAGS+=--coverage --min=80
endif

test: test-hub test-hr

setup-tests:
	@echo "Installing dependencies for both services"
	@cd $(HUB_SERVICE_DIR) && $(COMPOSER) install
	@cd $(HR_SERVICE_DIR) && $(COMPOSER) install

test-hub:
	@echo "Running Pest tests for hub-service"
	@cd $(HUB_SERVICE_DIR) && vendor/bin/pest $(TEST_FLAGS)

test-hr:
	@echo "Running Pest tests for hr-service"
	@cd $(HR_SERVICE_DIR) && vendor/bin/pest $(TEST_FLAGS)
