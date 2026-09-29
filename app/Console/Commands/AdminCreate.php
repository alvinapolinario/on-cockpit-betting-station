<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates an administrator account (use it for the first admin on a new server):
 *   artisan admin:create --username=admin --name="System Administrator"
 * The password is asked for interactively, or read from ADMIN_NEW_PASSWORD.
 */
class AdminCreate extends Command
{
  protected $signature = 'admin:create {--username= : Login name} {--name= : Full name}';
  protected $description = 'Create an administrator account';

  public function handle(): int
  {
    $username = (string) $this->option('username');
    $name = (string) ($this->option('name') ?: $username);
    if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) {
      $this->error('--username is required: 3-60 letters, numbers, dot, dash or underscore.');
      return self::FAILURE;
    }
    if (DB::table('accounts')->where('username', $username)->exists()) {
      $this->error("The username \"{$username}\" already exists.");
      return self::FAILURE;
    }
    $password = getenv('ADMIN_NEW_PASSWORD') ?: $this->secret('Password (min 10 characters, letters and numbers)');
    if (!is_string($password) || strlen($password) < 10 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
      $this->error('Password must be at least 10 characters and contain letters and numbers.');
      return self::FAILURE;
    }

    DB::transaction(function () use ($username, $name, $password) {
      $accountId = DB::table('accounts')->insertGetId([
        'username' => $username, 'password' => Hash::make($password), 'account_type' => 'Admin',
        'is_active' => 1, 'account_date_created' => now(),
      ]);
      DB::table('admins')->insert(['account_id' => $accountId, 'admin_name' => $name]);
      DB::table('transactions')->insert([
        'account_id' => $accountId, 'transaction_type' => 'Web App',
        'transaction_message' => "Admin account \"{$username}\" created from the server console",
        'transaction_datetime' => now(),
      ]);
    });
    $this->info("Administrator \"{$username}\" created.");
    return self::SUCCESS;
  }
}
