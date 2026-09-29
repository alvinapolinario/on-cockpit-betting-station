<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

class ResetController extends Controller
{
    /**
     * Display the reset page
     */
    public function index()
    {
        // Check if user is admin
        if (session()->get('account_type') !== 'Admin') {
            return redirect('/')->with('error', 'Unauthorized access');
        }

        return view('content.reset.index');
    }

    /**
     * Execute the reset operation
     */
    public function execute(Request $request)
    {
        // Check if user is admin
        if (session()->get('account_type') !== 'Admin') {
            return redirect('/')->with('error', 'Unauthorized access');
        }

        // Validate confirmation
        $request->validate([
            'confirmation' => 'required|in:RESET',
        ], [
            'confirmation.in' => 'You must type "RESET" to confirm the operation.',
        ]);

        try {
            // Create backup before reset
            $backupPath = $this->createDatabaseBackup();

            // Disable foreign key checks to allow truncation
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // Execute the reset queries
            DB::statement('TRUNCATE bets');
            DB::statement('TRUNCATE cash_ins');
            DB::statement('TRUNCATE cash_outs');
            DB::statement('TRUNCATE claims');
            DB::statement('UPDATE event_tellers SET teller_balance = 0, teller_match_balance = 0');
            DB::statement('TRUNCATE matches');
            DB::statement('TRUNCATE personal_access_tokens');
            DB::statement('UPDATE tellers SET phone_uid = ""');
            DB::statement('TRUNCATE transactions');
            DB::statement('TRUNCATE event_tellers');
            DB::statement('TRUNCATE teller_shorts');
            DB::statement('TRUNCATE salaries');
            DB::statement('TRUNCATE teller_remittances');

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            return redirect()->route('reset.index')->with('success', 'System has been successfully reset. All data has been cleared. Backup saved to: ' . $backupPath);

        } catch (\Exception $e) {
            // Re-enable foreign key checks in case of error
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } catch (\Exception $fkException) {
                // Ignore foreign key check error
            }

            return redirect()->route('reset.index')->with('error', 'An error occurred during reset: ' . $e->getMessage());
        }
    }

    /**
     * Create a database backup before reset
     */
    private function createDatabaseBackup()
    {
        $dbConfig = Config::get('database.connections.mysql');
        $dbName = $dbConfig['database'];
        $dbUser = $dbConfig['username'];
        $dbPassword = $dbConfig['password'];
        $dbHost = $dbConfig['host'];
        $dbPort = $dbConfig['port'] ?? 3306;

        // Create backup filename with timestamp
        $timestamp = date('Y-m-d_H-i-s');
        $backupFileName = "backup_before_reset_{$timestamp}.sql";
        $backupPath = storage_path("app/backups/{$backupFileName}");

        // Create backups directory if it doesn't exist
        if (!Storage::disk('local')->exists('backups')) {
            Storage::disk('local')->makeDirectory('backups');
        }

        // Try to find mysqldump executable
        $mysqldumpPath = $this->findMysqldumpPath();

        // Detect OS for proper command formatting
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        // Build mysqldump command (cross-platform)
        if (empty($dbPassword)) {
            if ($isWindows) {
                $command = sprintf(
                    '"%s" -h%s -P%s -u%s %s > "%s"',
                    $mysqldumpPath,
                    $dbHost,
                    $dbPort,
                    $dbUser,
                    $dbName,
                    $backupPath
                );
            } else {
                $command = sprintf(
                    "'%s' -h%s -P%s -u%s %s > '%s'",
                    $mysqldumpPath,
                    $dbHost,
                    $dbPort,
                    $dbUser,
                    $dbName,
                    $backupPath
                );
            }
        } else {
            if ($isWindows) {
                $command = sprintf(
                    '"%s" -h%s -P%s -u%s -p%s %s > "%s"',
                    $mysqldumpPath,
                    $dbHost,
                    $dbPort,
                    $dbUser,
                    $dbPassword,
                    $dbName,
                    $backupPath
                );
            } else {
                $command = sprintf(
                    "'%s' -h%s -P%s -u%s -p'%s' %s > '%s'",
                    $mysqldumpPath,
                    $dbHost,
                    $dbPort,
                    $dbUser,
                    $dbPassword,
                    $dbName,
                    $backupPath
                );
            }
        }

        // Execute the backup command
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            $errorOutput = implode("\n", $output);
            throw new \Exception('Database backup failed with return code: ' . $returnCode . '. Error: ' . $errorOutput);
        }

        // Verify backup file was created and is not empty
        if (!file_exists($backupPath) || filesize($backupPath) === 0) {
            throw new \Exception('Backup file was not created or is empty');
        }

        return $backupFileName;
    }

    /**
     * Find mysqldump executable path
     */
    private function findMysqldumpPath()
    {
        // Detect if we're running on Windows or Linux/WSL
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($isWindows) {
            // Windows/XAMPP paths
            $possiblePaths = [
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\xampp\\mysql\\bin\\mysqldump',
                'mysqldump.exe',
                'mysqldump'
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }

            // Try to find it in Windows PATH
            $output = [];
            exec('where mysqldump 2>nul', $output);
            if (!empty($output)) {
                return trim($output[0]);
            }
        } else {
            // Linux/WSL/Unix paths
            $possiblePaths = [
                '/usr/bin/mysqldump',
                '/usr/local/bin/mysqldump',
                '/opt/lampp/bin/mysqldump',  // XAMPP on Linux
                'mysqldump'
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }

            // Try to find it in Linux PATH
            $output = [];
            exec('which mysqldump 2>/dev/null', $output);
            if (!empty($output)) {
                return trim($output[0]);
            }
        }

        // Default fallback
        return 'mysqldump';
    }
}
