<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ProjectSuggestionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ProjectSuggestionService $suggestionService) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $type = (string) $request->query('type', '');
        $query = (string) $request->query('q', '');
        $metadataKey = $request->query('key');
        $metadataKey = is_string($metadataKey) ? $metadataKey : null;

        return response()->json([
            'suggestions' => $this->suggestionService->suggest($actor, $type, $query, $metadataKey),
        ]);
    }
}
