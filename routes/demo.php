<?php

use App\Http\Controllers\Demo\SecretOrcaCatalogDemoController;
use Illuminate\Support\Facades\Route;

Route::get('/demo/secretorca-catalog', SecretOrcaCatalogDemoController::class)
    ->name('demo.secretorca-catalog');
