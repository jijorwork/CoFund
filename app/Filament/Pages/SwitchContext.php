<?php

namespace App\Filament\Pages;

use App\Domain\Identity\Actions\SwitchActiveRoleContext;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Services\ListAvailableUserRoleContexts;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

/**
 * @property-read Collection<int, OrganisationUserRole> $contexts
 */
class SwitchContext extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Overview';

    protected static ?string $navigationLabel = 'Switch organisation or role';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.switch-context';

    public static function canAccess(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::FilamentAccess->value);
    }

    /** @return Collection<int, OrganisationUserRole> */
    #[Computed]
    public function contexts(): Collection
    {
        $user = auth()->user();

        return $user instanceof User
            ? app(ListAvailableUserRoleContexts::class)->forUser($user)
            : new Collection;
    }

    public function switchContext(string $assignmentUuid): void
    {
        $user = auth()->user();
        $assignment = $this->contexts
            ->first(fn (OrganisationUserRole $assignment): bool => $assignment->public_id === $assignmentUuid);

        if (! $user instanceof User || ! $assignment instanceof OrganisationUserRole) {
            abort(403);
        }

        app(SwitchActiveRoleContext::class)->handle(
            $user,
            $assignment->organisation,
            $assignment->branch,
            $assignment,
            request()->session(),
        );

        unset($this->contexts);

        Notification::make()
            ->success()
            ->title('Active context changed')
            ->body(
                $assignment->organisation->name.' · '
                .SystemRole::from($assignment->role->name)->label(),
            )
            ->send();

        $this->redirect(Dashboard::getUrl(), navigate: true);
    }
}
