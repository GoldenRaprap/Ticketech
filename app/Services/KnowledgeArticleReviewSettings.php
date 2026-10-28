<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class KnowledgeArticleReviewSettings
{
    public function visibilityThreshold(): int
    {
        $value = DB::table('system_settings')
            ->where('setting', 'knowledge_base.review_visibility_threshold')
            ->value('value');

        return $value === null ? 5 : (int) $value;
    }
}
