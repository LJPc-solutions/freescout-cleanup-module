<?php

namespace Modules\Cleanup\Http\Controllers;

use App\Mailbox;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Cleanup\Services\AttachmentCleanupService;
use Modules\Cleanup\Services\ConversationCleanupService;

class CleanupController extends Controller {
	private $attachments;
	private $conversations;

	public function __construct() {
		$this->attachments   = new AttachmentCleanupService();
		$this->conversations = new ConversationCleanupService();
	}

	public function index() {
		return view( 'cleanup::index', [
			'mailboxes' => $this->mailboxes(),
			'statuses'  => $this->conversations->statusLabels(),
		] );
	}

	public function previewConversations( Request $request ) {
		$criteria = $this->conversationCriteria( $request );

		if ( ! $this->conversations->hasFilters( $criteria ) ) {
			return redirect()->route( 'cleanup.index' )
			                 ->withInput()
			                 ->with( 'cleanup_error', __( 'Choose at least one conversation filter before previewing cleanup.' ) );
		}

		return redirect()->route( 'cleanup.index' )
		                 ->withInput()
		                 ->with( 'cleanup_conversations', $this->conversations->preview( $criteria ) );
	}

	public function deleteConversations( Request $request ) {
		$criteria = $this->conversationCriteria( $request );

		if ( ! $this->conversations->hasFilters( $criteria ) ) {
			return redirect()->route( 'cleanup.index' )
			                 ->withInput()
			                 ->with( 'cleanup_error', __( 'Choose at least one conversation filter before deleting cleanup matches.' ) );
		}

		if ( $request->input( 'confirm_delete' ) !== 'DELETE' ) {
			return redirect()->route( 'cleanup.index' )
			                 ->withInput()
			                 ->with( 'cleanup_error', __( 'Type DELETE to confirm conversation deletion.' ) );
		}

		return redirect()->route( 'cleanup.index' )
		                 ->withInput()
		                 ->with( 'cleanup_conversations', $this->conversations->delete( $criteria ) );
	}

	public function previewAttachments( Request $request ) {
		$criteria = $this->attachmentCriteria( $request );

		return redirect()->route( 'cleanup.index' )
		                 ->withInput()
		                 ->with( 'cleanup_attachments', $this->attachments->preview( $criteria ) );
	}

	public function deleteAttachments( Request $request ) {
		$criteria = $this->attachmentCriteria( $request );

		if ( $request->input( 'confirm_delete' ) !== 'DELETE' ) {
			return redirect()->route( 'cleanup.index' )
			                 ->withInput()
			                 ->with( 'cleanup_error', __( 'Type DELETE to confirm attachment cleanup.' ) );
		}

		return redirect()->route( 'cleanup.index' )
		                 ->withInput()
		                 ->with( 'cleanup_attachments', $this->attachments->delete( $criteria ) );
	}

	private function mailboxes() {
		try {
			return Mailbox::orderBy( 'name' )->get();
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	private function conversationCriteria( Request $request ): array {
		$statuses = array_values( array_intersect( $this->integerList( $request->input( 'statuses', [] ) ), [ 1, 2, 3, 4 ] ) );

		return [
			'mailbox_ids'         => $this->integerList( $request->input( 'mailbox_ids', $request->input( 'mailbox_ids_text', [] ) ) ),
			'statuses'            => $statuses,
			'older_than_days'     => $this->optionalPositiveInteger( $request->input( 'older_than_days' ) ),
			'subject_starts_with' => $this->cleanString( $request->input( 'subject_starts_with' ) ),
			'subject_contains'    => $this->cleanString( $request->input( 'subject_contains' ) ),
			'subject_ends_with'   => $this->cleanString( $request->input( 'subject_ends_with' ) ),
			'limit'               => $this->positiveInteger( $request->input( 'limit' ), 100, 5000 ),
		];
	}

	private function attachmentCriteria( Request $request ): array {
		return [
			'min_age_days' => $this->positiveInteger( $request->input( 'min_age_days' ), 730, 36500 ),
			'min_size_kb'  => $this->positiveInteger( $request->input( 'min_size_kb' ), 300, 1048576 ),
			'max_size_mb'  => $this->optionalPositiveInteger( $request->input( 'max_size_mb' ) ),
			'mailbox_id'   => $this->optionalPositiveInteger( $request->input( 'mailbox_id' ) ),
			'limit'        => $this->positiveInteger( $request->input( 'attachment_limit' ), 1000, 5000 ),
		];
	}

	private function integerList( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[,\s]+/', $value );
		}

		$items = [];
		foreach ( (array) $value as $item ) {
			$item = trim( (string) $item );
			if ( $item === '' ) {
				continue;
			}

			$integer = filter_var( $item, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
			if ( $integer !== false ) {
				$items[] = (int) $integer;
			}
		}

		return array_values( array_unique( $items ) );
	}

	private function optionalPositiveInteger( $value ) {
		$value = filter_var( $value, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );

		return $value === false ? null : (int) $value;
	}

	private function positiveInteger( $value, int $default, int $maximum ): int {
		$value = $this->optionalPositiveInteger( $value );
		if ( $value === null ) {
			return $default;
		}

		return min( $value, $maximum );
	}

	private function cleanString( $value ) {
		$value = trim( (string) $value );

		return $value === '' ? null : substr( $value, 0, 255 );
	}
}
