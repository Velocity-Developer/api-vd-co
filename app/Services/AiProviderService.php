<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

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
     */
    public function article_generator(string $topic, bool $stream = false): array
    {
        // 1. Kirim POST request ke API Anda
        $response = Http::baseUrl((string) config('services.ai_provider.url'))
            ->acceptJson()
            ->withToken((string) config('services.ai_provider.key'))
            ->connectTimeout(15)
            ->timeout(180)
            ->retry(2, 1000)
            ->post('/chat/completions', [
                'model' => (string) config('services.ai_provider.model'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->system_prompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Buatkan artikel menarik tentang: ' . $topic,
                    ],
                ],
                'response_format' => [
                    'type' => 'json_object',
                ],
                'stream' => $stream,
            ])
            ->throw()
            ->json();

        // 2. Cek apakah request ke API sukses
        if ($response->successful()) {
            // Parse JSON Pertama: Mengubah response API menjadi array PHP
            $apiData = $response->json();

            // Mengambil string teks JSON yang ter-escape dari dalam properti content
            $contentString = $apiData['choices'][0]['message']['content'];

            // Parse JSON Kedua: Mengubah string artikel menjadi array PHP yang bersih
            $article = json_decode($contentString, true);

            return $article;
        }
        return response()->array(['error' => 'Gagal generate artikel',], 500);
    }

    /**
     * @return string{
     * }
     */
    private function system_prompt(): string
    {
        $prompt = 'Anda adalah mesin generator konten SEO profesional handal berbasis JSON. Tugas Anda adalah mengubah topik yang diberikan user menjadi objek JSON tunggal yang valid tanpa teks tambahan di luar JSON.';
        $prompt .= "Aturan Ketat: Tidak boleh ada teks tambahan atau keterangan di luar JSON. Jangan menulis kata pengantar seperti 'Berikut adalah artikel...', jangan beri salam, dan jangan beri teks penutup.";
        $prompt .= "Output HARUS berupa JSON valid yang langsung bisa di-parse.";
        $prompt .= "Format JSON wajib mengikuti struktur berikut:{'title' => 'Judul menarik, informatif, ramah SEO (maksimal 80 karakter)', 'content' => 'Isi artikel lengkap dan mendalam minimal 4 paragraf. Gunakan format tag HTML dasar seperti <p>, <h3>, dan <strong', 'excerpt' => 'Ringkasan singkat artikel dalam 2-3 kalimat untuk meta description (maksimal 160 karakter)', 'tags' => 'maksimal 5 array kata kunci pendek, 'image_keyword' => '1 kata kunci gambar dalam bahasa Inggris'}";

        return $prompt;
    }
}
