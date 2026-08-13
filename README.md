# Cleanup module for FreeScout

This module adds a custom Artisan command to clean up conversations in FreeScout based on various criteria.

<a href="https://www.buymeacoffee.com/Lars-" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/v2/default-orange.png" alt="Buy Me A Coffee" height="60" style="height: 60px !important;width: 217px !important;" ></a>

## Features

### Conversation Cleanup
- Clean up conversations based on mailbox IDs, statuses, age, and subject patterns.
- Perform a dry run to preview the conversations that would be deleted without actually deleting them.
- Confirm deletion of conversations before proceeding.

### Attachment Cleanup
- Clean up old and large attachments to save storage space.
- Delete attachments based on age and size criteria.
- Detailed logging of cleaned attachments.
- Remove dangling database records of attachments whose file no longer exists on disk.
- Dry-run mode to preview deletions.

## Installation

1. Download the latest module zip file [here](https://resources.ljpc.network/freescout-modules/cleanup/latest.zip), or download the `Cleanup-x.y.z.zip` asset from the [GitHub releases page](https://github.com/LJPc-solutions/freescout-cleanup-module/releases). **Do not use the master branch or the auto-generated "Source code" archives!** Those unpack to a folder named `freescout-cleanup-module-x.y.z`, which will break your FreeScout instance.
2. Transfer the zip file to the server in the Modules folder of FreeScout.
3. Unpack the zip file. The module folder must be named exactly `Cleanup`. If the unpacked folder has any other name, rename it to `Cleanup` before proceeding.
4. Remove the zip file.
5. Activate the module via the Modules page in FreeScout.

## Usage

### Conversation Cleanup

To use the conversation cleanup command, run:

```bash
php artisan cleanup:conversations [options]
```

> [!NOTE]
> The cleanup command will not immediately delete conversations. It will prompt you to confirm the deletion of conversations before proceeding. If the `--y` option is provided, the command will not prompt for confirmation and will delete
> conversations immediately.

Available options:

- `--mailbox-id`: The IDs of the mailboxes to clean up conversations for (can be used multiple times).
- `--status`: The statuses of conversations to clean up (1=active, 2=pending, 3=closed, 4=spam, can be used multiple times).
- `--older-than-days`: Clean up conversations older than the specified number of days.
- `--subject-starts-with`: Clean up conversations with subjects starting with the specified string.
- `--subject-contains`: Clean up conversations with subjects containing the specified string.
- `--subject-ends-with`: Clean up conversations with subjects ending with the specified string.
- `--limit`: The maximum number of conversations to delete.
- `--dry-run`: Perform a dry run without actually deleting conversations.
- `--y`: Confirm deletion of conversations.

Examples:

- Clean up conversations older than 30 days:<br />
  `php artisan cleanup:conversations --older-than-days=30`
- Clean up closed conversations with subjects starting with "[RESOLVED]":<br />
  `php artisan cleanup:conversations --status=3 --subject-starts-with="[RESOLVED]"`
- Perform a dry run to preview conversations that would be deleted:<br />
  `php artisan cleanup:conversations --mailbox-id=1 --mailbox-id=2 --older-than-days=90 --dry-run`
- Remove all conversations that are older than 60 days and have the status spam or closed:<br />
  `php artisan cleanup:conversations --older-than-days=60 --status=3 --status=4`
- Remove all conversations that are older than 60 days and have the status spam or closed, but only if the subject starts with "SPAM":<br />
  `php artisan cleanup:conversations --older-than-days=60 --status=3 --status=4 --subject-starts-with="SPAM"`
- Remove all conversations that are older than 60 days and have the status spam or closed and limit the amount of conversations to delete to 10:<br />
  `php artisan cleanup:conversations --older-than-days=60 --status=3 --status=4 --limit=10`

### Attachment Cleanup

```bash
php artisan freescout:cleanup-attachments [options]
```

Available options:

- `--dry-run`: Preview what would be deleted without actually deleting.
- `--min-age-days`: Minimum age in days (default: 730 = 2 years).
- `--min-size-kb`: Minimum size in KB (default: 300).
- `--max-size-mb`: Maximum size in MB (optional).
- `--mailbox`: Specific mailbox ID to clean (optional).
- `--limit`: Maximum number of attachments to process in one run (default: 1000).
- `--dangling`: Delete database records of attachments whose file no longer exists on disk. This is a separate mode: the age and size filters are ignored, `--mailbox`, `--limit` and `--dry-run` still apply. Use this to resync the database after attachment files were removed outside of FreeScout, or to clean up the records left behind by earlier attachment cleanups.

Examples:

- Preview deletions (dry run):<br />
  `php artisan freescout:cleanup-attachments --dry-run`
- Delete attachments older than 1 year and larger than 500KB:<br />
  `php artisan freescout:cleanup-attachments --min-age-days=365 --min-size-kb=500`
- Clean only large files (1MB to 10MB) older than 6 months:<br />
  `php artisan freescout:cleanup-attachments --min-age-days=180 --min-size-kb=1024 --max-size-mb=10`
- Clean attachments from specific mailbox:<br />
  `php artisan freescout:cleanup-attachments --mailbox=1 --min-age-days=730`
- Preview which dangling database records would be removed:<br />
  `php artisan freescout:cleanup-attachments --dangling --dry-run`
- Remove dangling database records for files that no longer exist:<br />
  `php artisan freescout:cleanup-attachments --dangling`

> [!IMPORTANT]
> - Deleted attachments will return 404 errors when accessed.
> - Links to cleaned attachments in emails or shared externally will break.
> - Always test with `--dry-run` first.
> - Consider backing up attachments before bulk deletion.

## The future of this module

Feel free to add your own features by sending a pull request.

## Custom software

Interested in a custom FreeScout module or anything else? Please let us know via info@ljpc.nl or www.ljpc.solutions.

## Donations

This module took us time to develop, but we decided to make it open source anyway. If we helped you or your business, please consider donating. Click here to donate.

