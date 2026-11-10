<?php

namespace App\Http\Controllers\Concerns;

use App\Mail\AccountMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Accounts are never given a password by the person creating them. The system
 * makes a random temporary one, emails it, and the owner must replace it at
 * first sign-in (EnsureUserIsActive enforces that).
 */
trait IssuesTemporaryPassword
{
    private function generateTemporaryPassword(): string
    {
        // No look-alike characters (0/O, 1/l/I) so it can be read out or typed from a printout.
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $password = '';
        for ($i = 0; $i < 10; $i++) $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];

        return $password;
    }

    /** Sets a fresh temporary password on the account and returns it in plain text (shown once, never stored). */
    private function issueTemporaryPassword(User $user): string
    {
        $plain = $this->generateTemporaryPassword();
        $user->forceFill([
            'password' => Hash::make($plain),
            'must_change_password' => true,
            'temp_password_expires_at' => now()->addHours(User::TEMP_PASSWORD_VALID_HOURS),
        ])->save();
        // Anything signed in with the old password is signed out.
        $user->tokens()->delete();

        return $plain;
    }

    /** True when the email was handed to the mail server. A failure is logged, not thrown. */
    private function emailCredentials(User $user, string $plain, string $createdBy): bool
    {
        try {
            Mail::to($user->email)->send(new AccountMail($user, 'Your VMS account is ready', 'credentials', [
                'temporaryPassword' => $plain,
                'createdBy' => $createdBy,
                'expiresAt' => $user->temp_password_expires_at,
            ]));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
