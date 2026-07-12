<?php

namespace App\Console\Commands;

use App\Services\Partner\PartnerPostmanCollectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportPartnerPostmanCollection extends Command
{
    protected $signature = 'partner:export-postman {--path=storage/app/Buyselles_Partner_API.postman_collection.json : Output file path}';

    protected $description = 'Export the Partner API Postman collection with live sample product IDs';

    public function handle(PartnerPostmanCollectionService $postmanService): int
    {
        $path = base_path($this->option('path'));
        $json = $postmanService->generate();

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $json);

        $this->info('Partner API Postman collection exported to: '.$path);

        return self::SUCCESS;
    }
}
