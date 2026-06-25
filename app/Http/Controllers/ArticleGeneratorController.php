<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ArticleGenerator;
use App\Services\AiProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleGeneratorController extends Controller
{
    public function generate(
        Request $request,
        AiProviderService $aiProviderService,
    ): JsonResponse {
        $validated = $request->validate([
            'topic' => 'required|string|max:255',
        ]);

        $article = $aiProviderService->article_generator($validated['topic']);

        return response()->json([
            'data' => $article,
        ]);
    }

    public function generate_by_agent(
        Request $request,
        ArticleGenerator $articleGenerator,
    ): JsonResponse {
        $validated = $request->validate([
            'topic' => 'required|string|max:255',
        ]);

        $article = $articleGenerator->prompt(
            'Buatkan artikel menarik tentang: '.$validated['topic'],
            timeout: 90,
        );

        return response()->json([
            'data' => $article,
        ]);
    }
}
