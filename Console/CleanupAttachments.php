<?php

namespace Modules\Cleanup\Console;

use App\Attachment;
use App\Thread;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Cleanup\Models\AttachmentCleanupLog;

class CleanupAttachments extends Command
{
    protected $signature = 'freescout:cleanup-attachments 
                            {--dry-run : Preview what would be deleted without actually deleting}
                            {--min-age-days=730 : Minimum age in days (default: 730 = 2 years)}
                            {--min-size-kb=300 : Minimum size in KB (default: 300)}
                            {--max-size-mb= : Maximum size in MB (optional)}
                            {--mailbox= : Specific mailbox ID to clean (optional)}
                            {--limit=1000 : Maximum number of attachments to process in one run}
                            {--dangling : Delete database records of attachments whose file no longer exists on disk (ignores age/size filters)}';
    
    protected $description = 'Clean up old and large attachments to save storage space';
    
    protected $deletedCount = 0;
    protected $totalSizeSaved = 0;
    protected $errors = [];
    
    public function handle()
    {
        $this->info('Starting attachment cleanup...');
        
        $dryRun = $this->option('dry-run');
        $minAgeDays = (int) $this->option('min-age-days');
        $minSizeKb = (int) $this->option('min-size-kb');
        $maxSizeMb = $this->option('max-size-mb');
        $mailboxId = $this->option('mailbox');
        $limit = (int) $this->option('limit');
        
        if ($dryRun) {
            $this->info('*** DRY RUN MODE - No files will be deleted ***');
        }

        if ($this->option('dangling')) {
            return $this->cleanupDanglingRecords($dryRun, $mailboxId, $limit);
        }

        $cutoffDate = Carbon::now()->subDays($minAgeDays);
        $minSizeBytes = $minSizeKb * 1024;
        $maxSizeBytes = $maxSizeMb ? $maxSizeMb * 1024 * 1024 : null;
        
        $this->info("Criteria:");
        $this->info("- Older than: {$cutoffDate->format('Y-m-d')} ({$minAgeDays} days)");
        $this->info("- Larger than: {$minSizeKb} KB");
        if ($maxSizeBytes) {
            $this->info("- Smaller than: {$maxSizeMb} MB");
        }
        
        $attachments = $this->findAttachmentsToClean(
            $cutoffDate,
            $minSizeBytes,
            $maxSizeBytes,
            $mailboxId,
            $limit
        );
        
        if ($attachments->isEmpty()) {
            $this->info('No attachments found matching the criteria.');
            return 0;
        }
        
        $this->info("Found {$attachments->count()} attachments to process.");
        
        if (!$dryRun && !$this->confirm('Do you want to proceed with deletion?')) {
            $this->info('Operation cancelled.');
            return 0;
        }
        
        $progressBar = $this->output->createProgressBar($attachments->count());
        $progressBar->start();
        
        foreach ($attachments as $attachment) {
            $this->processAttachment($attachment, $dryRun);
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->line('');
        
        $this->showSummary($dryRun);
        
        return 0;
    }
    
    protected function cleanupDanglingRecords($dryRun, $mailboxId, $limit)
    {
        $this->info('Looking for dangling attachment records (file missing on disk)...');

        $disk = Storage::disk('private');
        $dangling = collect();

        $query = Attachment::query();
        if ($mailboxId) {
            $query->whereHas('thread.conversation', function ($q) use ($mailboxId) {
                $q->where('mailbox_id', $mailboxId);
            });
        }

        $query->orderBy('id')->chunk(500, function ($attachments) use ($disk, $dangling, $limit) {
            foreach ($attachments as $attachment) {
                if (!$disk->exists($attachment->getStorageFilePath())) {
                    $dangling->push($attachment);
                    if ($dangling->count() >= $limit) {
                        return false;
                    }
                }
            }
        });

        if ($dangling->isEmpty()) {
            $this->info('No dangling attachment records found.');
            return 0;
        }

        $this->info("Found {$dangling->count()} dangling attachment records.");

        foreach ($dangling as $attachment) {
            $this->line("- Attachment {$attachment->id}: {$attachment->file_name} ({$attachment->getStorageFilePath()})");
        }

        if ($dryRun) {
            $this->info('Mode: DRY RUN (no records were deleted)');
            return 0;
        }

        if (!$this->confirm('Do you want to delete these database records?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        // Core helper also keeps the has_attachments flags on
        // threads and conversations in sync.
        Attachment::deleteAttachments($dangling);

        $this->info("Deleted {$dangling->count()} dangling attachment records.");

        return 0;
    }

    protected function findAttachmentsToClean($cutoffDate, $minSizeBytes, $maxSizeBytes, $mailboxId, $limit)
    {
        // The attachments table has no timestamps, so age is determined
        // by the created_at of the thread the attachment belongs to.
        $query = Attachment::whereHas('thread', function ($q) use ($cutoffDate) {
                $q->where('created_at', '<', $cutoffDate);
            })
            ->where('size', '>=', $minSizeBytes);
        
        if ($maxSizeBytes) {
            $query->where('size', '<=', $maxSizeBytes);
        }
        
        if ($mailboxId) {
            $query->whereHas('thread.conversation', function ($q) use ($mailboxId) {
                $q->where('mailbox_id', $mailboxId);
            });
        }
        
        $query->whereNotIn('id', function ($q) {
            $q->select('attachment_id')
                ->from('cleanup_attachment_logs');
        });
        
        return $query->with(['thread' => function ($q) {
                $q->select('id', 'conversation_id', 'created_at');
            }])
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();
    }
    
    protected function processAttachment($attachment, $dryRun)
    {
        try {
            $storagePath = $attachment->getStorageFilePath();
            
            if (!$storagePath) {
                $this->errors[] = "Attachment {$attachment->id}: Could not determine storage path";
                return;
            }
            
            $disk = Storage::disk('private');
            
            if (!$disk->exists($storagePath)) {
                $this->errors[] = "Attachment {$attachment->id}: File not found at {$storagePath}";
                return;
            }
            
            if ($dryRun) {
                $this->info("Would delete: {$attachment->file_name} ({$this->formatBytes($attachment->size)})");
            } else {
                if ($disk->delete($storagePath)) {
                    AttachmentCleanupLog::logCleanedAttachment($attachment, $storagePath);
                    $this->deletedCount++;
                    $this->totalSizeSaved += $attachment->size;
                } else {
                    $this->errors[] = "Attachment {$attachment->id}: Failed to delete file";
                }
            }
        } catch (\Exception $e) {
            $this->errors[] = "Attachment {$attachment->id}: " . $e->getMessage();
        }
    }
    
    protected function showSummary($dryRun)
    {
        $this->line('');
        $this->info('=== Cleanup Summary ===');
        
        if ($dryRun) {
            $this->info('Mode: DRY RUN (no files were deleted)');
        } else {
            $this->info("Deleted attachments: {$this->deletedCount}");
            $this->info("Total space saved: {$this->formatBytes($this->totalSizeSaved)}");
        }
        
        if (!empty($this->errors)) {
            $this->line('');
            $this->error('Errors encountered:');
            foreach ($this->errors as $error) {
                $this->error("- {$error}");
            }
        }
        
        if (!$dryRun && $this->deletedCount > 0) {
            $totalSaved = AttachmentCleanupLog::getTotalSpaceSaved();
            $this->line('');
            $this->info("All-time total space saved: {$this->formatBytes($totalSaved)}");
        }
    }
    
    protected function formatBytes($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}