<?php

namespace App\Http\Controllers\API;

use App\Domain\UI\Repositories\UiConfigurationRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\SchemaShowRequest;
use App\Http\Resources\WidgetResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SchemaController extends Controller
{
    public function __construct(
        protected UiConfigurationRepository $repository
    ) {}

    public function __invoke(SchemaShowRequest $request): JsonResponse
    {
        $country = $request->input('country');
        $step = $request->route('step');

        $ttl = (int) config('ui.cache.schema', 900);
        $store = Cache::store(config('cache.ui_store', config('cache.default')));
        $key = sprintf('ui:schema:%s:%s', strtolower($country), strtolower($step));

        $widgets = $store->remember($key, $ttl, function () use ($country, $step) {
            $widgets = $this->repository->widgetsForStep($country, $step);

            if (empty($widgets)) {
                abort(404, 'Schema not found for step.');
            }

            return $widgets;
        });

        return response()->json([
            'data' => WidgetResource::collection($widgets),
        ]);
    }
}
