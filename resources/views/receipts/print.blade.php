<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $receiptView['receipt_number'] }}</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #17221b;
            background: #eef3ef;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 2rem 1rem;
        }

        .receipt {
            width: min(100%, 48rem);
            margin: 0 auto;
            overflow: hidden;
            border: 1px solid #d9e2db;
            border-radius: 1rem;
            background: #ffffff;
            box-shadow: 0 1rem 3rem rgb(23 34 27 / 8%);
        }

        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1.5rem;
            padding: 2rem;
            color: #ffffff;
            background: #123d27;
        }

        .eyebrow {
            margin: 0 0 .35rem;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #b7dec4;
        }

        h1 {
            margin: 0;
            font-size: 1.6rem;
            line-height: 1.2;
        }

        .status {
            flex: none;
            padding: .35rem .65rem;
            border: 1px solid rgb(255 255 255 / 35%);
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .content {
            display: grid;
            gap: 1.5rem;
            padding: 2rem;
        }

        .amount {
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e5ebe6;
        }

        .amount span {
            display: block;
            margin-bottom: .25rem;
            font-size: .8rem;
            color: #627168;
        }

        .amount strong {
            font-size: 2rem;
            letter-spacing: -.03em;
        }

        .details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem 2rem;
        }

        .detail dt {
            margin-bottom: .25rem;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #718077;
        }

        .detail dd {
            margin: 0;
            overflow-wrap: anywhere;
            font-size: .95rem;
        }

        .verification {
            padding: 1rem;
            border: 1px dashed #9ab7a3;
            border-radius: .75rem;
            background: #f5faf6;
        }

        .verification strong,
        .verification code {
            display: block;
        }

        .verification code {
            margin-top: .5rem;
            overflow-wrap: anywhere;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: .8rem;
            color: #245638;
        }

        .allocations {
            width: 100%;
            border-collapse: collapse;
            font-size: .85rem;
        }

        .allocations th,
        .allocations td {
            padding: .65rem .5rem;
            border-bottom: 1px solid #e5ebe6;
            text-align: left;
        }

        .allocations th {
            font-size: .7rem;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #718077;
        }

        .footer {
            padding: 1rem 2rem 1.5rem;
            font-size: .75rem;
            color: #718077;
            background: #f8faf8;
        }

        @media (max-width: 38rem) {
            body {
                padding: 0;
            }

            .receipt {
                border: 0;
                border-radius: 0;
            }

            .header,
            .content {
                padding: 1.25rem;
            }

            .details {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            @page {
                margin: 12mm;
            }

            body {
                padding: 0;
                background: #ffffff;
            }

            .receipt {
                width: 100%;
                border-color: #b9c5bc;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
<main class="receipt">
    <header class="header">
        <div>
            <p class="eyebrow">CoFund receipt</p>
            <h1>{{ data_get($receiptView, 'organisation.name') }}</h1>
            @if (data_get($receiptView, 'branch.name'))
                <p>{{ data_get($receiptView, 'branch.name') }}</p>
            @endif
        </div>
        <span class="status">{{ $receiptView['status'] }}</span>
    </header>

    <section class="content">
        <div class="amount">
            <span>Amount received</span>
            <strong>{{ $receiptView['amount_formatted'] }}</strong>
        </div>

        <dl class="details">
            <div class="detail">
                <dt>Receipt number</dt>
                <dd>{{ $receiptView['receipt_number'] }}</dd>
            </div>
            <div class="detail">
                <dt>Transaction number</dt>
                <dd>{{ $receiptView['transaction_number'] }}</dd>
            </div>
            <div class="detail">
                <dt>Member</dt>
                <dd>
                    {{ data_get($receiptView, 'member.name') }}
                    ({{ data_get($receiptView, 'member.membership_number') }})
                </dd>
            </div>
            <div class="detail">
                <dt>Payment method</dt>
                <dd>{{ str((string) $receiptView['payment_method'])->headline() }}</dd>
            </div>
            <div class="detail">
                <dt>Payment date</dt>
                <dd>
                    {{ $receiptView['payment_date']
                        ?? data_get($receiptView, 'repayment.date')
                        ?? data_get($receiptView, 'welfare_payment.date')
                        ?? data_get($receiptView, 'withdrawal_payout.date')
                        ?? '—' }}
                </dd>
            </div>
            <div class="detail">
                <dt>Received by</dt>
                <dd>{{ $receiptView['received_by'] }}</dd>
            </div>
            @if (data_get($receiptView, 'plan.name'))
                <div class="detail">
                    <dt>Contribution plan</dt>
                    <dd>
                        {{ data_get($receiptView, 'plan.name') }}
                        ({{ data_get($receiptView, 'plan.code') }})
                    </dd>
                </div>
            @endif
        </dl>

        @if (! empty($receiptView['allocations']))
            <table class="allocations">
                <thead>
                    <tr>
                        <th>Due date</th>
                        <th>Amount allocated</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($receiptView['allocations'] as $allocation)
                        <tr>
                            <td>{{ $allocation['due_date'] }}</td>
                            <td>{{ number_format($allocation['amount_minor'] / 100, 2) }}</td>
                            <td>{{ str($allocation['status'])->headline() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="verification">
            <strong>Receipt verification code</strong>
            <code>{{ $receiptView['verification_code'] }}</code>
        </div>
    </section>

    <footer class="footer">
        Reissued {{ $reissuedAt->toDayDateTimeString() }}.
        This copy does not replace or alter the original financial record.
    </footer>
</main>
</body>
</html>
