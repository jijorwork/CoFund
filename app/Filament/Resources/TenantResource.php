<?php

namespace App\Filament\Resources;

use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class TenantResource extends Resource
{
    protected static string $viewPermission;

    protected static bool $isGloballySearchable = false;

    protected static ?string $recordRouteKeyName = 'public_id';

    /** @return Builder<Model> */
    public static function getEloquentQuery(): Builder
    {
        $context = app(TenantContext::class);
        $branch = $context->branch();

        return parent::getEloquentQuery()
            ->where('organisation_id', $context->organisation()->getKey())
            ->when(
                $branch,
                fn (Builder $query) => static::scopeToActiveBranch($query, $branch),
            );
    }

    public static function canViewAny(): bool
    {
        return app(RoleAuthorization::class)->can(static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        $context = app(TenantContext::class);
        $branch = $context->branch();

        return static::canViewAny()
            && $record->getAttribute('organisation_id') === $context->organisation()->getKey()
            && (
                $branch === null
                || static::recordMatchesActiveBranch($record, $branch)
            );
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        if (! static::canView($record)) {
            return null;
        }

        $routeKeyName = static::getRecordRouteKeyName() ?? $record->getRouteKeyName();
        $routeKey = $record->getAttribute($routeKeyName);

        if (! is_int($routeKey) && ! is_string($routeKey)) {
            return null;
        }

        if (static::hasPage('view')) {
            return static::getUrl('view', ['record' => $routeKey]);
        }

        if (static::hasPage('edit') && static::canEdit($record)) {
            return static::getUrl('edit', ['record' => $routeKey]);
        }

        return static::getUrl(parameters: [
            'tableAction' => 'view',
            'tableActionRecord' => $routeKey,
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    public static function canReplicate(Model $record): bool
    {
        return false;
    }

    public static function canReorder(): bool
    {
        return false;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected static function scopeToActiveBranch(Builder $query, Branch $branch): Builder
    {
        return $query->where('branch_id', $branch->getKey());
    }

    protected static function recordMatchesActiveBranch(Model $record, Branch $branch): bool
    {
        return $record->getAttribute('branch_id') === $branch->getKey();
    }
}
