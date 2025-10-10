<?php

namespace App\Services\Tax\Contracts;

interface ProvidesKeys
{
    /**
     * @return array<int, string>
     */
    public static function provides(): array;
}