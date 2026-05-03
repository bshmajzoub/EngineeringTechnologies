<?php

namespace App\Enums;

enum LocationRequestStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
}
