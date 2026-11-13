:::badges
- Laravel Package {color=blue}
- {release.label} {color=green}
- {package.license} License {color=purple}
:::

# Laravel Reacts

Laravel Reacts lets any Eloquent model react to any other. A reaction is either a registered type you define in the configuration, such as `like` or `love`, or, in groups that allow it, one free-form emoji such as 🚀. Add `CanReact` to the model that reacts, such as a user, and `HasReactions` to the model that receives reactions, such as a post or a chat message. Every reaction is one row in a single `reactions` table; everything else, the types, their labels and emoji, and which kind of content offers which types, lives in `config/pharaonic/reacts.php`.

:::features
### Groups of Types {icon="grid"}
Posts, comments and chat messages each offer their own list of reaction types.

### Free-Form Emoji {icon="star"}
Where a group allows it, users react with exactly one emoji of their choice.

### Single or Multiple {icon="switch"}
Keep one reaction per user and post, or let users add several types.

### Unique by the Database {icon="lock"}
Unique indexes stop duplicates, even when requests arrive at the same time.

### Lists Without N+1 {icon="chart"}
One call returns counts, choices and the viewer's selection for a whole page.

### Policy and Events {icon="shield-check"}
Decide who may react, and listen to every reaction that was added, replaced or removed.
:::

:::info Quick Tip
The package has no routes, controllers or views. Authorize the request in your application, call `react()` or `toggleReaction()`, and render the array that [ReactionView](#listing-reactions) returns.
:::
