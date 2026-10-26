# T-912 Filament notification drift review

Branch: `fix/filament-notification-drift`. Test-only repair of main's
`php-tests` breakage; no production code, schema, fixture, release bytes or
dependency constraint change. `python scripts/validate.py` stays green.

Root cause: main's floating `filament/filament: ^5.0` resolved Filament
5.10.2+ after 2026-10-12. That release scopes `NotificationMailbox` in its
notifications provider; the confirmation approval integration test registered
only Livewire's provider, so `Notification::send()` and `assertNotified()`
resolved separate mailbox instances and three tests failed in every
`validate` matrix cell (`A notification was not sent`).

Fix: the test registers `Filament\Support\SupportServiceProvider` and
`Filament\Notifications\NotificationsServiceProvider` and pins array
cache/session/mailbox stores. Verification: RED reproduced first on Filament
5.10.3 (identical to CI), then the full Laravel package suite passes in
Docker — 612 tests / 3272 assertions, 2 expected MySQL skips — and the
confirmation test passes 6/6 on Filament 5.9.0, preserving the published
`^5.0` support range. After merge, [A2-02 B2 PR #54](https://github.com/kefyusuf/surfacerelay/pull/54)
is unblocked.

Security review focus: none required — approval flow assertions (locked
challenge, receipt isolation, redacted failure) are unchanged and still pass;
only the notification capture environment was repaired.
