<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use App\Languages;

final class LanguagesController
{
    public function index(): void
    {
        JsonResponse::ok(['languages' => Languages::all()]);
    }
}
