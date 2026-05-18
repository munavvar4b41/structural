<?php

namespace App\Http\Controllers\Api\Desktop;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\DesktopTraySnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesktopTrayController extends Controller
{
    public function __construct(private readonly DesktopTraySnapshot $snapshot)
    {
        //
    }

    public function show(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        return response()->json($this->snapshot->build($actor));
    }
}
