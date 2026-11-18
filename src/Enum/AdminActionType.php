<?php
namespace App\Enum;

enum AdminActionType: string
{
    case SuspendUser = 'suspend_user';
    case UnsuspendUser = 'unsuspend_user';
    case DeleteUser = 'delete_user';
    case RestoreUser = 'restore_user';
    case SuspendTrip = 'suspend_trip';
    case UnsuspendTrip = 'unsuspend_trip';
    case DeleteTrip = 'delete_trip';
    case RestoreTrip = 'restore_trip';
    case SuspendMessage = 'suspend_message';
    case UnsuspendMessage = 'unsuspend_message';
    case DeleteMessage = 'delete_message';
    case SuspendReview = 'suspend_review';
    case UnsuspendReview = 'unsuspend_review';
    case DeleteReview = 'delete_review';
    case SuspendConversation = 'suspend_conversation';
    case UnsuspendConversation = 'unsuspend_conversation';
    case DeleteConversation = 'delete_conversation';
}