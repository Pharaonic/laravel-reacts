---
view: components.packages.features
variant: compact
badge: Key Features
title: Every reaction your app needs
subtitle: Two traits, one table, and a config file that lists your types.
items:
  - icon: grid
    title: Groups of Reaction Types
    text: 'Posts offer `like`, `love`, `wow` and more, chat offers its own set, and a group can `extend` another.'
  - icon: star
    title: Free-Form Emoji
    text: 'Where a group allows it, `$user->react($message, ''🚀'')` stores exactly one emoji; text and pairs of emoji are refused.'
  - icon: switch
    title: Single or Multiple
    text: 'In `multiple` mode a user adds several types; in `single` mode a new type replaces the old one in one call.'
  - icon: lock
    title: Unique by the Database
    text: Unique indexes keep one row per reaction, and one per user in single mode, even under concurrent requests.
  - icon: chart
    title: Counts and Listings
    text: '`ReactionView::forMany()` gives counts, choices and the viewer''s selection for a whole page in two queries.'
  - icon: shield-check
    title: Policy and Events
    text: 'A replaceable `ReactionPolicy` decides who may react, and `ReactionCreated`, `ReactionReplaced` and `ReactionRemoved` report every change.'
---
