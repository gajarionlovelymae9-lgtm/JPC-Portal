<?php

namespace App\Enums;

enum PeriodType: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';
}
