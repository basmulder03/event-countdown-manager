# Event Countdown Manager

## Features

- Event custom post type with date/timezone, call-to-action label, and call-to-action URL.
- Countdown rendering via shortcode and blocks.
- Auto-created Events page (`ecm/upcoming-events` block).
- Read-only mirrored regular posts synced from events.
- Locale-aware event metadata with WordPress core locale fallback.
- Simple global theming via plugin settings (CSS variables).

## Shortcodes

- `[ecm_event_countdown event_id="123"]`
- `[ecm_upcoming_events limit="5"]`

## Blocks

- `ecm/event-countdown`
- `ecm/upcoming-events`

## Admin

- Event editor: `Events` post type.
- Settings: `Settings > Event Countdown`.

## WP-CLI

- `wp ecm seed --count=3` creates demo events and triggers mirror sync.
- `wp ecm cleanup_demo` removes demo events and mirrored posts.
- `wp ecm test_sync` runs sync + read-only mirror assertions.

## PHPUnit

1. Install test suite files:

```bash
bash bin/install-wp-tests.sh wordpress_test root root db latest true
```

2. Run tests with polyfills path:

```bash
WP_TESTS_PHPUNIT_POLYFILLS_PATH=/tmp/PHPUnit-Polyfills phpunit -c phpunit.xml.dist
```
