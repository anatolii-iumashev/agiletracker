<?php

declare(strict_types=1);

namespace App\Rest\Controllers;

use App\Rest\Resources\UserResource;

class UsersController extends Controller
{
    public static string $resource = UserResource::class;
}
