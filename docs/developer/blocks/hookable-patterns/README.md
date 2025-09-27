# Hookable patterns for events & venues

GatherPress registers multiple invisible block-patterns, that are used as template properties for the main post types.

Patterns allow to be filtered by the (upgraded since WordPress 6.5) Block Hooks API. Making use of this API brings some advantages, which are at least:

- GatherPress' blocks can be easily moved, modified, or removed by extenders via standardized core code
- GatherPress provides central entry points for plugin developers to hook-in own blocks, to extend GatherPress
- GatherPress' blocks will provide their hooking code themself, which keeps concerns separate and code clean

For example, when you create a new event post, it gets pre-populated with a set of blocks, curated within a block-pattern named `gatherpress/event-template`.

## Overview of hookable patterns

GatherPress combines four of such block-patterns to curate the creation of:

- [New Events](#new-event)
- [New Venues](#new-venue)
- [New Event Queries within any post](#new-event-queries-within-any-post)
- [Venue Details within any post](#venue-details-within-any-post)

### New Event

GatherPress adds the following blocks by default into a new created event:

- A block-pattern named `gatherpress/event-template`, which contains the following blocks by default:

    - `gatherpress/event-date`
    - `gatherpress/add-to-calendar`
    - `gatherpress/venue`
    - `gatherpress/rsvp`
    - `core/paragraph`
    - `gatherpress/rsvp-response`


### New Venue

A new created venue will have the following blocks prepared by default:

- A block-pattern named `gatherpress/venue-template`, which contains the following block by default:

    - `gatherpress/venue`


### New Event Queries within any post

...

### Venue Details within any post

...

## Modify the blocks in the patterns

- [Change order of the default blocks](#change-order-of-the-default-blocks)
- [Add blocks to the default patterns](#add-blocks-to-the-default-patterns)
- [Remove default blocks](#remove-default-blocks)

### Change order of the default blocks

**Example**: To move the *RSVP-Response* block directly behind the *RSVP* block for every new created Event,
you could call:

```php
/**
 * Move the "RSVP-Response" block directly behind the "RSVP" block.
 *
 * @param string[]  $hooked_block_types The list of hooked block types.
 * @return string[]                     The modified list of hooked block types.
 */
add_filter( 'hooked_block_types', function( array $hooked_block_types ) : array {
    $index  = array_search('gatherpress/rsvp-response', $hooked_block_types);
    if ( $index !== false ) {
        // Remove the "RSVP-Response" block from its current position.
        $block = array_splice( $hooked_block_types, $index, 1 );
        // Find the index of the "RSVP" block.
        $rsvp_index = array_search( 'gatherpress/rsvp', $hooked_block_types );
        // Insert the "RSVP-Response" block directly behind the "RSVP" block.
        array_splice( $hooked_block_types, $rsvp_index + 1, 0, $block );
    }
    return $hooked_block_types;
});
```

### Add blocks to the default patterns

**Example**: To add the *Featured Image* block before the *Event Date* block for every new created Event,
you could call:

```php
/**
 * Add the 'Featured Image' block before the 'Event Date' block.
 *
 * @see https://developer.wordpress.org/reference/hooks/hooked_block_types/
 *
 * @param string[]                 $hooked_block_types The list of hooked  block types.
 * @param string                   $relative_position  The relative position  of the hooked blocks. Can be one of 'before', 'after', 'first_child', or  'last_child'.
 * @param string|null              $anchor_block_type  The anchor block type.
 * @param \WP_Block_Template|array $context            The block template,  template part, or pattern that the anchor block belongs to.
 * @return string[] The list of hooked block types.
 */
add_filter( 'hooked_block_types', function ( array $hooked_block_types, string $relative_position, ?string $anchor_block_type, $context ): array {
    // Check that the place to hook into is a pattern and
    // hook block into the "gatherpress/event-template" pattern.
    if ( 
        is_array( $context ) &&
        isset( $context['name'] ) &&
        'gatherpress/event-template' === $context['name'] &&
        'gatherpress/event-date' === $anchor_block_type &&
        'before' === $relative_position
    ) {
        $hooked_block_types[] = 'core/post-featured-image';
    }
    return $hooked_block_types;
}, 10, 4 );
```

### Remove default blocks

**Example**: To remove the *RSVP-Response* block, you could call:

```php
/**
 * Remove every use of the RSVP-Response block (everywhere).
 *
 * @see https://developer.wordpress.org/reference/hooks/hooked_block_types/
 *
 * @param string[]  $hooked_block_types The list of hooked block types.
 * @return string[]                     The modified list of hooked block types.
 */
add_filter( 'hooked_block_types', function( array $hooked_block_types ) : array {
    return array_diff( $hooked_block_types, array( 'gatherpress/rsvp-response' ) );
});
```

## Resources

- [@wordpress/hooks - Block Editor Handbook | Developer.WordPress.org](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-hooks/)
- [#devnote - Introducing Block Hooks for dynamic blocks - Make WordPress Core](https://make.wordpress.org/core/2023/10/15/introducing-block-hooks-for-dynamic-blocks/)
- [Exploring the Block Hooks API in WordPress 6.5 - WordPress Developer Blog](https://developer.wordpress.org/news/2024/03/25/exploring-the-block-hooks-api-in-wordpress-6-5/)
