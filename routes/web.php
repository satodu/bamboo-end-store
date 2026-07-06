<?php

use Illuminate\Support\Facades\Route;

use Illuminate\Support\Facades\File;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/appimage/icon/{id}', function ($id) {
    $iconDir = getenv('HOME') . '/.local/share/icons';
    
    $extensions = ['png', 'svg', 'jpg', 'jpeg'];
    foreach ($extensions as $ext) {
        $path = $iconDir . '/appimage-' . $id . '.' . $ext;
        if (File::exists($path)) {
            $mime = $ext === 'svg' ? 'image/svg+xml' : 'image/' . $ext;
            return response()->file($path, [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }
    }
    
    return response('Icon not found', 404);
});
