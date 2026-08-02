<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Forgot / reset password for the SPA.
 *
 * Both endpoints are public, so they deliberately never reveal whether an
 * email address exists: the "sent" response is identical either way and the
 * real outcome only goes to the log.
 */
class PasswordResetController extends Controller
{
    private const GENERIC_SENT = 'If that email address is registered, a password reset link is on its way.';

    /**
     * POST /api/password/forgot
     */
    public function sendResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Enter a valid email address',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (\Exception $e) {
            // A misconfigured mailer must not look like a bad email address
            Log::error('Password reset mail failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'The reset email could not be sent. Please contact your administrator.',
            ], 500);
        }

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'success' => false,
                'message' => 'A reset link was just sent. Please wait a minute before asking for another.',
            ], 429);
        }

        Log::info('Password reset requested', [
            'email' => $request->email,
            'status' => $status,
        ]);

        // RESET_LINK_SENT and INVALID_USER both answer the same way
        return response()->json([
            'success' => true,
            'message' => self::GENERIC_SENT,
        ]);
    }

    /**
     * POST /api/password/reset
     */
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                // The User model hashes on assignment (setPasswordAttribute)
                $user->password = $password;
                $user->setRememberToken(Str::random(60));
                $user->save();

                // Every existing session is invalidated: a reset is the one
                // action that must lock out whoever knew the old password.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Your password has been reset. You can sign in with it now.',
            ]);
        }

        // The broker only ever returns these four failure statuses - an expired
        // token comes back as INVALID_TOKEN, there is no EXPIRED_TOKEN constant.
        $messages = [
            Password::INVALID_TOKEN => 'This reset link is invalid, expired, or has already been used. Request a new one.',
            Password::INVALID_USER => 'This reset link is invalid, expired, or has already been used. Request a new one.',
            Password::RESET_THROTTLED => 'Too many attempts. Please wait a minute and try again.',
        ];

        return response()->json([
            'success' => false,
            'message' => $messages[$status] ?? 'Could not reset your password. Request a new link.',
        ], 422);
    }
}
