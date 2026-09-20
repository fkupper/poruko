<?php

namespace App\Http\Resources;

use App\Models\StatementImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StatementImport
 */
class StatementImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'status' => $this->status,
            'stage' => $this->stage,
            'filename' => $this->original_filename,
            'parsed_count' => $this->parsed_count,
            'pending_count' => $this->pending_count,
            'duplicate_count' => $this->duplicate_count,
            'failed_count' => $this->failed_count,
            'progress_current' => $this->progress_current,
            'progress_total' => $this->progress_total,
            'error_message' => $this->error_message,
            'processed_at' => $this->processed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
