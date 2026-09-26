<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('app'))->name('home');
Route::get('/{any}', fn () => view('app'))->where('any', '^(?!api|up|storage|build).*$');
