<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * `make:filament-user` creates users with `is_admin` false, so they cannot reach the
 * panel until they are granted access here. That is the intended flow: creating an
 * account and granting it the reservations dashboard are two separate decisions.
 */
#[Signature('admin:grant {email : The email address of an existing user} {--revoke : Remove panel access instead of granting it}')]
#[Description('Grant or revoke Filament admin panel access for a user')]
class GrantAdminAccess extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user with the email {$email}.");
            $this->line('Create one first with: php artisan make:filament-user');

            return self::FAILURE;
        }

        $revoking = (bool) $this->option('revoke');

        if ($revoking && User::where('is_admin', true)->where('id', '!=', $user->id)->doesntExist()) {
            $this->error("Refusing to revoke {$email} — they are the only admin, and this would lock everyone out of /admin.");

            return self::FAILURE;
        }

        $user->is_admin = ! $revoking;
        $user->save();

        $this->info($revoking
            ? "Revoked panel access for {$email}."
            : "Granted panel access to {$email}.");

        return self::SUCCESS;
    }
}
