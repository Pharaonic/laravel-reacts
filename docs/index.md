---
name: Reacts

action:
  label: View on Packagist
  href: "{package.packagistUrl}"

views: components.packages

breadcrumbs:
  - label: Home
    href: route:home
  - label: Packages
    href: route:packages.index
  - label: "{technology.name} Packages"
    href: "url:/packages/{technology.slug}"
  - label: "{package.name}"

card:
  topic: database
  icon: star
  tags: reactions reacts emoji like love laugh wow free-form emoji single reaction multiple reactions eloquent polymorphic morph users posts comments chat messages counts uuid ulid events unique index
  description: "Let any Eloquent model react to any other with configured types such as like and love, or with any emoji where you allow it, in single or multiple mode, with counts and listings ready for Blade and JSON."

seo:
  title: "{package.fullName} - Emoji Reactions for Eloquent Models"
  description: "{package.name} adds reactions between any two Laravel Eloquent models: configured reaction types per group, free-form emoji, one or many reactions per user, database-enforced uniqueness, counts and N+1-free listings."
  keywords: laravel reactions, laravel reacts, laravel emoji reactions, eloquent reactions, laravel like love reactions, laravel polymorphic reactions, laravel facebook style reactions, laravel slack style reactions, laravel reaction counts, laravel emoji
  author: Pharaonic
  images:
    - "{package.cover}"
  openGraph:
    type: website
    siteName: Pharaonic
  twitter:
    card: summary_large_image

schema:
  "@type": SoftwareSourceCode
  name: "{package.name}"
  description: "{package.name} is a Laravel package that lets any Eloquent model react to any other with configured types or free-form emoji, in single or multiple mode, with unique indexes, events, counts and batched listings."
  keywords: laravel, eloquent, reactions, emoji, polymorphic relations, uuid, ulid, events, counts
  image: "{package.cover}"
  codeRepository: "{package.githubUrl}"
  programmingLanguage: PHP
  runtimePlatform: "{technology.name}"
  version: "{package.version}"
  datePublished: "{package.publishedAt}"
  dateModified: "{package.updatedAt}"
  license: "https://opensource.org/licenses/{package.license}"
  isAccessibleForFree: true
  sameAs:
    - "{package.githubUrl}"
    - "{package.packagistUrl}"
  author:
    "@id": url:/#organization
  publisher:
    "@id": url:/#organization
  interactionStatistic:
    "@type": InteractionCounter
    interactionType: https://schema.org/DownloadAction
    userInteractionCount: "{package.downloads}"
---
