<?php

use App\Domain\Approvals\Rules\EnsureDifferentActor;
use App\Domain\Identity\Auditing\ActingContext;

test('same user remains the same actor even after a role switch', function () {
    $firstRole = new ActingContext(10, 20, null, 30, 40);
    $secondRole = new ActingContext(10, 20, null, 31, 41);
    $rule = new EnsureDifferentActor;

    expect(fn () => $rule->handle($firstRole, 10))->toThrow(DomainException::class)
        ->and(fn () => $rule->handle($secondRole, 10))->toThrow(DomainException::class);
});

test('a different authenticated actor passes the separation rule', function () {
    $context = new ActingContext(11, 20, null, 30, 40);

    (new EnsureDifferentActor)->handle($context, 10);

    expect(true)->toBeTrue();
});

test('missing actor context fails safely through the type boundary', function () {
    (new EnsureDifferentActor)->handle(null, 10);
})->throws(TypeError::class);
