<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyImport extends Model
{
    use HasUuids;

    protected $table = 'property_imports';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'org_id',
        'created_by',
        'module',
        'status',
        'original_filename',
        'stored_path',
        'stored_disk',
        'file_type',
        'source_columns',
        'mapping',
        'preview',
        'summary',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'duplicate_rows',
        'missing_required_rows',
        'imported_rows',
        'failed_rows',
        'skipped_rows',
        'progress',
        'error_report_path',
        'started_at',
        'finished_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'source_columns' => 'array',
            'mapping' => 'array',
            'preview' => 'array',
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
