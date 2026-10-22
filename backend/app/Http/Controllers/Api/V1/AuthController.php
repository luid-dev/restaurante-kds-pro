<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\PinLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->auth->loginWithPassword($request, $request->string('email')->toString(), $request->string('password')->toString());

        return $this->sessionResponse($request, $user, 'Sesión iniciada.');
    }

    public function pinLogin(PinLoginRequest $request): JsonResponse
    {
        $user = $this->auth->loginWithPin($request, $request->string('company_ruc')->toString(), $request->string('pin_code')->toString());

        return $this->sessionResponse($request, $user, 'Terminal desbloqueada.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request);

        return $this->success(message: 'Sesión cerrada.');
    }

    private function sessionResponse(Request $request, User $user, string $message): JsonResponse
    {
        return $this->success(UserResource::make($user), $message, meta: [
            'auth_method' => $this->auth->sessionMethod($request)?->value,
            'pin_idle_timeout_minutes' => config()->integer('auth.pin.idle_timeout_minutes', 30),
        ]);
    }
}
