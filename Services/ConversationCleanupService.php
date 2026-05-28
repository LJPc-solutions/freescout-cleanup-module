<?php

namespace Modules\Cleanup\Services;

use App\Conversation;

class ConversationCleanupService {
	public function statusLabels(): array {
		return [
			1 => __( 'Active' ),
			2 => __( 'Pending' ),
			3 => __( 'Closed' ),
			4 => __( 'Spam' ),
		];
	}

	public function hasFilters( array $criteria ): bool {
		return ! empty( $criteria['mailbox_ids'] )
		       || ! empty( $criteria['statuses'] )
		       || ! empty( $criteria['older_than_days'] )
		       || ! empty( $criteria['subject_starts_with'] )
		       || ! empty( $criteria['subject_contains'] )
		       || ! empty( $criteria['subject_ends_with'] );
	}

	public function preview( array $criteria ): array {
		$conversations = $this->query( $criteria )->get();

		return [
			'action'   => 'preview',
			'criteria' => $criteria,
			'count'    => $conversations->count(),
			'items'    => $conversations->map( function ( $conversation ) {
				return $this->conversationRow( $conversation );
			} )->all(),
		];
	}

	public function delete( array $criteria ): array {
		$conversations   = $this->query( $criteria )->get();
		$conversationIds = $conversations->pluck( 'id' )->toArray();

		if ( ! empty( $conversationIds ) ) {
			Conversation::deleteConversationsForever( $conversationIds );
		}

		return [
			'action'   => 'delete',
			'criteria' => $criteria,
			'count'    => count( $conversationIds ),
			'items'    => $conversations->map( function ( $conversation ) {
				return $this->conversationRow( $conversation );
			} )->all(),
		];
	}

	private function query( array $criteria ) {
		$query = Conversation::query()
		                     ->select( [ 'id', 'number', 'mailbox_id', 'status', 'subject', 'created_at' ] )
		                     ->orderBy( 'id', 'asc' );

		if ( ! empty( $criteria['mailbox_ids'] ) ) {
			$query->whereIn( 'mailbox_id', $criteria['mailbox_ids'] );
		}

		if ( ! empty( $criteria['statuses'] ) ) {
			$query->whereIn( 'status', $criteria['statuses'] );
		}

		if ( ! empty( $criteria['older_than_days'] ) ) {
			$query->where( 'created_at', '<=', now()->subDays( $criteria['older_than_days'] ) );
		}

		if ( ! empty( $criteria['subject_starts_with'] ) ) {
			$query->where( 'subject', 'like', $criteria['subject_starts_with'] . '%' );
		}

		if ( ! empty( $criteria['subject_contains'] ) ) {
			$query->where( 'subject', 'like', '%' . $criteria['subject_contains'] . '%' );
		}

		if ( ! empty( $criteria['subject_ends_with'] ) ) {
			$query->where( 'subject', 'like', '%' . $criteria['subject_ends_with'] );
		}

		if ( ! empty( $criteria['limit'] ) ) {
			$query->limit( $criteria['limit'] );
		}

		return $query;
	}

	private function conversationRow( $conversation ): array {
		$createdAt = $conversation->created_at;
		if ( $createdAt && method_exists( $createdAt, 'format' ) ) {
			$createdAt = $createdAt->format( 'Y-m-d H:i' );
		}

		return [
			'id'          => $conversation->id,
			'number'      => $conversation->number,
			'mailbox_id'  => $conversation->mailbox_id,
			'status'      => $conversation->status,
			'status_name' => $this->statusLabels()[$conversation->status] ?? $conversation->status,
			'subject'     => $conversation->subject,
			'created_at'  => (string) $createdAt,
		];
	}
}
