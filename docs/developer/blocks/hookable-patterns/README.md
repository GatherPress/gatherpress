# Hookable patterns for events & venues

GatherPress registers multiple invisible block-patterns, that are used as template properties of the main post types.

Patterns allow to be filtered by the (upgraded since WordPress 6.5) Block Hooks API. Making use of this API brings some advantages, which are at least:

- GatherPress' blocks can be easily moved, modified or removed by extenders via standardized core code
- GatherPress provides central entry points for plugin developers to hook in own blocks, to extend GatherPress
- GatherPress' blocks will provide their hooking code themself, which keeps concerns separate and code clean

For example when you create a new event post, it gets pre-poulated with a set of blocks, curated within a block-pattern named `gatherpress/event-template`.

GatherPress combines four of such block-patterns to curate the creation of:

- [New Events](#new-event)
- [New Venues](#new-venue)
- [New Event Queries within any post](#new-event-queries-within-any-post)
- [Venue Details within any post](#venue-details-within-any-post)

## New Event

GatherPress adds the following blocks by default into a new created event:

- A block-pattern named `gatherpress/event-template`.


## New Venue

A new created venue will have the following blocks prepared by default:

- A block-pattern named `gatherpress/venue-template`
    - A block-pattern named `gatherpress/venue-details`, which keeps detailed information about a selected venue in the shape of blocks

## New Event Queries within any post

## Venue Details within any post

### Resources

- [@wordpress/hooks - Block Editor Handbook | Developer.WordPress.org](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-hooks/)
- [#devnote - Introducing Block Hooks for dynamic blocks - Make WordPress Core](https://make.wordpress.org/core/2023/10/15/introducing-block-hooks-for-dynamic-blocks/)
- [Exploring the Block Hooks API in WordPress 6.5 - WordPress Developer Blog](https://developer.wordpress.org/news/2024/03/25/exploring-the-block-hooks-api-in-wordpress-6-5/)
