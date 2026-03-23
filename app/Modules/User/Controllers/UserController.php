<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->query('q');

        if (!$query) {
            return response()->json([]);
        }

        $users = User::where(function ($q) use ($query) {
                $q->where('username', 'like', "%{$query}%")
                  ->orWhere('name', 'like', "%{$query}%");
            })
            ->where('id', '!=', auth()->id())
            ->limit(10)
            ->get(['id', 'name', 'username', 'avatar']);

        return response()->json($users);
    }
}
