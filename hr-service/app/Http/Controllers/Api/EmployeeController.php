<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\IndexEmployeeRequest;
use App\Http\Requests\Employees\StoreEmployeeRequest;
use App\Http\Requests\Employees\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeService $service
    ) {}

    public function index(IndexEmployeeRequest $request): AnonymousResourceCollection
    {
        $employees = $this->service->list(
            $request->validated()['country'],
            $request->perPage()
        );

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->service->create($request->dto(), $request->changedFields());

        return EmployeeResource::make($employee)
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    public function show(int $employee): JsonResponse|EmployeeResource
    {
        $model = $this->service->find($employee);

        if (! $model) {
            Log::warning('Employee not found when showing record', [
                'employee_id' => $employee,
            ]);

            return response()->json([
                'message' => 'Employee not found.',
            ], SymfonyResponse::HTTP_NOT_FOUND);
        }

        return EmployeeResource::make($model);
    }

    public function update(UpdateEmployeeRequest $request, int $employee): JsonResponse|EmployeeResource
    {
        $model = $this->service->find($employee);

        if (! $model) {
            Log::warning('Employee not found when updating record', [
                'employee_id' => $employee,
                'payload' => $request->validated(),
            ]);

            return response()->json([
                'message' => 'Employee not found.',
            ], SymfonyResponse::HTTP_NOT_FOUND);
        }

        $updated = $this->service->update($model, $request->dto($model), $request->changedFields());

        return EmployeeResource::make($updated);
    }

    public function destroy(int $employee): Response|JsonResponse
    {
        $model = $this->service->find($employee);

        if (! $model) {
            Log::warning('Employee not found when deleting record', [
                'employee_id' => $employee,
            ]);

            return response()->json([
                'message' => 'Employee not found.',
            ], SymfonyResponse::HTTP_NOT_FOUND);
        }

        $this->service->delete($model);

        return response()->noContent();
    }
}
