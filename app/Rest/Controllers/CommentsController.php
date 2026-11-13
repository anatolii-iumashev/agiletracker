<?php

declare(strict_types=1);

namespace App\Rest\Controllers;

use App\Rest\Resources\CommentResource;

class CommentsController extends Controller
{
    public static string $resource = CommentResource::class;
}
