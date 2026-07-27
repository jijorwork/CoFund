<?php

namespace App\Domain\Accounting\Models;

use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Wallets\Models\Wallet;
use Database\Factories\Domain\Accounting\Models\TransactionEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** @property EntryType $entry_type */
#[Fillable([
    'public_id', 'organisation_id', 'financial_transaction_id',
    'ledger_account_id', 'entry_type', 'amount_minor', 'currency_code',
    'description', 'member_id', 'wallet_id', 'sequence_number',
])]
class TransactionEntry extends Model
{
    /** @use HasFactory<TransactionEntryFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Transaction entries are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Transaction entries cannot be deleted.'));
    }

    protected static function newFactory(): TransactionEntryFactory
    {
        return TransactionEntryFactory::new();
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['entry_type' => EntryType::class];
    }
}
