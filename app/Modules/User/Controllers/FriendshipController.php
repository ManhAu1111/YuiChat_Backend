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

    public function store(Request $request)
    {
        $request->validate([
            'friend_id' => 'required|integer'
        ]);

        try {
            $this->friendshipService->sendRequest($request->user(), $request->input('friend_id'));
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function accept(Request $request)
    {
        $request->validate([
            'friend_id' => 'required|integer'
        ]);

        try {
            $this->friendshipService->acceptRequest($request->user(), $request->input('friend_id'));
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function decline(Request $request)
    {
        $request->validate([
            'friend_id' => 'required|integer'
        ]);

        try {
            $this->friendshipService->cancelOrDeclineRequest($request->user()->id, $request->input('friend_id'));
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'friend_id' => 'required|integer'
        ]);

        try {
            $this->friendshipService->unfriend($request->user()->id, $request->input('friend_id'));
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
