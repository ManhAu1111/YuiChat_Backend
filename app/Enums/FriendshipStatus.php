<?php
namespace App\Enums;

enum FriendshipStatus: string {
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case DECLINED = 'declined';
    case BLOCKED = 'blocked';
    case UNBLOCKED = 'unblocked';
}
?>