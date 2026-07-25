<?php

namespace App\Enums;

enum InventoryReservationStatus: string
{
    case RESERVED = 'reserved';
    case COMMITTED = 'committed';
    case RELEASED = 'released';
    case EXPIRED = 'expired';
}
