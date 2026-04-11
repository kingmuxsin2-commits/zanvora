<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\LoginToken;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use App\Mail\MagicLinkMail;

class MagicLinkController extends Controller
{
    public function request(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)
            ->where('role', 'supplier')
            ->first();

        if (!$user || !$user->supplier?->is_approved) {
            // Return success anyway to prevent email enumeration
            return response()->json(['message' => 'If the email exists and is approved, a magic link has been sent.']);
        }

        $token = LoginToken::generateFor($user);

        Mail::to($user->email)->send(new MagicLinkMail($token->token, $user));

        return response()->json(['message' => 'Magic link sent to your email.']);
    }

    public function verify(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        $loginToken = LoginToken::where('token', $request->token)->first();

        if (!$loginToken || !$loginToken->isValid()) {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        $loginToken->markUsed();

        $user = $loginToken->user;
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user->load('supplier'),
            'token' => $token,
        ]);
    }
}