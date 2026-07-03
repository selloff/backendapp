<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\Sync\LegacyBlogCommentsSync;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyBlogCommentsSeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_sync_imports_legacy_blog_comments_when_posts_exist(): void
    {
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

        $this->assertGreaterThanOrEqual(10, $synced);
        $this->assertDatabaseHas('blog_comments', [
            'legacy_id' => 1,
            'email' => 'somtochukwutochi955@gmail.com',
            'status' => 'pending',
        ]);
    }
}
