<?php

namespace App\Jobs;

use App\Models\UserDevice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Illuminate\Support\Facades\Log;

class SendFcmNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userIds;
    protected $title;
    protected $body;
    protected $data;

    /**
     * Create a new job instance.
     *
     * @param array $userIds
     * @param string $title
     * @param string $body
     * @param array $data
     */
    public function __construct(array $userIds, string $title, string $body, array $data = [])
    {
        $this->userIds = $userIds;
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(Messaging $messaging): void
    {
        // Get all active devices for the users
        $devices = UserDevice::whereIn('user_id', $this->userIds)
            ->whereNotNull('fcm_token')
            ->get();

        if ($devices->isEmpty()) {
            return;
        }

        $notification = Notification::create($this->title, $this->body);

        foreach ($devices as $device) {
            $message = CloudMessage::withTarget('token', $device->fcm_token)
                ->withNotification($notification)
                ->withData($this->data);

            try {
                $messaging->send($message);
            } catch (NotFound $e) {
                // Token is not registered or unregistered by the user
                Log::info("FCM Token is invalid/expired. Removing from database.", ['device_id' => $device->id]);
                $device->update(['fcm_token' => null]);
            } catch (InvalidMessage $e) {
                // Invalid message format
                Log::error("FCM Invalid Message: " . $e->getMessage());
            } catch (\Exception $e) {
                Log::error("FCM Unknown Error: " . $e->getMessage());
            }
        }
    }
}
