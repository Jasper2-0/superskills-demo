<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use App\Repositories\TagRepository;

final class TagsController
{
    public function __construct(private readonly TagRepository $tags) {}

    public function index(): void
    {
        JsonResponse::ok(['tags' => $this->tags->listWithCounts()]);
    }
}
