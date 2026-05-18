<?php

namespace App\Http\Controllers\Api\Desktop;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MyWorkBoardBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesktopMyWorkController extends Controller
{
    public function __construct(private readonly MyWorkBoardBuilder $boardBuilder)
    {
        //
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        return response()->json($this->boardBuilder->build($actor, $request));
    }
}
