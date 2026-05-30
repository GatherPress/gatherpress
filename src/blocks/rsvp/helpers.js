/**
 * Safely parse the `serializedInnerBlocks` attribute into a status→markup map.
 *
 * `serializedInnerBlocks` is a JSON string round-tripped through
 * `post_content`: a block attribute whose value is itself serialized block
 * markup. That double nesting is fragile — a single dropped escape anywhere in
 * the storage round-trip (issue #1704) leaves the editor with a malformed JSON
 * string. An unguarded `JSON.parse` of it throws on every render, tripping
 * Gutenberg's error boundary ("RSVP block has encountered an error and cannot
 * be previewed") and taking the whole block down.
 *
 * Treat an unparseable or non-object value as empty so the block degrades
 * gracefully — the hydrate effect re-seeds the per-status defaults — instead of
 * crashing the editor.
 *
 * @since 0.34.0
 *
 * @param {string} serialized The raw `serializedInnerBlocks` attribute value.
 *
 * @return {Object} Parsed status→markup map, or `{}` when the value is missing
 *                  or not valid JSON.
 */
export function parseSerializedInnerBlocks( serialized ) {
	try {
		const parsed = JSON.parse( serialized || '{}' );

		// A valid-JSON array/string/number is not a status map — normalize to
		// an object so callers can rely on `Object.keys` / property access.
		return parsed && 'object' === typeof parsed && ! Array.isArray( parsed )
			? parsed
			: {};
	} catch {
		// Malformed JSON — fall back to an empty map and let hydration rebuild.
		return {};
	}
}
