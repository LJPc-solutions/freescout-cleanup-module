@extends('layouts.app')

@section('title', __('Cleanup'))

@section('content')
	@php
		$conversationResult = session('cleanup_conversations');
		$attachmentResult = session('cleanup_attachments');
		$oldMailboxIds = old('mailbox_ids', []);
		if (!is_array($oldMailboxIds)) {
			$oldMailboxIds = preg_split('/[,\s]+/', $oldMailboxIds);
		}
		$oldStatuses = old('statuses', []);
		if (!is_array($oldStatuses)) {
			$oldStatuses = preg_split('/[,\s]+/', $oldStatuses);
		}
	@endphp

	<div class="container">
		@if (session('cleanup_error'))
			<div class="alert alert-danger">
				{{ session('cleanup_error') }}
			</div>
		@endif

		<div class="row">
			<div class="col-md-6">
				<div class="panel panel-default">
					<div class="panel-heading">
						<h3 class="panel-title">{{ __('Conversation cleanup') }}</h3>
					</div>
					<div class="panel-body">
						<form method="POST" action="{{ route('cleanup.conversations.preview') }}">
							{{ csrf_field() }}

							@if (count($mailboxes))
								<div class="form-group">
									<label for="mailbox_ids">{{ __('Mailboxes') }}</label>
									<select id="mailbox_ids" name="mailbox_ids[]" multiple class="form-control">
										@foreach ($mailboxes as $mailbox)
											<option value="{{ $mailbox->id }}" {{ in_array($mailbox->id, $oldMailboxIds) ? 'selected' : '' }}>
												#{{ $mailbox->id }} {{ $mailbox->name }}
											</option>
										@endforeach
									</select>
								</div>
							@else
								<div class="form-group">
									<label for="mailbox_ids_text">{{ __('Mailbox IDs') }}</label>
									<input id="mailbox_ids_text" name="mailbox_ids_text" type="text" class="form-control" value="{{ old('mailbox_ids_text') }}" placeholder="1, 2">
								</div>
							@endif

							<div class="form-group">
								<label>{{ __('Statuses') }}</label>
								<div>
									@foreach ($statuses as $statusId => $statusName)
										<label class="checkbox-inline">
											<input type="checkbox" name="statuses[]" value="{{ $statusId }}" {{ in_array($statusId, $oldStatuses) ? 'checked' : '' }}>
											{{ $statusName }}
										</label>
									@endforeach
								</div>
							</div>

							<div class="form-group">
								<label for="older_than_days">{{ __('Older than days') }}</label>
								<input id="older_than_days" name="older_than_days" type="number" min="1" class="form-control" value="{{ old('older_than_days') }}">
							</div>

							<div class="form-group">
								<label for="subject_starts_with">{{ __('Subject starts with') }}</label>
								<input id="subject_starts_with" name="subject_starts_with" type="text" class="form-control" value="{{ old('subject_starts_with') }}">
							</div>

							<div class="form-group">
								<label for="subject_contains">{{ __('Subject contains') }}</label>
								<input id="subject_contains" name="subject_contains" type="text" class="form-control" value="{{ old('subject_contains') }}">
							</div>

							<div class="form-group">
								<label for="subject_ends_with">{{ __('Subject ends with') }}</label>
								<input id="subject_ends_with" name="subject_ends_with" type="text" class="form-control" value="{{ old('subject_ends_with') }}">
							</div>

							<div class="form-group">
								<label for="limit">{{ __('Limit') }}</label>
								<input id="limit" name="limit" type="number" min="1" max="5000" class="form-control" value="{{ old('limit', 100) }}">
							</div>

							<button type="submit" class="btn btn-primary">{{ __('Preview conversations') }}</button>
						</form>
					</div>
				</div>
			</div>

			<div class="col-md-6">
				<div class="panel panel-default">
					<div class="panel-heading">
						<h3 class="panel-title">{{ __('Attachment cleanup') }}</h3>
					</div>
					<div class="panel-body">
						<form method="POST" action="{{ route('cleanup.attachments.preview') }}">
							{{ csrf_field() }}

							<div class="form-group">
								<label for="attachment_mailbox_id">{{ __('Mailbox ID') }}</label>
								<input id="attachment_mailbox_id" name="mailbox_id" type="number" min="1" class="form-control" value="{{ old('mailbox_id') }}">
							</div>

							<div class="form-group">
								<label for="min_age_days">{{ __('Minimum age in days') }}</label>
								<input id="min_age_days" name="min_age_days" type="number" min="1" class="form-control" value="{{ old('min_age_days', 730) }}">
							</div>

							<div class="form-group">
								<label for="min_size_kb">{{ __('Minimum size in KB') }}</label>
								<input id="min_size_kb" name="min_size_kb" type="number" min="1" class="form-control" value="{{ old('min_size_kb', 300) }}">
							</div>

							<div class="form-group">
								<label for="max_size_mb">{{ __('Maximum size in MB') }}</label>
								<input id="max_size_mb" name="max_size_mb" type="number" min="1" class="form-control" value="{{ old('max_size_mb') }}">
							</div>

							<div class="form-group">
								<label for="attachment_limit">{{ __('Limit') }}</label>
								<input id="attachment_limit" name="attachment_limit" type="number" min="1" max="5000" class="form-control" value="{{ old('attachment_limit', 1000) }}">
							</div>

							<button type="submit" class="btn btn-primary">{{ __('Preview attachments') }}</button>
						</form>
					</div>
				</div>
			</div>
		</div>

		@if ($conversationResult)
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title">
						@if ($conversationResult['action'] === 'delete')
							{{ __('Deleted :count conversations', ['count' => $conversationResult['count']]) }}
						@else
							{{ __('Conversation preview: :count matches', ['count' => $conversationResult['count']]) }}
						@endif
					</h3>
				</div>
				<div class="panel-body">
					@if (!empty($conversationResult['items']))
						<div class="table-responsive">
							<table class="table table-striped">
								<thead>
									<tr>
										<th>{{ __('ID') }}</th>
										<th>{{ __('Number') }}</th>
										<th>{{ __('Mailbox') }}</th>
										<th>{{ __('Status') }}</th>
										<th>{{ __('Subject') }}</th>
										<th>{{ __('Created') }}</th>
									</tr>
								</thead>
								<tbody>
									@foreach ($conversationResult['items'] as $conversation)
										<tr>
											<td>{{ $conversation['id'] }}</td>
											<td>{{ $conversation['number'] ?? '' }}</td>
											<td>{{ $conversation['mailbox_id'] ?? '' }}</td>
											<td>{{ $conversation['status_name'] ?? '' }}</td>
											<td>{{ $conversation['subject'] ?? '' }}</td>
											<td>{{ $conversation['created_at'] ?? '' }}</td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					@endif

					@if ($conversationResult['action'] === 'preview' && $conversationResult['count'] > 0)
						<form method="POST" action="{{ route('cleanup.conversations.delete') }}">
							{{ csrf_field() }}
							@foreach ($conversationResult['criteria']['mailbox_ids'] as $mailboxId)
								<input type="hidden" name="mailbox_ids[]" value="{{ $mailboxId }}">
							@endforeach
							@foreach ($conversationResult['criteria']['statuses'] as $status)
								<input type="hidden" name="statuses[]" value="{{ $status }}">
							@endforeach
							<input type="hidden" name="older_than_days" value="{{ $conversationResult['criteria']['older_than_days'] }}">
							<input type="hidden" name="subject_starts_with" value="{{ $conversationResult['criteria']['subject_starts_with'] }}">
							<input type="hidden" name="subject_contains" value="{{ $conversationResult['criteria']['subject_contains'] }}">
							<input type="hidden" name="subject_ends_with" value="{{ $conversationResult['criteria']['subject_ends_with'] }}">
							<input type="hidden" name="limit" value="{{ $conversationResult['criteria']['limit'] }}">
							<div class="form-group">
								<label for="confirm_conversation_delete">{{ __('Type DELETE to confirm') }}</label>
								<input id="confirm_conversation_delete" name="confirm_delete" type="text" class="form-control">
							</div>
							<button type="submit" class="btn btn-danger">{{ __('Delete matching conversations') }}</button>
						</form>
					@endif
				</div>
			</div>
		@endif

		@if ($attachmentResult)
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title">
						@if ($attachmentResult['action'] === 'delete')
							{{ __('Deleted :count attachments', ['count' => $attachmentResult['count']]) }}
						@else
							{{ __('Attachment preview: :count matches', ['count' => $attachmentResult['count']]) }}
						@endif
						@if (!empty($attachmentResult['total_size']))
							<span class="text-muted">({{ $attachmentResult['formatted_total_size'] }})</span>
						@endif
					</h3>
				</div>
				<div class="panel-body">
					@if (!empty($attachmentResult['errors']))
						<div class="alert alert-warning">
							<ul>
								@foreach ($attachmentResult['errors'] as $error)
									<li>{{ $error }}</li>
								@endforeach
							</ul>
						</div>
					@endif

					@if (!empty($attachmentResult['items']))
						<div class="table-responsive">
							<table class="table table-striped">
								<thead>
									<tr>
										<th>{{ __('ID') }}</th>
										<th>{{ __('File') }}</th>
										<th>{{ __('Conversation') }}</th>
										<th>{{ __('Size') }}</th>
										<th>{{ __('Created') }}</th>
									</tr>
								</thead>
								<tbody>
									@foreach ($attachmentResult['items'] as $attachment)
										<tr>
											<td>{{ $attachment['id'] }}</td>
											<td>{{ $attachment['file_name'] }}</td>
											<td>{{ $attachment['conversation_id'] }}</td>
											<td>{{ $attachment['formatted_size'] }}</td>
											<td>{{ $attachment['created_at'] }}</td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					@endif

					@if ($attachmentResult['action'] === 'preview' && $attachmentResult['count'] > 0)
						<form method="POST" action="{{ route('cleanup.attachments.delete') }}">
							{{ csrf_field() }}
							<input type="hidden" name="mailbox_id" value="{{ $attachmentResult['criteria']['mailbox_id'] }}">
							<input type="hidden" name="min_age_days" value="{{ $attachmentResult['criteria']['min_age_days'] }}">
							<input type="hidden" name="min_size_kb" value="{{ $attachmentResult['criteria']['min_size_kb'] }}">
							<input type="hidden" name="max_size_mb" value="{{ $attachmentResult['criteria']['max_size_mb'] }}">
							<input type="hidden" name="attachment_limit" value="{{ $attachmentResult['criteria']['limit'] }}">
							<div class="form-group">
								<label for="confirm_attachment_delete">{{ __('Type DELETE to confirm') }}</label>
								<input id="confirm_attachment_delete" name="confirm_delete" type="text" class="form-control">
							</div>
							<button type="submit" class="btn btn-danger">{{ __('Delete matching attachments') }}</button>
						</form>
					@endif
				</div>
			</div>
		@endif
	</div>
@stop
