<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CurrentUserController extends Controller
{
    public function __invoke(Request $request, AuthService $auth): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->success(UserResource::make($auth->loadProfile($user)), meta: [
            'auth_method' => $auth->sessionMethod($request)?->value,
            'pin_idle_timeout_minutes' => config()->integer('auth.pin.idle_timeout_minutes', 30),
        ]);
    }
}
