<?php

use App\Models\Tag;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $tag = Tag::query()->where('slug', 'missing-facts')->first();
        if (! $tag) {
            return;
        }

        $tag->name = '隱瞞事實或造假';
        $tag->description = 'Key facts are omitted or fabricated in a way that changes interpretation.';
        $tag->save();

        $translations = $tag->translations ?? [];
        $translations['zh-TW'] = [
            'name' => '隱瞞事實或造假',
            'description' => '省略關鍵事實或捏造資訊，導致讀者對事件產生不同理解。',
        ];
        $translations['en'] = [
            'name' => 'Missing facts or fabrication',
            'description' => 'Key facts are omitted or fabricated in a way that changes interpretation.',
        ];
        $tag->translations = $translations;
        $tag->save();
    }

    public function down(): void
    {
        $tag = Tag::query()->where('slug', 'missing-facts')->first();
        if (! $tag) {
            return;
        }

        $tag->name = '隱瞞事實';
        $tag->description = 'Key facts are omitted in a way that changes interpretation.';
        $tag->save();

        $translations = $tag->translations ?? [];
        $translations['zh-TW'] = [
            'name' => '隱瞞事實',
            'description' => '省略關鍵事實，導致讀者對事件產生不同理解。',
        ];
        $translations['en'] = [
            'name' => 'Missing key facts',
            'description' => 'Key facts are omitted in a way that changes interpretation.',
        ];
        $tag->translations = $translations;
        $tag->save();
    }
};
