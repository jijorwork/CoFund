<?php

namespace App\Domain\Loans\Enums;

enum ProcessingFeeTreatment: string
{
    case Deducted = 'deducted';
    case AddedToLoan = 'added_to_loan';
}
