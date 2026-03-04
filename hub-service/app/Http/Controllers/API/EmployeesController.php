<?php

namespace App\Http\Controllers\API;

use App\Domain\Employees\Services\EmployeeListingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeIndexRequest;
use App\Http\Resources\ColumnResource;
use App\Http\Resources\EmployeeResource;
use App\Infrastructure\UI\Cache\UiCacheRepository;
use Illuminate\Http\JsonResponse;

class EmployeesController extends Controller
{
    public function __construct(
        protected EmployeeListingService $listingService,
        protected UiCacheRepository $cache
    ) {}

    public function __invoke(EmployeeIndexRequest $request): JsonResponse
    {
        $country = $request->input('country');
        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);

        $data = $this->cache->rememberEmployees(
            $country,
            $page,
            $perPage,
            fn () => $this->listingService->list($country, $perPage, $page)
        );

        /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
        $paginator = $data['employees'];

        return response()->json([
            'data' => [
                'columns' => ColumnResource::collection($data['columns']),
                'employees' => EmployeeResource::collection($paginator->items()),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }
}
