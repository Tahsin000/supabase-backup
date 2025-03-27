<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class SupabaseBackupViaPDO extends Command
{
    protected $signature = 'supabase:backup-pdo';
    protected $description = 'Backup Supabase DB and email the .sql file';

    public function handle()
    {
        try {
            $startTime = now();
            $filename = 'supabase_backup_' . $startTime->format('Y-m-d_H-i-s') . '.sql';
            $path = storage_path("app/backups/{$filename}");

            if (!is_dir(storage_path('app/backups'))) {
                mkdir(storage_path('app/backups'), 0755, true);
            }

            Log::info('Starting Supabase database backup process');
            $pdo = DB::connection()->getPdo();

            $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")
                ->fetchAll(\PDO::FETCH_COLUMN);

            Log::info('Found ' . count($tables) . ' tables to backup');
            $sql = "-- Supabase Database Backup\n-- " . now() . "\n\n";

            foreach ($tables as $table) {
                $quotedTable = "\"$table\"";
                $sql .= "-- Table: {$table}\n";

                // Create Table statement (basic)
                $columns = $pdo->prepare("
                    SELECT column_name, data_type, is_nullable, column_default
                    FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = :table
                    ORDER BY ordinal_position
                ");
                $columns->execute(['table' => $table]);
                $cols = $columns->fetchAll(\PDO::FETCH_ASSOC);

                $sql .= "CREATE TABLE {$quotedTable} (\n";
                foreach ($cols as $col) {
                    $line = "  \"{$col['column_name']}\" {$col['data_type']}";
                    if ($col['column_default']) $line .= " DEFAULT {$col['column_default']}";
                    if ($col['is_nullable'] === 'NO') $line .= " NOT NULL";
                    $sql .= $line . ",\n";
                }
                $sql = rtrim($sql, ",\n") . "\n);\n\n";

                // Insert Data
                Log::info("Backing up data for table: {$table}");
                $rows = $pdo->query("SELECT * FROM {$quotedTable}")->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $cols = array_map(fn($col) => "\"$col\"", array_keys($row));
                    $vals = array_map(fn($val) => $val === null ? 'NULL' : $pdo->quote($val), array_values($row));
                    $sql .= "INSERT INTO {$quotedTable} (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $vals) . ");\n";
                }

                $sql .= "\n";
            }

            file_put_contents($path, $sql);
            
            $endTime = now();
            $duration = $startTime->diffInSeconds($endTime);
            $fileSize = round(filesize($path) / (1024 * 1024), 2); // Size in MB
            
            Log::info("Database backup completed successfully", [
                'filename' => $filename,
                'path' => $path,
                'size' => $fileSize . ' MB',
                'tables_count' => count($tables),
                'duration' => $duration . ' seconds'
            ]);

            // Email the backup with modern HTML template
            $htmlContent = $this->getEmailHtmlTemplate([
                'filename' => $filename,
                'fileSize' => $fileSize,
                'tablesCount' => count($tables),
                'duration' => $duration,
                'date' => now()->format('F j, Y'),
                'time' => now()->format('g:i A'),
            ]);

            $recipientEmail = env('BACKUP_RECIPIENT_EMAIL', 'tahsin.blendin@gmail.com');
            $senderEmail = env('MAIL_FROM_ADDRESS', 'noreply@example.com');
            $senderName = env('MAIL_FROM_NAME', 'Database Backup System');

            Mail::html($htmlContent, function ($message) use ($path, $filename, $recipientEmail, $senderEmail, $senderName) {
                $message->to($recipientEmail)
                    ->from($senderEmail, $senderName)
                    ->subject('Database Backup Completed - ' . now()->format('F j, Y'))
                    ->attach($path, [
                        'as' => $filename,
                        'mime' => 'application/sql',
                    ]);
            });

            Log::info("Backup notification email sent successfully", [
                'recipient' => $recipientEmail,
                'sender' => $senderEmail,
                'subject' => 'Database Backup Completed - ' . now()->format('F j, Y')
            ]);
            
            $this->info("✅ Database backup completed and notification sent to {$recipientEmail}");
        } catch (\Throwable $th) {
            Log::error('Database backup process failed', [
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString()
            ]);
            $this->error('Backup failed: ' . $th->getMessage());
        }
    }

    /**
     * Generate a modern HTML email template
     * 
     * @param array $data
     * @return string
     */
    private function getEmailHtmlTemplate(array $data): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Database Backup Completed</title>
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    background-color: #f9f9f9;
                    margin: 0;
                    padding: 0;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    background-color: #ffffff;
                    border-radius: 8px;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
                }
                .header {
                    text-align: center;
                    padding: 20px 0;
                    border-bottom: 1px solid #eaeaea;
                }
                .logo {
                    max-width: 150px;
                    margin-bottom: 15px;
                }
                h1 {
                    color: #2c3e50;
                    font-size: 24px;
                    margin: 0;
                }
                .content {
                    padding: 20px 0;
                }
                .backup-info {
                    background-color: #f8fafc;
                    border-radius: 6px;
                    padding: 20px;
                    margin-bottom: 20px;
                }
                .info-item {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 10px;
                    border-bottom: 1px dashed #eaeaea;
                    padding-bottom: 10px;
                }
                .info-item:last-child {
                    border-bottom: none;
                    margin-bottom: 0;
                    padding-bottom: 0;
                }
                .info-label {
                    font-weight: 600;
                    color: #4a5568;
                }
                .info-value {
                    color: #2d3748;
                }
                .success-icon {
                    color: #38a169;
                    font-size: 48px;
                    text-align: center;
                    margin: 20px 0;
                }
                .message {
                    text-align: center;
                    margin-bottom: 20px;
                }
                .footer {
                    text-align: center;
                    padding-top: 20px;
                    border-top: 1px solid #eaeaea;
                    color: #718096;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Database Backup Completed</h1>
                </div>
                <div class="content">
                    <div class="success-icon">✅</div>
                    <div class="message">
                        <p>Your database backup has been successfully completed and is attached to this email.</p>
                    </div>
                    <div class="backup-info">
                        <div class="info-item">
                            <span class="info-label">Filename:</span>
                            <span class="info-value">{$data['filename']}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">File Size:</span>
                            <span class="info-value">{$data['fileSize']} MB</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Tables Backed Up:</span>
                            <span class="info-value">{$data['tablesCount']}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Duration:</span>
                            <span class="info-value">{$data['duration']} seconds</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Date:</span>
                            <span class="info-value">{$data['date']}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Time:</span>
                            <span class="info-value">{$data['time']}</span>
                        </div>
                    </div>
                    <p>Please store this backup in a secure location. For any questions or issues, please contact your system administrator.</p>
                </div>
                <div class="footer">
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}
