<?php

namespace App\Enums;

enum TransactionAction: string
{
    case Addition = 'addition';
    case Deduction = 'deduction';
    case Neutral = 'neutral';
}
