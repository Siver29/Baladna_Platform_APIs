<?php

namespace App\Enums;

enum Role: string
{
    case Citizen = 'citizen';
    case Employee = 'employee';
    case Admin = 'admin';

    /**
     * Determine if this role is the citizen role.
     */
    public function isCitizen(): bool
    {
        return $this === self::Citizen;
    }

    /**
     * Determine if this role is the employee role.
     */
    public function isEmployee(): bool
    {
        return $this === self::Employee;
    }

    /**
     * Determine if this role is the admin role.
     */
    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }
}
