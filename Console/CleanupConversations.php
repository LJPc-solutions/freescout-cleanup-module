<?php

namespace Modules\Cleanup\Console;

use App\Conversation;
use App\Thread;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\InputOption;

class CleanupConversations extends Command {
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cleanup:conversations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleans up conversations based on supplied arguments.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions() {
        return [
            [ 'mailbox-id', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The IDs of the mailboxes to clean up conversations for (comma-separated).' ],
            [ 'status', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The statuses of conversations to clean up (1=active, 2=pending, 3=closed, 4=spam, comma-separated).' ],
            [ 'older-than-days', null, InputOption::VALUE_OPTIONAL, 'Clean up conversations older than the specified number of days.' ],
            [ 'subject-starts-with', null, InputOption::VALUE_OPTIONAL, 'Clean up conversations with subjects starting with the specified string.' ],
            [ 'subject-contains', null, InputOption::VALUE_OPTIONAL, 'Clean up conversations with subjects containing the specified string.' ],
            [ 'subject-ends-with', null, InputOption::VALUE_OPTIONAL, 'Clean up conversations with subjects ending with the specified string.' ],
            [ 'limit', null, InputOption::VALUE_OPTIONAL, 'Limit the number of conversations to delete.' ],
            [ 'notes', null, InputOption::VALUE_NONE, 'Delete only the notes (threads with type 3) of the matching conversations, never the opening thread of a conversation. The conversations themselves are kept, --older-than-days and --limit apply to the notes.' ],
            [ 'by-user-id', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Only delete notes created by these user IDs. Requires --notes.' ],
            [ 'dry-run', null, InputOption::VALUE_NONE, 'Perform a dry run without actually deleting conversations.' ],
            [ 'y', null, InputOption::VALUE_NONE, 'Confirm deletion of conversations.' ],
        ];
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        $mailboxIds        = $this->option( 'mailbox-id' );
        $statuses          = $this->option( 'status' );
        $olderThanDays     = $this->option( 'older-than-days' );
        $subjectStartsWith = $this->option( 'subject-starts-with' );
        $subjectContains   = $this->option( 'subject-contains' );
        $subjectEndsWith   = $this->option( 'subject-ends-with' );
        $limit             = $this->option( 'limit' );
        $notes             = $this->option( 'notes' );
        $byUserIds         = $this->option( 'by-user-id' );
        $dryRun            = $this->option( 'dry-run' );
        $confirmed         = $this->option( 'y' );

        if ( $byUserIds && ! $notes ) {
            $this->error( 'The --by-user-id option can only be used together with --notes.' );

            return 1;
        }

        foreach ( $byUserIds as $byUserId ) {
            if ( ! ctype_digit( (string) $byUserId ) || (int) $byUserId === 0 ) {
                $this->error( 'Invalid --by-user-id value "' . $byUserId . '", expected a user ID.' );

                return 1;
            }
        }

        $conversations = Conversation::query();

        if ( $mailboxIds ) {
            $conversations->whereIn( 'mailbox_id', $mailboxIds );
        }

        if ( $statuses ) {
            $conversations->whereIn( 'status', $statuses );
        }

        if ( $subjectStartsWith ) {
            $conversations->where( 'subject', 'like', $subjectStartsWith . '%' );
        }

        if ( $subjectContains ) {
            $conversations->where( 'subject', 'like', '%' . $subjectContains . '%' );
        }

        if ( $subjectEndsWith ) {
            $conversations->where( 'subject', 'like', '%' . $subjectEndsWith );
        }

        if ( $notes ) {
            return $this->cleanupNotes( $conversations, $byUserIds, $olderThanDays, $limit, $dryRun, $confirmed );
        }

        if ( $olderThanDays ) {
            $olderThanDate = now()->subDays( $olderThanDays );
            $conversations->where( 'created_at', '<=', $olderThanDate );
        }

        if ( $limit ) {
            $conversations->limit( $limit );
        }

        $conversationIds = $conversations->pluck( 'id' )->toArray();

        if ( ! $conversationIds ) {
            $this->info( 'No conversations found matching the specified criteria.' );

            return true;
        }

        if ( $dryRun ) {
            $this->info( 'Dry run mode enabled. No conversations were deleted.' );
            $this->info( 'The following conversations would have been deleted:' );
            $this->info( implode( ', ', $conversationIds ) );

            return true;
        }

        if ( ! $confirmed ) {
            $this->info( 'The following conversations will be deleted:' );
            $this->info( implode( ', ', $conversationIds ) );
            $confirmed = $this->confirm( 'Do you wish to proceed?' );
        }

        if ( $confirmed ) {
            $this->info( 'Deleting ' . count( $conversationIds ) . ' conversations...' );
            Conversation::deleteConversationsForever( $conversationIds );
            $this->info( 'Conversations deleted successfully.' );
        } else {
            $this->info( 'Deletion of conversations cancelled.' );
        }

        return true;
    }

    /**
     * Delete the notes of the given conversations, keeping the conversations themselves.
     *
     * @return mixed
     */
    private function cleanupNotes( $conversations, $byUserIds, $olderThanDays, $limit, $dryRun, $confirmed ) {
        $notes = Thread::query()
                       ->where( 'type', Thread::TYPE_NOTE )
                       ->whereIn( 'conversation_id', $conversations->select( 'id' ) )
                       // Never delete the opening thread of a conversation: phone conversations start with a note.
                       ->whereExists( function ( $query ) {
                           $query->select( DB::raw( 1 ) )
                                 ->from( 'threads as older_threads' )
                                 ->whereColumn( 'older_threads.conversation_id', 'threads.conversation_id' )
                                 ->whereColumn( 'older_threads.id', '<', 'threads.id' );
                       } );

        if ( $byUserIds ) {
            $notes->whereIn( 'created_by_user_id', $byUserIds );
        }

        if ( $olderThanDays ) {
            $olderThanDate = now()->subDays( $olderThanDays );
            $notes->where( 'created_at', '<=', $olderThanDate );
        }

        if ( $limit ) {
            $notes->orderBy( 'id' )->limit( $limit );
        }

        $noteIds = $notes->pluck( 'id' )->toArray();

        if ( ! $noteIds ) {
            $this->info( 'No notes found matching the specified criteria.' );

            return true;
        }

        if ( $dryRun ) {
            $this->info( 'Dry run mode enabled. No notes were deleted.' );
            $this->info( 'The following notes would have been deleted:' );
            $this->info( implode( ', ', $noteIds ) );

            return true;
        }

        if ( ! $confirmed ) {
            $this->info( 'The following notes will be deleted:' );
            $this->info( implode( ', ', $noteIds ) );
            $confirmed = $this->confirm( 'Do you wish to proceed?' );
        }

        if ( $confirmed ) {
            $this->info( 'Deleting ' . count( $noteIds ) . ' notes...' );
            foreach ( array_chunk( $noteIds, \Helper::IN_LIMIT ) as $noteIdsChunk ) {
                // deleteThread() also removes the attachments and updates the conversation preview.
                foreach ( Thread::whereIn( 'id', $noteIdsChunk )->get() as $note ) {
                    $note->deleteThread();
                }
            }
            $this->info( 'Notes deleted successfully.' );
        } else {
            $this->info( 'Deletion of notes cancelled.' );
        }

        return true;
    }


}
