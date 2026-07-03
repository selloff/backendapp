<?php

namespace App\Console\Commands;

use App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminSeoController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'selloff:generate-sitemap';

    protected $description = 'Generate a sitemap XML file using platform SEO settings';

    public function handle(AdminSeoController $controller): int
    {
        $response = $controller->generateSitemap(Request::create('/', 'POST'));
        $payload = $response->getData(true);

        if (($payload['success'] ?? false) !== true) {
            $this->error('Sitemap generation failed.');

            return self::FAILURE;
        }

        $filename = $payload['data']['filename'] ?? 'sitemap.xml';
        $url = $payload['data']['url'] ?? '';
        $this->info('Sitemap generated: '.$filename);
        if ($url !== '') {
            $this->line($url);
        }

        return self::SUCCESS;
    }
}
