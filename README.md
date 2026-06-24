# Meom Scheduled Removal

Schedule automatic unpublishing of posts. An editor picks a date and time in the
post sidebar, and when that moment arrives the post is automatically set to
`draft`. The date is stored in native post meta and edited with the core
Gutenberg `DateTimePicker` — no ACF required.

## How editors use it

1. Open a post in the block editor.
2. In the document settings sidebar, find the **Scheduled removal** panel.
3. Pick the date and time the post should be unpublished, then save.
4. To cancel, press **Clear** and save again.

When the scheduled time passes, the post is set to `draft` (it is not deleted),
so the content is preserved and can be re-published at any time.

## Supported post types

By default the field appears on the built-in **Posts** only. Extend it to other
post types with the `meom_scheduled_removal_post_types` filter:

```php
add_filter(
    'meom_scheduled_removal_post_types',
    function ( $post_types ) {
        $post_types[] = 'page';
        return $post_types;
    }
);
```

The same list is used everywhere: the meta is registered for those post types,
the editor panel renders for them, and the hourly sweep checks them.

## How it works

- **Meta key:** `meom_scheduled_removal` — an ISO `Y-m-d\TH:i:s` datetime string
  (the `DateTimePicker` output), interpreted in the site timezone
  (`wp_timezone()`).
- **Scheduling** is driven by metadata-change hooks (`added_post_meta`,
  `updated_post_meta`, `deleted_post_meta`), so it reacts to any save source —
  the block editor, quick edit, WP-CLI, or programmatic updates.
- Each change reconciles a single WP-Cron event (`meom_scheduled_removal_event`).
  If the chosen time is already in the past, the post is unpublished
  immediately.
- An hourly sweep (`meom_scheduled_removal_sweep`) is a safety net: it catches
  any past-due published post whose single event was missed (WP-Cron is
  best-effort, not guaranteed).

### Hooks

| Hook | Type | Purpose |
|------|------|---------|
| `meom_scheduled_removal_post_types` | filter | Post types the field applies to (default `['post']`). |
| `meom_scheduled_removal_event` | action (cron) | Per-post single event that unpublishes the post. |
| `meom_scheduled_removal_sweep` | action (cron) | Hourly backstop for missed events. |

## Notes & limitations

- Removal sets the post to `draft`; it never trashes or deletes content.
- A post is only acted on while it is `publish`; manually changing the status
  makes the scheduled event a no-op.
- If WP-Cron is entirely disabled on the site, neither the single event nor the
  hourly sweep can fire.
- The sweep processes up to 100 past-due posts per run; any overflow is handled
  on the next hourly run.

## Development

Built with [`@wordpress/scripts`](https://www.npmjs.com/package/@wordpress/scripts).
Node is managed via `nvm` — run `nvm use` first.

```bash
nvm use
npm install
npm run build      # production build to build/
npm run start      # development watch build
npm run lint:js    # ESLint
```

PHP is checked with the project's `MEOM-default` PHPCS standard:

```bash
# from the project root
vendor/bin/phpcs --standard=htdocs/wp-content/plugins/meom-scheduled-removal/.phpcs.xml.dist htdocs/wp-content/plugins/meom-scheduled-removal/
```

The `build/` directory is committed so the plugin deploys without a build step.
