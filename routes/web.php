<?php

use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function (Request $request) {
        $user = $request->user();

        foreach (['platform', 'admin'] as $panelId) {
            $panel = Filament::getPanel($panelId);

            if ($user->canAccessPanel($panel)) {
                return redirect($panel->getUrl());
            }
        }

        return view('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
