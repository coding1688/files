<?php

use Coding1688\Files\Http\Controllers\FileController;
use Coding1688\Files\Http\Controllers\FileStorageAccountController;
use Coding1688\Files\Http\Middleware\AuthorizeManagement;
use Illuminate\Support\Facades\Route;

Route::prefix(config('files.management.prefix', 'files-management'))->name('files.management.')
    ->middleware(array_merge((array) config('files.management.middleware', ['web', 'auth']), [AuthorizeManagement::class]))
    ->group(function () {
        Route::prefix('storage')->name('storage.')->controller(FileStorageAccountController::class)->group(function () {
            Route::get('list', 'list')->name('list');
            Route::get('settings', 'settings')->name('settings');
            Route::get('tasks', 'tasks')->name('tasks');
            Route::post('save-settings', 'saveSettings')->name('saveSettings');
            Route::post('create', 'create')->name('create');
            Route::post('update/{id}', 'update')->whereNumber('id')->name('update');
            Route::post('delete/{id}', 'delete')->whereNumber('id')->name('delete');
            Route::post('sync', 'sync')->name('sync');
            Route::post('retry/{id}', 'retry')->whereNumber('id')->name('retry');
        });
        Route::prefix('item')->name('item.')->controller(FileController::class)->group(function () {
            Route::get('list', 'list')->name('list');
            Route::get('options', 'options')->name('options');
            Route::get('detail/{id}', 'detail')->whereNumber('id')->name('detail');
            Route::post('upload', 'upload')->name('upload');
            Route::post('delete/{id}', 'delete')->whereNumber('id')->name('delete');
        });
    });
