---
view: components.packages.package-hero
badges:
  - label: Laravel Package
    color: blue
  - label: "{package.latestVersionLabel}"
    color: green
  - label: "{package.license} License"
    color: purple
  - label: "{package.downloadsShort}+ downloads"
    color: blue
eyebrow: "{package.name}"
title: Reactions for anything
highlight: with one table
buttons:
  - label: View Full Documentation
    href: "{card.docsUrl}"
    style: primary
    icon: arrow-right
  - label: View on GitHub
    href: "{package.githubUrl}"
    style: ghost
    external: true
install: "{card.install}"
labels:
  copy: Copy
  copied: Copied!
---

Add `CanReact` to the model that reacts and `HasReactions` to the model that receives reactions, run one migration, and write `$user->react($post, 'love')`. Reaction types come from your config, grouped per kind of content; chat messages can accept any emoji, and a post can keep one reaction per user. Unique indexes keep every reaction to one row, even when requests arrive at the same moment.
