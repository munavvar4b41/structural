<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MyWorkBoardBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyWorkController extends Controller
{
    public function __construct(private readonly MyWorkBoardBuilder $boardBuilder)
    {
        //
    }

    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        return Inertia::render('admin/my-work/Index', $this->boardBuilder->build($actor, $request));
    }
}
