# Melbourne Cup sweepstake

Served by the existing PHP site at `/sweepstake/`. No Node build or new hosting service is required.

## Setup

- `config.php` has independent connection settings matching the existing shared MariaDB database. If database credentials change, update this config separately.
- PHP needs PDO MySQL, mbstring, cURL and DOM, and the database account needs access to the existing `users` table plus the sweepstake tables.
- Open `/sweepstake/`, sign in with an existing admin account, then select **Initialize / update sweepstake**. Alternatively import `schema.sql` into the same database using the existing database administration process.
- Initialization creates `sweepstake_pots`, `sweepstake_runners`, `sweepstake_entries`, `sweepstake_runner_details`, and `sweepstake_field_sync`. Existing installations use the same button to add the two import tables without altering existing pots, entries, or shared tables/users.

## Running a sweep

1. Create any number of pots, with separate prices, race years and prize splits.
2. The field imports automatically when a pot is created. Horse number/name, jockey, trainer, barrier, weight, recent form and scratch status are read from Racing Australia for the selected year. Open existing pots import automatically when an admin views them; the selected pot refreshes every 15 minutes while that admin page remains open. **Refresh Cup field** checks immediately.
3. Add one participant entry per active runner slot. Repeated names are supported for multiple tickets. Mark cash collected using the payment controls.
4. Once all slots are filled, draw the pot. Horses are assigned with `random_int` using Fisher–Yates shuffle. The draw is final and locks runners and entries; payment status remains editable.
5. Record the confirmed first three horses to show the winning participants. Results can be corrected.

Prize pools use all registered entries, including unpaid entries. Collected and outstanding amounts are shown separately. Prizes are calculated in cents, with rounding remainder allocated to third place. The site records payment status; it does not process payments. Imported scratched runners remain visible but are excluded from available entry slots and the draw. If a refreshed field has fewer active runners than registered entries, the update is rejected and an admin must remove excess entries before retrying. Drawn pots preserve their saved field; later source changes do not change assignments.

Everyone can view the sweep. Only current `users.role = 'admin'` accounts can change it, rechecked against the database on each request. The existing PHP session provides shared admin sign-in when both apps use the same PHP host/session settings. Sweepstake has its own configuration and access controls. Signing out clears the shared login.

## Automatic field import

The source is the official date-specific [Racing Australia acceptances page](https://www.racingaustralia.horse/FreeFields/Acceptances.aspx?Key=2026Nov03,VIC,Flemington). The importer calculates the first Tuesday of November for the pot year, verifies the Flemington meeting date, and selects the 3200m Melbourne Cup by name rather than assuming a race number. It uses HTTPS with certificate verification, no redirects, a 15-second timeout, and a response size limit. The host must allow outbound HTTPS to `www.racingaustralia.horse`.

If the field is not published, the source is unavailable, or the page structure cannot be validated, the site keeps the existing field and displays the error to admins. A newly created pot is retained even when its first import fails. The last successful import and latest attempt are recorded separately. An unresolved import warning blocks the draw until a successful refresh or an explicit manual field is saved. Automatic retries wait 15 minutes after a failed attempt. Refreshes use authenticated, CSRF-protected POST requests; public visitors do not trigger imports. Refresh pauses while the admin is editing a form, and only runs while the selected pot is open in the browser; there is no background scheduler.

**Enter runners manually** remains available as a fallback. Saving a manual field clears imported details and pauses automatic updates for that pot. **Refresh Cup field** resumes automatic updates after a successful import.

The upstream HTML structure can change; an importer failure is reported instead of replacing the field with unreadable data. Official race results are still entered manually.

## Validation

```sh
php sweepstake/tests/field_import.php
find sweepstake -name '*.php' -exec php -l {} \;
```

The import test also runs the workflow suite. Tests use isolated SQLite and synthetic source fixtures to cover date/race selection, runner details, duplicate rejection, scratchings, refresh capacity protection, rollback, manual override, frozen assignments, pot isolation, payment isolation, result corrections, and payout rounding. They do not replace a MariaDB integration check. Verify initialization and the end-to-end workflow on the existing PHP/MariaDB host before use.

## Removing pots

Admins can expand **Delete this pot** below the selected pot, tick the confirmation, and select **Delete pot**. This permanently removes the pot and all its entries, payment records, runners, results, and import details. Open, drawn, and finished pots can be deleted. Other pots are preserved.
