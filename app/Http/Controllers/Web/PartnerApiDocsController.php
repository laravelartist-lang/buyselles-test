<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Partner\PartnerApiDocumentationExamplesService;
use App\Services\Partner\PartnerPostmanCollectionService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PartnerApiDocsController extends Controller
{
    public function index(PartnerApiDocumentationExamplesService $examplesService): View
    {
        return view('partner-api.documentation', [
            'apiExamples' => $examplesService->build(),
            'apiExampleFormatter' => $examplesService,
        ]);
    }

    public function downloadPostman(PartnerPostmanCollectionService $postmanService): StreamedResponse
    {
        $json = $postmanService->generate();

        return response()->streamDownload(
            static function () use ($json): void {
                echo $json;
            },
            'Buyselles_Partner_API.postman_collection.json',
            ['Content-Type' => 'application/json'],
        );
    }
}
