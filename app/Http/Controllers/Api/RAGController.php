<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RAGService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API controller that exposes the RAG (Retrieval-Augmented Generation) question-answering endpoint.
 *
 * @package App\Http\Controllers\Api
 */
class RAGController extends Controller
{
    public function query(Request $request): JsonResponse
    {
        $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        $rag      = new RAGService();
        $answer   = $rag->query($request->input('question'));

        return response()->json([
            'success'  => true,
            'question' => $request->input('question'),
            'answer'   => $answer,
        ]);
    }
}