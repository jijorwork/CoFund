<?php

namespace App\Domain\Withdrawals\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organisation_id', 'sequence_type', 'sequence_year', 'next_number'])]
class WithdrawalNumberSequence extends Model {}
