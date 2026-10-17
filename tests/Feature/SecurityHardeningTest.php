<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_private_disk_creates_owner_only_files_even_with_a_permissive_umask(): void
    {
        $root = sys_get_temp_dir().'/homya-private-test-'.Str::uuid();
        $previous = umask(0022);
        try {
            $disk = Storage::build(array_replace(config('filesystems.disks.local'), ['root' => $root]));
            $this->assertTrue($disk->put('nested/fixture.enc', 'synthetic encrypted fixture'));
            clearstatcache();
            $this->assertSame(0600, fileperms($root.'/nested/fixture.enc') & 0777);
            $this->assertSame(0700, fileperms($root.'/nested') & 0777);
            $this->assertSame(0700, fileperms($root) & 0777);
        } finally {
            umask($previous);
            File::deleteDirectory($root);
        }
    }

    public function test_cart_write_limit_is_shared_across_actions_languages_and_sessions(): void
    {
        $this->withoutVite();
        DB::shouldReceive('connection')->never();
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $locale = ['fr', 'en', 'de'][$attempt % 3];
            $this->postJson('/'.$locale.'/panier', [])->assertUnprocessable();
        }
        $this->app['session']->regenerate();
        $this->postJson('/en/panier', [])->assertStatus(429)->assertHeader('Retry-After');
        $this->patchJson('/de/panier/fixture', [])->assertStatus(429)->assertHeader('Retry-After');
        $this->deleteJson('/fr/panier/fixture')->assertStatus(429)->assertHeader('Retry-After');
        // A separate visitor and ordinary browsing do not spend the same write budget.
        $this->withServerVariables(['REMOTE_ADDR' => '::1'])->postJson('/fr/panier', [])->assertUnprocessable();
    }

    public function test_executable_php_files_are_denied_without_rewrite_rules(): void
    {
        $configuration = File::get(public_path('.htaccess'));
        $beforeRewrite = explode('<IfModule mod_rewrite.c>', $configuration, 2)[0];
        preg_match_all('/<FilesMatch "([^"]+)">\s*Require all denied\s*<\/FilesMatch>/', $beforeRewrite, $rules);
        $this->assertNotEmpty($rules[1]);
        $denied = fn (string $file) => collect($rules[1])->contains(fn ($pattern) => preg_match('~'.$pattern.'~', $file) === 1);
        foreach (['payload.php', 'payload.PHP', 'payload.php8', 'payload.php.jpg', 'source.phps', 'upload.pht', '.env', 'backup.sql.gz', 'upload.phtml'] as $file) {
            $this->assertTrue($denied($file), $file.' must be refused independently of rewriting');
        }
        foreach (['index.php', 'image.webp', 'energy-label.pdf', 'products.xml', 'app-hash.js'] as $file) {
            $this->assertFalse($denied($file), $file.' must remain usable');
        }
    }
}
