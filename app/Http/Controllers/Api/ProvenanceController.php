<?php

// RJC

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\AIProvenanceService;
use Illuminate\Http\JsonResponse;

/**
 * Manages AI provenance data for digital assets.
 *
 * Exposes the AI generation audit trail and human-review workflow
 * required by the EU AI Act to demonstrate content traceability.
 *
 * Routes (auth:sanctum):
 *   GET  /api/assets/{asset}/provenance         — full provenance history
 *   POST /api/assets/{asset}/provenance/review  — mark content as reviewed
 *
 * @author  RJC
 */
class ProvenanceController extends Controller
{
    /**
     * @param  AIProvenanceService  $provenance  Records and retrieves provenance data
     */
    public function __construct(
        private readonly AIProvenanceService $provenance,
    ) {}

    /**
     * GET /api/assets/{asset}/provenance
     *
     * Returns the complete AI provenance record for an asset, including
     * all generation events and the human-review status.
     *
     * Access: admin (all assets) | others (own assets only — IDOR protection).
     *
     * @return JsonResponse 200 {
     *                      data: {
     *                      ai_generated:    bool,
     *                      ai_model:        string|null,
     *                      ai_generated_at: ISO8601|null,
     *                      ai_reviewed_by:  {id: int, name: string}|null,
     *                      ai_reviewed_at:  ISO8601|null,
     *                      generations:     Array<{
     *                      type, model, prompt_summary, response_preview, created_at
     *                      }>,
     *                      }
     *                      }
     */
    public function show(Asset $asset): JsonResponse
    {
        $user = auth()->user();

        if (! $user->isAdmin() && $asset->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $asset->load([
            'metadata',
            'aiGenerations' => fn ($q) => $q->orderBy('created_at'),
            'reviewer:id,name',
        ]);

        return response()->json([
            'data' => [
                'ai_generated' => (bool) ($asset->metadata?->ai_generated),
                'ai_model' => $asset->ai_model,
                'ai_generated_at' => $asset->ai_generated_at?->toISOString(),
                'ai_reviewed_by' => $asset->reviewer
                    ? ['id' => $asset->reviewer->id, 'name' => $asset->reviewer->name]
                    : null,
                'ai_reviewed_at' => $asset->ai_reviewed_at?->toISOString(),
                'generations' => $asset->aiGenerations->map(fn ($g) => [
                    'type' => $g->generation_type,
                    'model' => $g->model,
                    'prompt_summary' => $g->prompt_summary,
                    'response_preview' => $g->response_preview,
                    'created_at' => $g->created_at->toISOString(),
                ])->values(),
            ],
        ]);
    }

    /**
     * POST /api/assets/{asset}/provenance/review
     *
     * Marks the AI-generated content on an asset as reviewed by a human.
     *
     * Access: admin (any asset) | editor (own assets only).
     * Volunteer and viewer roles are not permitted.
     *
     * @return JsonResponse 200 {
     *                      data: {
     *                      ai_reviewed_by: {id: int, name: string},
     *                      ai_reviewed_at: ISO8601,
     *                      }
     *                      }
     */
    public function markReviewed(Asset $asset): JsonResponse
    {
        $user = auth()->user();

        $isAdminOrOwningEditor = $user->isAdmin()
            || ($user->role === 'editor' && $asset->user_id === $user->id);

        if (! $isAdminOrOwningEditor) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $this->provenance->markReviewed($asset, $user->id);

        $asset->refresh();

        return response()->json([
            'data' => [
                'ai_reviewed_by' => ['id' => $user->id, 'name' => $user->name],
                'ai_reviewed_at' => $asset->ai_reviewed_at?->toISOString(),
            ],
        ]);
    }
}
