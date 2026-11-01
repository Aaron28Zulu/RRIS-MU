<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drops the "Vision 2030" wording from challenge calls that were already saved.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('innovation_challenges')
            ->where('title', 'like', '%Vision 2030%')
            ->orWhere('description', 'like', '%Vision 2030%')
            ->orderBy('id')
            ->each(function (object $challenge): void {
                DB::table('innovation_challenges')->where('id', $challenge->id)->update([
                    'title' => trim(str_replace('Vision 2030 ', '', $challenge->title)),
                    'description' => str_replace(
                        [" that support Zambia's Vision 2030 and contribute", "Zambia's Vision 2030", 'Vision 2030 '],
                        [' that contribute', 'national development goals', ''],
                        $challenge->description,
                    ),
                ]);
            });
    }

    public function down(): void
    {
        // The original wording is not restored.
    }
};
