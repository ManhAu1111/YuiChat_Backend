<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Services\FriendshipService;
use Illuminate\Http\Request;

class FriendshipController extends Controller
{
    protected FriendshipService $friendshipService;

    public function __construct(FriendshipService $friendshipService)
    {
        $this->friendshipService = $friendshipService;
    }

    /**
     * Get friendship states for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $states = $this->friendshipService->getFriendshipStates($user);

        return response()->json([
            'status' => 'success',
            'data' => $states
        ]);
    }
}
