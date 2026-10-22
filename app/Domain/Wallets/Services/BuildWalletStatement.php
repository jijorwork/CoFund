<?php

namespace App\Domain\Wallets\Services;

use App\Domain\Accounting\Enums\FinancialTransactionStatus;
use App\Domain\Wallets\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BuildWalletStatement
{
    public function __construct(private CalculateWalletBalance $balances) {}

    /** @return array<string, mixed> */
    public function build(Wallet $wallet, CarbonImmutable $from, CarbonImmutable $to, int $page = 1, int $perPage = 50): array
    {
        $wallet->loadMissing('ledgerAccount');
        $opening = $this->balances->calculate($wallet, $from->subDay());
        $closing = $this->balances->calculate($wallet, $to);
        $normalEntryType = $wallet->ledgerAccount->normal_balance->value;

        $entries = DB::table('transaction_entries as entries')
            ->join('financial_transactions as tx', 'tx.id', '=', 'entries.financial_transaction_id')
            ->where('entries.organisation_id', $wallet->organisation_id)
            ->where('tx.organisation_id', $wallet->organisation_id)
            ->where('entries.wallet_id', $wallet->getKey())
            ->where('entries.currency_code', $wallet->currency_code)
            ->whereIn('tx.status', [FinancialTransactionStatus::Posted->value, FinancialTransactionStatus::Reversed->value])
            ->whereDate('tx.transaction_date', '>=', $from->toDateString())
            ->whereDate('tx.transaction_date', '<=', $to->toDateString())
            ->select([
                'entries.public_id as entry_uuid',
                'entries.entry_type',
                'entries.amount_minor',
                'entries.description as entry_description',
                'entries.sequence_number',
                'tx.public_id as transaction_uuid',
                'tx.transaction_number',
                'tx.transaction_date',
                'tx.description as transaction_description',
                'tx.status as transaction_status',
            ])
            ->selectRaw(
                'SUM(CASE WHEN entries.entry_type = ? THEN entries.amount_minor ELSE -entries.amount_minor END) '
                .'OVER (ORDER BY tx.transaction_date, tx.id, entries.sequence_number, entries.id '
                .'ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) as running_delta_minor',
                [$normalEntryType],
            )
            ->orderBy('tx.transaction_date')
            ->orderBy('tx.id')
            ->orderBy('entries.sequence_number')
            ->orderBy('entries.id')
            ->simplePaginate($perPage, ['*'], 'page', $page);

        return [
            'wallet_uuid' => $wallet->public_id,
            'currency_code' => $wallet->currency_code,
            'normal_balance' => $wallet->ledgerAccount->normal_balance->value,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'opening_balance_minor' => $opening->displayBalanceMinor,
            'closing_balance_minor' => $closing->displayBalanceMinor,
            'page' => $entries->currentPage(),
            'per_page' => $perPage,
            'has_more' => $entries->hasMorePages(),
            'entries' => array_map(static function (object $entry) use ($opening): array {
                $row = get_object_vars($entry);

                return [
                    'entry_uuid' => $row['entry_uuid'],
                    'transaction_uuid' => $row['transaction_uuid'],
                    'transaction_number' => $row['transaction_number'],
                    'transaction_date' => $row['transaction_date'],
                    'transaction_status' => $row['transaction_status'],
                    'entry_type' => $row['entry_type'],
                    'amount_minor' => (int) $row['amount_minor'],
                    'description' => $row['entry_description'] ?? $row['transaction_description'],
                    'running_balance_minor' => $opening->displayBalanceMinor + (int) $row['running_delta_minor'],
                ];
            }, $entries->items()),
        ];
    }
}
