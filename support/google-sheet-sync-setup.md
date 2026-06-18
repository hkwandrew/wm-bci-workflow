# Google Sheet Sync Setup Guide

This guide walks through the one-time setup needed to connect the Waters Meet BCI workflow plugin in WordPress to a Google Sheet.

After setup:

- Approved BCI submissions will sync automatically to the Google Sheet.
- The WordPress dashboard widget will show synced, pending, and failed sync counts.
- A manual retry button will be available in WordPress if a sync fails.
- The CSV export will remain available as a fallback.

## What You Need

- Access to the target Google account that owns the spreadsheet.
- Edit access to the target Google Sheet.
- Access to Google Apps Script.
- Admin access to the WordPress site.
- Optional access to `wp-config.php` if you want to store the sync URL and shared secret as constants instead of saving them in WordPress.

## Step 1: Prepare the Google Sheet

1. Open the Google Sheet that should receive approved BCI opportunities.
2. Decide which tab should receive the synced rows.
3. If the tab is brand new and empty, you can leave it blank. The sync script can write the header row automatically.
4. If the tab already has headers, the first 15 columns must match the current WordPress export exactly and stay in the same order.
5. Extra columns to the right are fine, but do not insert extra columns inside the synced column set.

The expected columns are:

1. `Timestamp`
2. `What kind of opportunity is this?`
3. `Your name:`
4. `What is the title of your community opportunity?`
5. `What is the name of your organization?`
6. `When is your opportunity happening? We need this to have this with at least a week's notice. The newsletter goes out on Thursdays, so anything happening before Thursday of the current week should not be included.`
7. `If your opportunity has a date range, what is the end date?`
8. `For events and learning opportunities, what time of day is it happening?`
9. `For opportunities with a physical location, what is the address?`
10. `Is there any cost?`
11. `Provide a short description of this opportunity`
12. `Provide a link for additional information:`
13. `Please upload any relevant files here:`
14. `Has this been in a newsletter?`
15. `Additional Info , Instructions, and Commentary`

If you want the safest starting point, download one sample CSV from WordPress and use its header row as the Google Sheet header row.

## Step 2: Create the Google Apps Script Web App

1. In Google Drive, open the target spreadsheet.
2. Go to `Extensions > Apps Script`.
3. Delete any starter code in the editor.
4. Open this file from the plugin:

   `wp-content/plugins/wm-bci-workflow/support/google-sheet-sync.gs`

5. Copy the full file contents into the Apps Script editor.
6. Save the project with a clear name such as `Waters Meet BCI Sync`.

## Step 3: Add the Script Properties

In the Apps Script project:

1. Open `Project Settings`.
2. Find `Script Properties`.
3. Add these three properties:

   - `BCI_SPREADSHEET_ID`
   - `BCI_SHEET_NAME`
   - `BCI_SHARED_SECRET`

Use these values:

- `BCI_SPREADSHEET_ID`
  - The long ID from the spreadsheet URL.
  - Example spreadsheet URL:
    `https://docs.google.com/spreadsheets/d/SPREADSHEET_ID/edit`
- `BCI_SHEET_NAME`
  - The exact tab name that should receive synced rows.
- `BCI_SHARED_SECRET`
  - A long random secret string used to verify that requests are really coming from WordPress.

Use a strong shared secret. A password manager or secure generator is fine. Aim for at least 32 random characters.

## Step 4: Deploy the Script as a Web App

1. In Apps Script, click `Deploy > New deployment`.
2. Choose type `Web app`.
3. Set:

   - `Execute as`: `Me`
   - `Who has access`: `Anyone`

4. Deploy the web app.
5. Authorize the script when Google prompts you.
6. Copy the deployed web app URL.

Important:

- The WordPress site will post to this URL from the server, not from a logged-in Google session.
- The deployment needs to allow incoming requests from outside Google login flows.
- Security is enforced by the shared secret, so keep that secret private.

If you later edit the Apps Script code, deploy a new version and update the WordPress URL if Google gives you a different deployment URL.

## Step 5: Configure WordPress

The plugin reads its Google sync settings from `Settings > BCI Workflow > Google Sheets Sync`.

Add the deployed Apps Script URL and shared secret there.

If you prefer to keep those values in environment configuration, the plugin can also fall back to constants in `wp-config.php`.

### Optional constant fallback

Add these constants to the site’s `wp-config.php`:

```php
define( 'WATERS_MEET_BCI_GOOGLE_SYNC_URL', 'PASTE_THE_APPS_SCRIPT_WEB_APP_URL_HERE' );
define( 'WATERS_MEET_BCI_GOOGLE_SYNC_SECRET', 'PASTE_THE_SAME_SHARED_SECRET_HERE' );
```

Use the exact same secret value you added to the Apps Script project as `BCI_SHARED_SECRET`.

Place these constants with the other custom site configuration values, above the line that says:

```php
/* That's all, stop editing! Happy publishing. */
```

## Step 6: Test the Connection

Once both sides are configured:

1. Submit a test BCI opportunity in WordPress.
2. Approve it through the normal review workflow.
3. Open the WordPress dashboard widget labeled `BCI Newsletter Export`.
4. Confirm the counts update as expected.
5. Open the Google Sheet and confirm a new row was added.

Expected behavior:

- New approved entries sync once.
- If the same item is processed again later, it should not create a duplicate row.
- The Apps Script creates a hidden tab named `_wm_bci_sync_log` to keep track of which entry IDs have already synced.

## Step 7: Use the WordPress Dashboard Widget

After setup, the dashboard widget shows:

- `Approved and synced`
- `Approved awaiting sync`
- `Sync failed`
- `Pending review`

Available actions:

- `Retry Google Sheet Sync`
  - Re-attempts approved items that have not synced successfully yet.
- `Download Approved Opportunities CSV`
  - Fallback export if you need a manual backup.
- `Open form entries`
  - Opens the Gravity Forms entries screen for the BCI form.

## Troubleshooting

### Nothing is syncing

Check:

- The sync URL and shared secret are saved in `Settings > BCI Workflow`.
- If you are not using the settings page, confirm the `WATERS_MEET_BCI_GOOGLE_SYNC_URL` constant is present.
- If you are not using the settings page, confirm the `WATERS_MEET_BCI_GOOGLE_SYNC_SECRET` constant is present.
- The Apps Script deployment is still active.
- The Apps Script web app URL is the deployed URL, not the editor URL.
- The WordPress secret and Apps Script secret are exactly the same.

### The widget shows failed syncs

1. Open the relevant Gravity Forms entry.
2. Check the entry notes.
3. Look for notes labeled `WM BCI Google Sync`.
4. Use the failure message there to identify the issue.

Common causes:

- Wrong script URL
- Shared secret mismatch
- Sheet tab name mismatch
- Header row mismatch in the target tab
- Google deployment changed but the WordPress URL was not updated

### The Google Sheet headers do not match

The sync will fail if the existing header row in the target tab differs from the WordPress export header row across the synced columns.

Fix:

1. Compare the Google Sheet header row to the list in this guide.
2. Correct the first 15 tab headers so they match exactly and stay in the same order.
3. Leave any extra columns to the right only.
4. Retry the sync from WordPress.

### Rows are not appearing, but there are no obvious errors

Check the hidden `_wm_bci_sync_log` tab in the spreadsheet.

- If the entry ID is already listed there, the item was treated as already synced.
- If the WordPress entry is already marked as synced, the retry button will skip it even if you remove the log row from the sheet.
- If you truly need to append the same entry again, you must coordinate both the sheet log state and the WordPress sync status before retrying.

Do this carefully. Removing the sheet log row alone does not force WordPress to resend an entry that is already marked as synced.

## Ongoing Maintenance

- If the target spreadsheet changes owners, confirm the Apps Script deployment still has access.
- If you rename the destination tab, update `BCI_SHEET_NAME`.
- If you rotate the shared secret, update it in Apps Script and in whichever WordPress configuration source you use: the plugin settings or `wp-config.php`.
- If you change the Apps Script code, redeploy the web app.

## File References

Plugin runtime:

- `wp-content/plugins/wm-bci-workflow/src/Plugin.php`

Google Apps Script endpoint:

- `wp-content/plugins/wm-bci-workflow/support/google-sheet-sync.gs`

This setup guide:

- `wp-content/plugins/wm-bci-workflow/support/google-sheet-sync-setup.md`
