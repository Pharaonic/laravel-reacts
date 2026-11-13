## Troubleshooting

### "The reaction type [x] is not selectable in the [default] group"

The reactable's group does not offer that type. Add the key to the group's `types` in `config/pharaonic/reacts.php`, or return the right group from `reactionGroup()`. A model without `ResolvesReactionGroup` uses `default_group`.

### "does not allow free-form emoji"

The group has no `'free_form' => true`, and none of the groups it extends sets it. Turn it on for the group that should accept any emoji.

### An emoji is refused although it looks like one

A free-form reaction must be exactly one emoji: no text, spaces or second emoji. Check for a trailing space from a form field. If every emoji is refused with `InvalidReactionConfiguration`, PHP's PCRE is older than 10.40; check `PCRE_VERSION` and update PHP.

### Two rows for the same post in single mode

The post was in multiple mode when they were stored. Reads never rewrite rows; the next `react()` keeps one, or reconcile all of them as [Integrity and Concurrency](#integrity-and-concurrency) shows.

### Reactions stay after deleting models

The models were deleted with a query (`Post::where(...)->delete()`), which fires no model events, or `cleanup_on_delete` is off, or the model was only soft deleted. See [Deletes and Soft Deletes](#deletion-and-soft-deletes).

### Counts merge different emoji on MySQL

The migration gives `type_key` a binary collation (`utf8mb4_bin`) on MySQL and MariaDB, because the default collations treat many emoji as equal. A table created another way needs that collation too.

### "The [reactions] table already exists"

Another package or migration already created a `reactions` table. Set `pharaonic.reacts.table` to another name before migrating.
