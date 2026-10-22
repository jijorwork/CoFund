<?php

namespace App\Filament\Platform\Resources\OrganisationSubscriptions\Pages;

use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Subscriptions\Actions\StartOrganisationSubscription;
use App\Domain\Subscriptions\Enums\BillingInterval;
use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Filament\Platform\Resources\OrganisationSubscriptions\OrganisationSubscriptionResource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListOrganisationSubscriptions extends ListRecords
{
    protected static string $resource = OrganisationSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('startSubscription')
                ->label('Start subscription')
                ->form([
                    Select::make('organisation_id')
                        ->options(Organisation::query()->where('status', OrganisationStatus::Active)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->required(),
                    Select::make('subscription_plan_id')
                        ->options(SubscriptionPlan::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->required(),
                    Select::make('billing_interval')
                        ->options(['monthly' => 'Monthly', 'yearly' => 'Yearly'])
                        ->required(),
                    DatePicker::make('starts_on')->default(today())->required(),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    app(StartOrganisationSubscription::class)->handle(
                        $actor,
                        Organisation::query()->findOrFail((int) $data['organisation_id']),
                        SubscriptionPlan::query()->findOrFail((int) $data['subscription_plan_id']),
                        BillingInterval::from((string) $data['billing_interval']),
                        CarbonImmutable::parse((string) $data['starts_on']),
                    );
                }),
        ];
    }
}
