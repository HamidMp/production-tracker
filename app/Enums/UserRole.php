<?php

namespace App\Enums;

/**
 * The exercise has three fixed roles, so an enum is enough.
 * In production roles and permissions would live in the database (e.g. spatie/laravel-permission), so they can change without a deploy
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Planner = 'planner';
    case Installer = 'installer';
}
