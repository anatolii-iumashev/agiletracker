<?php

declare(strict_types=1);

namespace App\Rest\Controllers;

use App\Rest\Resources\ItemResource;

class ItemsController extends Controller
{
    public static string $resource = ItemResource::class;
}
