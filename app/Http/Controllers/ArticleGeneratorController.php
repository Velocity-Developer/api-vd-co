<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Ai\Agents\ArticleGenerator;

class ArticleGeneratorController extends Controller
{
    public function generate(Request $request)
    {
        // 1. Validasi input dari user
        $request->validate([
            'topic' => 'required|string|max:255',
        ]);

        // 2. Panggil AI Agent Anda
        $agent = new ArticleGenerator();
        $article = $agent->prompt("Buatkan artikel menarik tentang: " . $request->topic);

        // 3. Kirim hasil artikel ke client
        return response()->json($article);
    }
}
