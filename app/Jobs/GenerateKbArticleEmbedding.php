<?php

namespace App\Jobs;

use App\Models\KbArticle;
use App\Services\KbEmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateKbArticleEmbedding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 15;

    public function __construct(public int $articleId)
    {
    }

    public function handle(KbEmbeddingService $embeddings): void
    {
        $article = KbArticle::find($this->articleId);

        if ($article) {
            $embeddings->syncArticle($article);
        }
    }
}