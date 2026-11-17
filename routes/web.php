<?php

use Illuminate\Support\Facades\Route;

// SPA única: / (celular), /display (telão), /master (painel).
Route::view('/{any?}', 'app')->where('any', '^(?!api|up).*$');
