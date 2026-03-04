<?php

namespace App\Domain\UI\Services;

use App\Domain\UI\DTOs\ColumnDefinition;
use App\Domain\UI\DTOs\StepDefinition;
use App\Domain\UI\DTOs\WidgetDefinition;
use App\Domain\UI\Repositories\UiConfigurationRepository;

class UiConfigurationService
{
    public function __construct(
        protected UiConfigurationRepository $repository
    ) {}

    /**
     * @return StepDefinition[]
     */
    public function steps(string $country): array
    {
        return $this->repository->stepsForCountry($country);
    }

    /**
     * @return ColumnDefinition[]
     */
    public function columns(string $country): array
    {
        return $this->repository->columnsForCountry($country);
    }

    /**
     * @return WidgetDefinition[]
     */
    public function widgets(string $country, string $step): array
    {
        return $this->repository->widgetsForStep($country, $step);
    }
}
