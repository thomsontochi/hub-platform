<?php

namespace App\Http\Controllers\API;

use App\Domain\UI\Services\UiConfigurationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StepsIndexRequest;
use App\Http\Resources\StepResource;
use App\Infrastructure\UI\Cache\UiCacheRepository;
use Illuminate\Http\JsonResponse;

class StepsController extends Controller
{
    public function __construct(
        protected UiConfigurationService $service,
        protected UiCacheRepository $cache
    ) {}

    public function __invoke(StepsIndexRequest $request): JsonResponse
    {
        $country = $request->input('country');
        $steps = $this->cache->rememberSteps($country, fn () => $this->service->steps($country));

        return response()->json([
            'data' => StepResource::collection($steps),
        ]);
    }
}
