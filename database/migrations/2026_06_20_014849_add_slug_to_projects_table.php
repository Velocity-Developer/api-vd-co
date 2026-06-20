<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('slug', 191)->nullable()->after('name');
        });

        $slugCounts = [];

        DB::table('projects')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->get()
            ->each(function (object $project) use (&$slugCounts): void {
                $baseSlug = Str::slug($project->name);

                if ($baseSlug === '') {
                    $baseSlug = 'project';
                }

                $slugCounts[$baseSlug] = ($slugCounts[$baseSlug] ?? 0) + 1;
                $slug = $slugCounts[$baseSlug] === 1
                    ? $baseSlug
                    : $baseSlug.'-'.$slugCounts[$baseSlug];

                DB::table('projects')
                    ->where('id', $project->id)
                    ->update(['slug' => $slug]);
            });

        Schema::table('projects', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
