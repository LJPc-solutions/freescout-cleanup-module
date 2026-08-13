<?php

namespace Modules\Cleanup\Models;

use Illuminate\Database\Eloquent\Model;

class AttachmentCleanupLog extends Model
{
    protected $table = 'cleanup_attachment_logs';
    
    protected $fillable = [
        'attachment_id',
        'conversation_id',
        'file_name',
        'mime_type',
        'size',
        'storage_path',
        'attachment_created_at',
        'cleaned_at',
    ];
    
    protected $dates = [
        'attachment_created_at',
        'cleaned_at',
        'created_at',
        'updated_at',
    ];
    
    protected $casts = [
        'size' => 'integer',
        'attachment_id' => 'integer',
        'conversation_id' => 'integer',
    ];
    
    public function getFormattedSizeAttribute()
    {
        $bytes = $this->size;
        
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    public static function logCleanedAttachment($attachment, $storagePath)
    {
        return self::create([
            'attachment_id' => $attachment->id,
            'conversation_id' => $attachment->thread->conversation_id,
            'file_name' => $attachment->file_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'storage_path' => $storagePath,
            'attachment_created_at' => $attachment->thread->created_at,
            'cleaned_at' => now(),
        ]);
    }
    
    public static function getTotalSpaceSaved()
    {
        return self::sum('size');
    }
    
    public static function getCleanupStatsByMonth()
    {
        return self::selectRaw('
                YEAR(cleaned_at) as year,
                MONTH(cleaned_at) as month,
                COUNT(*) as count,
                SUM(size) as total_size
            ')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();
    }
}