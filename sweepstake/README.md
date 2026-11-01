# Melbourne Cup sweepstake

Served by the existing PHP site at `/sweepstake/`. No Node build or new hosting service is required.

## Setup

- `config.php` has independent connection settings matching the existing c69t MariaDB database. If database credentials change, update this config separately.
- PHP needs PDO MySQL and mbstring, and the database account needs access to the existing `users` table plus the three sweepstake tables.
- Open `/sweepstake/`, sign in with an existing c69t administrator account, then select **Initialize sweepstake**. Alternatively import `schema.sql` into the same database using the existing database administration process.
- Initialization creates only `sweepstake_pots`, `sweepstake_runners`, and `sweepstake_entries`. It does not change c69t tables or users.

## Running a sweep

1. Create any number of pots, with separate prices, race years and prize splits.
2. Paste the confirmed runners into each pot, one per line: `1 Horse name`. No race field is preloaded.
3. Add one participant entry per runner slot. Repeated names are supported for multiple tickets. Mark cash collected using the payment controls.
4. Once all slots are filled, draw the pot. Horses are assigned with `random_int` using Fisher–Yates shuffle. The draw is final and locks runners and entries; payment status remains editable.
5. Record the confirmed first three horses to show the winning participants. Results can be corrected.

Prize pools use all registered entries, including unpaid entries. Collected and outstanding amounts are shown separately. Prizes are calculated in cents, with rounding remainder allocated to third place. The site records payment status; it does not process payments. Handle scratches and update the field before the draw.

Everyone can view the sweep. Only current `users.role = 'admin'` accounts can change it, rechecked against the database on each request. The existing PHP session provides shared c69t sign-in when both apps use the same PHP host/session settings. Sweepstake does not include c69t's operational config or site-lock logic. Signing out clears the shared login.

## Validation

```sh
php sweepstake/tests/workflow.php
find sweepstake -name '*.php' -exec php -l {} \;
```

Workflow tests use an isolated SQLite database and cover draw validation, assignment uniqueness, final draw state, pot isolation, payment isolation, result corrections, and payout rounding. They do not replace a MariaDB integration check. Verify initialization and the end-to-end workflow on the existing PHP/MariaDB host before use.
