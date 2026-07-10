<?php

use App\LegacyImport\Sync\LegacyBlogCommentsSync;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('sync imports legacy blog comments when posts exist', function () {
    $authorId = DB::table('users')->insertGetId([
        'first_name' => 'Legacy',
        'last_name' => 'Author',
        'slug' => 'legacy-blog-author',
        'username' => 'legacyblogauthor',
        'email' => 'legacy-blog-author@example.test',
        'password' => bcrypt('secret'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ([62, 63, 75] as $postId) {
        DB::table('blog_posts')->updateOrInsert(
            ['id' => $postId],
            [
                'user_id' => $authorId,
                'slug' => 'legacy-post-'.$postId,
                'title' => 'Legacy post '.$postId,
                'content' => 'Body',
                'is_published' => true,
                'published_at' => now(),
                'legacy_id' => $postId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    $synced = app(LegacyBlogCommentsSync::class)->sync();

    expect($synced)->toBeGreaterThanOrEqual(10);
    $this->assertDatabaseHas('blog_comments', [
        'legacy_id' => 1,
        'email' => 'somtochukwutochi955@gmail.com',
        'status' => 'pending',
    ]);
});
