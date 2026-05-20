<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\UserDevice;
use App\Events\UserOnlineStatusChanged;

class DeviceController extends Controller
{
    public function heartbeat(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string',
        ]);

        $user = $request->user();

        // Cập nhật hoặc tạo thiết bị
        UserDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $request->device_id,
            ],
            [
                'last_active_at' => now(),
            ]
        );

        $wasOffline = !$user->is_online;

        // Cập nhật trạng thái user
        $user->is_online = true;
        $user->last_active_at = now();
        $user->save();

        if ($wasOffline) {
            broadcast(new UserOnlineStatusChanged($user->id, true, $user->last_active_at));
        }

        return response()->json(['success' => true]);
    }
}
