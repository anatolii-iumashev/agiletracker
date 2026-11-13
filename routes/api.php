<?php

declare(strict_types=1);

use App\Rest\Controllers\CommentsController;
use App\Rest\Controllers\ItemsController;
use App\Rest\Controllers\LabelsController;
use App\Rest\Controllers\UsersController;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'throttle:api'])
    ->group(function (): void {
        Rest::resource('items', ItemsController::class)
            ->only('details', 'search', 'mutate', 'operate', 'destroy');

        Rest::resource('users', UsersController::class)
            ->only('details', 'search', 'mutate');

        Rest::resource('labels', LabelsController::class)
            ->only('details', 'search', 'mutate', 'destroy');

        Rest::resource('comments', CommentsController::class)
            ->only('details', 'search', 'mutate', 'destroy');
    });
