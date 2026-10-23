<?php

namespace App\Providers;

use App\Domain\Expenses\Models\ExpenseRequest;
use App\Domain\Expenses\Models\PettyCashAdvance;
use App\Domain\Identity\Models\ContextualAccessToken;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberContact;
use App\Domain\Notifications\Contracts\MessagingNotificationProvider;
use App\Domain\Notifications\Contracts\SmsNotificationProvider;
use App\Domain\Notifications\Services\NullMessagingNotificationProvider;
use App\Domain\Notifications\Services\NullSmsNotificationProvider;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->bind(SmsNotificationProvider::class, NullSmsNotificationProvider::class);
        $this->app->bind(MessagingNotificationProvider::class, NullMessagingNotificationProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(ContextualAccessToken::class);

        Relation::enforceMorphMap([
            'member' => Member::class,
            'member_contact' => MemberContact::class,
            'branch' => Branch::class,
            'organisation' => Organisation::class,
            'collector_user' => User::class,
            'custodian_user' => User::class,
            'expense_request' => ExpenseRequest::class,
            'petty_cash_advance' => PettyCashAdvance::class,
        ]);

        RateLimiter::for('api', function (Request $request): Limit {
            $identity = $request->user() === null
                ? 'ip:'.$request->ip()
                : 'user:'.$request->user()->getAuthIdentifier();

            return Limit::perMinute((int) config('api.rate_limit_per_minute'))
                ->by($identity);
        });

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
