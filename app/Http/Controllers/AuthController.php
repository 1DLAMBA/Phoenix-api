<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Hospital;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * One sign-in for every account type. Admins and hospitals live in their
     * own tables and get a Sanctum token; anyone else falls through to the
     * regular user login, which keeps its email-verification handling.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $admin = Admin::where('email', $request->email)->first();
        if ($admin && Hash::check($request->password, $admin->password)) {
            return $this->tokenResponse($admin, 'admin');
        }

        $hospital = Hospital::where('email', $request->email)->first();
        if ($hospital && $hospital->password && Hash::check($request->password, $hospital->password)) {
            return $this->tokenResponse($hospital, 'hospital');
        }

        return app(UserController::class)->login($request);
    }

    private function tokenResponse(Admin|Hospital $account, string $type)
    {
        return response()->json([
            'token' => $account->createToken($type . '-token')->plainTextToken,
            'user' => array_merge($account->toArray(), ['user_type' => $type]),
        ]);
    }
}
