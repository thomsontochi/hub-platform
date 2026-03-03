<?php

namespace App\Http\Controllers;

use App\Domain\Checklists\Services\ChecklistProjectionService;
use App\Http\Requests\ChecklistIndexRequest;
use App\Http\Resources\ChecklistResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ChecklistController extends Controller
{
    public function __invoke(
        ChecklistIndexRequest $request,
        ChecklistProjectionService $projectionService
    ): ChecklistResource|JsonResponse {
        $country = $request->input('country');
        $projection = $projectionService->get($country);

        if (! $projection) {
            Log::warning('Checklist rules missing for requested country checklist projection', [
                'country' => $country,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => sprintf('Checklist rules not configured for country [%s].', strtoupper($country)),
            ], SymfonyResponse::HTTP_NOT_FOUND);
        }

        return ChecklistResource::make($projection);
    }
}
