<?php

namespace Modules\Cleanup\Services;

use App\Attachment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Modules\Cleanup\Models\AttachmentCleanupLog;

class AttachmentCleanupService {
	public function preview( array $criteria ): array {
		$attachments = $this->query( $criteria )->get();
		$totalSize   = $attachments->sum( 'size' );

		return [
			'action'               => 'preview',
			'criteria'             => $criteria,
			'count'                => $attachments->count(),
			'total_size'           => $totalSize,
			'formatted_total_size' => $this->formatBytes( $totalSize ),
			'items'                => $attachments->map( function ( $attachment ) {
				return $this->attachmentRow( $attachment );
			} )->all(),
			'errors'               => [],
		];
	}

	public function delete( array $criteria ): array {
		$attachments = $this->query( $criteria )->get();
		$deleted     = 0;
		$totalSize   = 0;
		$errors      = [];

		foreach ( $attachments as $attachment ) {
			try {
				$storagePath = $attachment->getStorageFilePath();
				if ( ! $storagePath ) {
					$errors[] = __( 'Attachment :id: could not determine storage path.', [ 'id' => $attachment->id ] );
					continue;
				}

				$disk = Storage::disk( 'private' );
				if ( ! $disk->exists( $storagePath ) ) {
					$errors[] = __( 'Attachment :id: file not found at :path.', [ 'id' => $attachment->id, 'path' => $storagePath ] );
					continue;
				}

				if ( $disk->delete( $storagePath ) ) {
					AttachmentCleanupLog::logCleanedAttachment( $attachment, $storagePath );
					$deleted++;
					$totalSize += $attachment->size;
				} else {
					$errors[] = __( 'Attachment :id: failed to delete file.', [ 'id' => $attachment->id ] );
				}
			} catch ( \Exception $e ) {
				$errors[] = __( 'Attachment :id: :message', [ 'id' => $attachment->id, 'message' => $e->getMessage() ] );
			}
		}

		return [
			'action'               => 'delete',
			'criteria'             => $criteria,
			'count'                => $deleted,
			'total_size'           => $totalSize,
			'formatted_total_size' => $this->formatBytes( $totalSize ),
			'items'                => [],
			'errors'               => $errors,
		];
	}

	public function formatBytes( $bytes ): string {
		if ( $bytes >= 1073741824 ) {
			return number_format( $bytes / 1073741824, 2 ) . ' GB';
		}

		if ( $bytes >= 1048576 ) {
			return number_format( $bytes / 1048576, 2 ) . ' MB';
		}

		if ( $bytes >= 1024 ) {
			return number_format( $bytes / 1024, 2 ) . ' KB';
		}

		return $bytes . ' bytes';
	}

	private function query( array $criteria ) {
		$cutoffDate   = Carbon::now()->subDays( $criteria['min_age_days'] );
		$minSizeBytes = $criteria['min_size_kb'] * 1024;
		$maxSizeBytes = ! empty( $criteria['max_size_mb'] ) ? $criteria['max_size_mb'] * 1024 * 1024 : null;

		$query = Attachment::where( 'created_at', '<', $cutoffDate )
		                   ->where( 'size', '>=', $minSizeBytes );

		if ( $maxSizeBytes ) {
			$query->where( 'size', '<=', $maxSizeBytes );
		}

		if ( ! empty( $criteria['mailbox_id'] ) ) {
			$query->whereHas( 'thread.conversation', function ( $q ) use ( $criteria ) {
				$q->where( 'mailbox_id', $criteria['mailbox_id'] );
			} );
		}

		$query->whereNotIn( 'id', function ( $q ) {
			$q->select( 'attachment_id' )
			  ->from( 'cleanup_attachment_logs' );
		} );

		return $query->with( [ 'thread' => function ( $q ) {
			$q->select( 'id', 'conversation_id' );
		} ] )
		             ->orderBy( 'created_at', 'asc' )
		             ->limit( $criteria['limit'] );
	}

	private function attachmentRow( $attachment ): array {
		$createdAt = $attachment->created_at;
		if ( $createdAt && method_exists( $createdAt, 'format' ) ) {
			$createdAt = $createdAt->format( 'Y-m-d H:i' );
		}

		return [
			'id'              => $attachment->id,
			'file_name'       => $attachment->file_name,
			'mime_type'       => $attachment->mime_type,
			'size'            => $attachment->size,
			'formatted_size'  => $this->formatBytes( $attachment->size ),
			'conversation_id' => $attachment->thread ? $attachment->thread->conversation_id : null,
			'created_at'      => (string) $createdAt,
		];
	}
}
