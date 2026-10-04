/**
 * Look up a published post by ID across every post type with a given support.
 *
 * Shared by `findEventPostById()` and `findVenuePostById()`, which differ only
 * in the support they scan for.
 *
 * Returns `null` when the post type registry has not finished loading. The
 * caller's `useSelect` will re-run once it does, since `getPostTypes` is a
 * subscribed read.
 *
 * @since TBD
 *
 * @param {Function} selectFunc WordPress data `select` function.
 * @param {number}   postId     Post ID to resolve.
 * @param {string}   support    Post type support to scan for, such as
 *                              `gatherpress-event-date`.
 *
 * @return {Object|null} The post entity if a post type with the support owns
 *                       the ID and the post is published; null otherwise.
 */
export function findPublishedPostBySupport( selectFunc, postId, support ) {
	if ( ! postId ) {
		return null;
	}

	// `context: 'edit'` is required because WP REST only exposes the
	// `supports` field on post types in the edit context. Without it the
	// loop below never matches any type and the lookup silently fails.
	const postTypes = selectFunc( 'core' ).getPostTypes?.( {
		per_page: -1,
		context: 'edit',
	} );
	if ( ! Array.isArray( postTypes ) ) {
		return null;
	}

	for ( const type of postTypes ) {
		if ( ! type?.supports?.[ support ] ) {
			continue;
		}
		// Query by `include` filter rather than `getEntityRecord( id )` so a
		// miss returns an empty array (HTTP 200) instead of a 404. The 404s
		// are technically accurate but they show up in browser devtools and
		// look like a real bug to anyone reading the console. Edit context
		// matches the default `getEntityRecord` uses inside the editor and
		// guarantees full `meta` in the response.
		const records = selectFunc( 'core' ).getEntityRecords(
			'postType',
			type.slug,
			{ include: [ postId ], context: 'edit', per_page: 1 },
		);
		if ( Array.isArray( records ) && 0 < records.length ) {
			const post = records[ 0 ];
			if ( 'publish' === post?.status ) {
				return post;
			}
		}
	}

	return null;
}
