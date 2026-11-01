<?php

namespace App\Enums;

/**
 * Derived from the order's jobs, Order::syncStatus()
 */
enum OrderStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
