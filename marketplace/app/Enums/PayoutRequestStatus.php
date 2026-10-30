<?php

namespace App\Enums;

enum PayoutRequestStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';
}
