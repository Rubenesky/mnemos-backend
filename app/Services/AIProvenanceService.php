<?php

namespace App\Services;

use App\Models\AiGeneration;
use App\Models\Asset;

/**
 * Records and manages AI provenance data for digital assets.
 *
 * Satisfies EU AI Act traceability requirements by capturing:
 *   - Which AI model generated content for an asset
 *   - When the generation occurred
 *   - A summary of the prompt and a preview of the response
 *   - Whether a human reviewer has validated the AI output
 *
 * @package App\Services
 * @author  RJC
 */
class AIProvenanceService
{
    /**
     * Record a single AI generation event for an asset.
     *
     * Creates a row in ai_generations and, on the first generation for
     * a given asset, stamps ai_model and ai_generated_at on the asset
     * itself for quick access without joining the generations table.
     *
     * @param  Asset  $asset           The asset the content was generated for
     * @param  string $type            Generation type (alt_text|tags|description|report|story)
     * @param  string $model           AI model identifier (e.g. 'gemini-2.5-flash')
     * @param  string $promptSummary   Human-readable summary of the prompt sent to the model
     * @param  string $responsePreview First ≤200 characters of the model's response
     * @return void
     */
    public function recordGeneration(
        Asset $asset,
        string $type,
        string $model,
        string $promptSummary,
        string $responsePreview
    ): void {
        AiGeneration::create([
            'asset_id'         => $asset->id,
            'generation_type'  => $type,
            'model'            => $model,
            'prompt_summary'   => mb_substr($promptSummary, 0, 500),
            'response_preview' => mb_substr($responsePreview, 0, 200),
            'user_id'          => null, // system-initiated; no interactive user context
        ]);

        // Stamp the asset on first generation; subsequent calls update ai_prompt only
        $updates = ['ai_prompt' => mb_substr($promptSummary, 0, 500)];

        if (! $asset->ai_generated_at) {
            $updates['ai_model']        = $model;
            $updates['ai_generated_at'] = now();
        }

        $asset->update($updates);
    }

    /**
     * Mark the AI-generated content on an asset as reviewed by a human.
     *
     * Records who performed the review and when, enabling downstream
     * systems to filter on human-validated content.
     *
     * @param  Asset $asset   The asset whose AI content has been reviewed
     * @param  int   $userId  ID of the user who performed the review
     * @return void
     */
    public function markReviewed(Asset $asset, int $userId): void
    {
        $asset->update([
            'ai_reviewed_by' => $userId,
            'ai_reviewed_at' => now(),
        ]);
    }
}
