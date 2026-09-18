<?php

namespace App\Services;

use App\Models\ChatIntent;

class IntentService
{

  public function detect(?string $message): ?string
{
    if (!$message) {
        return null;
    }

    $message = strtolower(trim($message));
    $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $message);

    $intents = ChatIntent::where('active', true)
        ->orderBy('priority')
        ->get();

    foreach ($intents as $intent) {
        foreach ($intent->keywords as $keyword) {
            $keyword = strtolower(trim($keyword));
            if ($keyword === '') {
                continue;
            }

            $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($keyword, '/') . '(?![\p{L}\p{N}])/u';

            if (preg_match($pattern, $normalized)) {
                return $intent->intent;
            }
        }
    }

    return null;
}
}
