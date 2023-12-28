<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Account\AccountController;
use App\Http\Controllers\API\Permissions\PermissionController;
use App\Http\Controllers\API\Roles\RoleController;
use App\Http\Controllers\API\Roles\RolePermissionsController;
use App\Http\Controllers\API\Categories\CategoryController;
use App\Http\Controllers\API\Users\UserController;
use App\Http\Controllers\API\Users\UserPermissionsController;
use App\Http\Controllers\API\Users\UserRolesController;
use App\Http\Controllers\API\Tags\TagController;
use App\Http\Controllers\API\Links\LinkController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::controller(AuthController::class)->group(function() {
    Route::post('sessions', 'login');
    Route::delete('sessions', 'logout');
});

// allow only 3 actions / minute for the same session
Route::middleware(['throttle:3,1'])->group( function () {
    Route::controller(AccountController::class)->group(function() {
        Route::post('accounts', 'register');
        Route::post('accounts/requests', 'handleAccountRequests');
        // allow users who don't have the email verified, to send emails
        Route::middleware(['auth:sanctum'])->group( function () {
            Route::post('accounts/emails', 'sendEmail');
        });
    });
});


Route::middleware(['auth:sanctum', 'checkstatus'])->group( function () {
    Route::controller(AccountController::class)->group(function() {
        Route::get('accounts', 'getProfile');
        Route::put('accounts', 'updateProfile');
        Route::patch('accounts', 'partialUpdateProfile');
    });

     // admin section actions
    //  Route::group(['middleware' => 'role:admin'], function () {

        // permissions actions
        Route::controller(PermissionController::class)->group(function() {
            Route::get('permissions', 'list');
            Route::get('permissions/exports', 'export');
            Route::post('permissions', 'store');
            Route::get('permissions/{id}', 'getItem');
            Route::put('permissions/{id}', 'update');
            Route::delete('permissions', 'deleteSelected');
            Route::delete('permissions/{id}', 'delete');
        });

        // role permissions actions
        Route::controller(RolePermissionsController::class)->group(function() {
            Route::get('roles/{id}/permissions', 'list');
            Route::put('roles/{id}/permissions', 'update');
            Route::get('roles/{id}/permissions/exports', 'export');
        });

        // roles actions
        Route::controller(RoleController::class)->group(function() {
            Route::get('roles', 'list');
            Route::get('roles/exports', 'export');
            Route::post('roles', 'store');
            Route::get('roles/{id}', 'getItem');
            Route::put('roles/{id}', 'update');
            Route::delete('roles', 'deleteSelected');
            Route::delete('roles/{id}', 'delete');
        });

        // category actions
        Route::controller(CategoryController::class)->group(function() {
            Route::get('categories', 'list');
            Route::get('categories/exports', 'export');
            Route::post('categories', 'store');
            Route::get('categories/{id}', 'getItem');
            Route::put('categories/{id}', 'update');
            Route::delete('categories', 'deleteSelected');
            Route::delete('categories/{id}', 'delete');
        });


        // user actions
        Route::controller(UserController::class)->group(function() {
            Route::get('users', 'list');
            Route::post('users', 'store');
            Route::get('users/exports', 'export');
            Route::get('users/{id}', 'getItem');
            Route::put('users/{id}', 'update');
            Route::delete('users', 'deleteSelected');
            Route::delete('users/{id}', 'delete');
            Route::delete('users/{id}/requests', 'handleRequests');
        });

        // user roles actions
        Route::controller(UserRolesController::class)->group(function() {
            Route::get('users/{id}/roles', 'list');
            Route::put('users/{id}/roles', 'update');
            Route::get('users/{id}/roles/exports', 'export');
        });

        // user permissions actions
        Route::controller(UserPermissionsController::class)->group(function() {
            Route::get('users/{id}/permissions', 'list');
            Route::put('users/{id}/permissions', 'update');
            Route::get('users/{id}/permissions/exports', 'export');
        });
    // });

    // tags actions
    Route::controller(TagController::class)->group(function() {
        Route::get('tags', 'list');
        Route::get('tags/exports', 'export');
        Route::post('tags', 'store');
        Route::get('tags/{id}', 'getItem');
        Route::put('tags/{id}', 'update');
        Route::delete('tags', 'deleteSelected');
        Route::delete('tags/{id}', 'delete');

    });

    // links actions
    Route::controller(LinkController::class)->group(function() {
        Route::get('links', 'list');
        Route::get('links/exports', 'export');
        Route::post('links', 'store');
        Route::get('links/{id}', 'getItem');
        Route::put('links/{id}', 'update');
        Route::delete('links', 'deleteSelected');
        Route::delete('links/{id}', 'delete');
    });


});

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
