<?php

declare(strict_types=1);

namespace App\Rest\Controllers;

use App\Rest\Resources\LabelResource;

class LabelsController extends Controller
{
    public static string $resource = LabelResource::class;
}
