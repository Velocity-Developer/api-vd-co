<?php

test('migrations use MariaDB safe lengths for indexed string columns', function () {
    $usersMigration = file_get_contents(database_path('migrations/0001_01_01_000000_create_users_table.php'));
    $cacheMigration = file_get_contents(database_path('migrations/0001_01_01_000001_create_cache_table.php'));
    $jobsMigration = file_get_contents(database_path('migrations/0001_01_01_000002_create_jobs_table.php'));
    $postsMigration = file_get_contents(database_path('migrations/2026_06_02_030452_create_posts_table.php'));
    $categoriesMigration = file_get_contents(database_path('migrations/2026_06_02_044501_create_categories_table.php'));
    $tagsMigration = file_get_contents(database_path('migrations/2026_06_02_044501_create_tags_table.php'));
    $licensesMigration = file_get_contents(database_path('migrations/2026_06_02_065538_create_licenses_table.php'));
    $websitesMigration = file_get_contents(database_path('migrations/2026_06_03_071356_create_websites_table.php'));

    expect($usersMigration)
        ->toContain("\$table->string('email', 191)->unique();")
        ->toContain("\$table->string('email', 191)->primary();")
        ->toContain("\$table->string('id', 191)->primary();");

    expect($cacheMigration)
        ->toContain("\$table->string('key', 191)->primary();");

    expect($jobsMigration)
        ->toContain("\$table->string('queue', 100)->index();")
        ->toContain("\$table->string('id', 36)->primary();")
        ->toContain("\$table->string('uuid', 36)->unique();")
        ->toContain("\$table->string('connection', 100);")
        ->toContain("\$table->string('queue', 100);");

    expect($postsMigration)->toContain("\$table->string('slug', 191)->unique();");
    expect($categoriesMigration)->toContain("\$table->string('slug', 191)->unique();");
    expect($tagsMigration)->toContain("\$table->string('slug', 191)->unique();");
    expect($licensesMigration)->toContain("\$table->string('code', 191)->unique();");

    expect($websitesMigration)
        ->toContain("\$table->string('domain', 191)->unique();")
        ->toContain("\$table->string('license_key', 191)->unique();");
});
