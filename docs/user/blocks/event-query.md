# Event Query

GatherPress includes a block-variation of the `core/query` block, called _Event Query_ to be added to templates or pages/posts. This block allows for everything a normal query block can do including the following:


![Screenshot of the Event Query block inside the WordPress post editor.](https://github.com/user-attachments/assets/6496e4eb-308d-4e8d-823e-83f06dce2fb2)


1. Allows endless customization in terms of layout & style, incl. the use of interactive blocks.
2. Allows editors to select from explicit compositions of block-patterns directly via the blocks Choose (layout) button and the Replace button in the top toolbar.
3. Allows editors to choose starter-content via the blocks Start blank button and the underlying [Block Variation Picker](https://github.com/WordPress/gutenberg/tree/trunk/packages/block-editor/src/components/block-variation-picker).
4. Allows to query either past or upcoming events.
5. Allows to select for the inclusion or exclusion of started, but not yet finished, events.
6. If used within a `gatherpress_event` post, an editor can choose to "Exclude (the) current Event"
7. Allows for custom ordering (`ORDER BY`) of the events by:
   - datetime (default)
   - random
   - title
   - post_id
   - last modified date
1. ... in either ASC or DESC `ORDER`
2. Allows to filter the queried events by Author, Keyword, Topic or Venue (and any other additionally registered taxonomies).
3. The variation is automatically loaded, when an editor chooses the „Event“ post type in a regular `core/query` block.
