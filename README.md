# Cleanup module for FreeScout

This module adds a custom Artisan command to clean up conversations in FreeScout based on various criteria.

<a href="https://www.buymeacoffee.com/Lars-" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/v2/default-orange.png" alt="Buy Me A Coffee" height="60" style="height: 60px !important;width: 217px !important;" ></a>

## Features

- Clean up conversations based on mailbox IDs, statuses, age, and subject patterns.
- Perform a dry run to preview the conversations that would be deleted without actually deleting them.
- Confirm deletion of conversations before proceeding.

## Installation

1. Download the latest module zip file [here](https://resources.ljpc.network/freescout-modules/cleanup/latest.zip).
2. Transfer the zip file to the server in the Modules folder of FreeScout.
3. Unpack the zip file.
4. Remove the zip file.
5. Activate the module via the Modules page in FreeScout.

## Usage

To use the cleanup command, run the following Artisan command:

```bash
php artisan cleanup:conversations [options]
```

Available options:

- --mailbox-id: The IDs of the mailboxes to clean up conversations for (can be used multiple times).
- --status: The statuses of conversations to clean up (1=active, 2=pending, 3=closed, 4=spam, can be used multiple times).
- --older-than-days: Clean up conversations older than the specified number of days.
- --subject-starts-with: Clean up conversations with subjects starting with the specified string.
- --subject-contains: Clean up conversations with subjects containing the specified string.
- --subject-ends-with: Clean up conversations with subjects ending with the specified string.
- --dry-run: Perform a dry run without actually deleting conversations.
- --y: Confirm deletion of conversations.

> [!NOTE]
> The cleanup command will not immediately delete conversations. It will prompt you to confirm the deletion of conversations before proceeding. If the --y option is provided, the command will not prompt for confirmation and will delete conversations immediately.


Examples:

- Clean up conversations older than 30 days:<br />
`php artisan cleanup:conversations --older-than-days=30`
- Clean up closed conversations with subjects starting with "[RESOLVED]":<br />
  `php artisan cleanup:conversations --status=3 --subject-starts-with="[RESOLVED]"`
- Perform a dry run to preview conversations that would be deleted:<br />
  `php artisan cleanup:conversations --mailbox-id=1,2 --older-than-days=90 --dry-run`
- Remove all conversations that are older than 60 days and have the status spam or closed:<br />
`php artisan cleanup:conversations --older-than-days=60 --status=3 --status=4`

## The future of this module

Feel free to add your own features by sending a pull request.

## Custom software

Interested in a custom FreeScout module or anything else? Please let us know via info@ljpc.nl or www.ljpc.solutions.

## Donations

This module took us time to develop, but we decided to make it open source anyway. If we helped you or your business, please consider donating. Click here to donate.

