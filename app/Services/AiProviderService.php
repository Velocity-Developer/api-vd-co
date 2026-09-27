<?php

namespace App\Services;

use App\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class AiProviderService
{
    /**
     * Generate article content using an OpenAI-compatible AI provider.
     *
     * @return array{
     *     title: string,
     *     content: string,
     *     excerpt: string,
     *     tags: array<int, string>,
     *     image_keyword: string
     * }
     *
     * @throws AiProviderException when the provider fails or does not return an article.
     */
    public function article_generator(string $topic, bool $stream = false): array
    {
        // 1. Kirim POST request ke API Anda
        try {
            $response = Http::timeout(120)->withHeaders([
                'Authorization' => 'Bearer '.config('services.ai_provider.key'),
                'Content-Type' => 'application/json',
            ])->post(config('services.ai_provider.url').'/chat/completions', [
                'model' => config('services.ai_provider.model'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->system_prompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Buatkan artikel menarik tentang: '.$topic,
                    ],
                ],
                'stream' => $stream,
                'response_format' => [
                    'type' => 'json_object',
                ],
            ]);
        } catch (ConnectionException $exception) {
            throw new AiProviderException('AI provider tidak bisa dihubungi atau terlalu lama menjawab.', previous: $exception);
        }

        // 2. Cek apakah request ke API sukses
        if (! $response->successful()) {
            throw new AiProviderException("AI provider menolak permintaan (HTTP {$response->status()}).");
        }

        // Mengambil string teks JSON yang ter-escape dari dalam properti content
        $contentString = $response->json('choices.0.message.content');

        // Parse JSON Kedua: Mengubah string artikel menjadi array PHP yang bersih; model kadang membungkusnya dengan ```json
        $article = is_string($contentString)
            ? json_decode(preg_replace('/^\s*```(?:json)?\s*|\s*```\s*$/', '', $contentString), true)
            : null;

        if (! is_array($article) || blank($article['content'] ?? null)) {
            throw new AiProviderException('Jawaban AI provider bukan artikel yang valid. Coba generate lagi.');
        }

        return $article;
    }

    /**
     * Send a chat completion request to the AI provider.
     *
     * @param  string  $prompt  System prompt / instruction
     * @param  string  $content  User message content
     * @param  bool  $stream  Whether to stream the response
     * @return array|null Decoded JSON response, or null on failure
     */
    public function chat(string $prompt, string $content, bool $stream = false)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('services.ai_provider.key'),
            'Content-Type' => 'application/json',
        ])->post(config('services.ai_provider.url').'/chat/completions', [
            'model' => config('services.ai_provider.model'),
            'messages' => [
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user', 'content' => $content],
            ],
            'stream' => $stream,
        ]);

        if ($response->successful()) {
            $contentString = $response->json()['choices'][0]['message']['content'];

            $decoded = json_decode($contentString, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }

            return $contentString;
        }

        return null;
    }

    /**
     * @return string{
     * }
     */
    private function system_prompt(): string
    {
        $prompt = 'Anda adalah mesin generator konten SEO profesional handal berbasis JSON. Tugas Anda adalah mengubah topik yang diberikan user menjadi objek JSON tunggal yang valid tanpa teks tambahan di luar JSON.';
        $prompt .= "Aturan Ketat: Tidak boleh ada teks tambahan atau keterangan di luar JSON. Jangan menulis kata pengantar seperti 'Berikut adalah artikel...', jangan beri salam, dan jangan beri teks penutup.";
        $prompt .= 'Output HARUS berupa JSON valid yang langsung bisa di-parse.';
        $prompt .= "Format JSON wajib mengikuti struktur berikut:{'title' => 'Judul menarik, informatif, ramah SEO (maksimal 80 karakter), jangan gunakan :', 'content' => 'Isi artikel lengkap dan mendalam minimal 5 paragraf. Gunakan format tag HTML dasar seperti <p>, <h3>, dan <strong', 'excerpt' => 'Ringkasan singkat artikel dalam 2-3 kalimat untuk meta description (maksimal 160 karakter)', 'tags' => 'maksimal 5 array kata kunci pendek, 'image_keyword' => '1 kata kunci gambar dalam bahasa Inggris'}";

        return $prompt;
    }
}
