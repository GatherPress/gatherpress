/**
 * External dependencies
 */
import { v4 as uuidv4 } from 'uuid';

/**
 * Maximum number of items kept in a checklist.
 *
 * Mirrors `Checklist::MAX_ITEMS` in PHP so the editor and the sanitizer agree
 * on what a checklist can hold.
 *
 * @since 0.36.0
 * @type {number}
 */
export const MAX_CHECKLIST_ITEMS = 200;

/**
 * Maximum number of characters kept per item.
 *
 * Mirrors `Checklist::MAX_TEXT_LENGTH` in PHP.
 *
 * @since 0.36.0
 * @type {number}
 */
export const MAX_CHECKLIST_TEXT_LENGTH = 255;

/**
 * Maximum number of characters kept in an item id.
 *
 * Mirrors `Checklist::MAX_ID_LENGTH` in PHP so an over-long id is dropped in
 * the editor rather than only on save, where the PHP sanitizer would silently
 * discard the whole row.
 *
 * @since 0.36.0
 * @type {number}
 */
export const MAX_CHECKLIST_ID_LENGTH = 64;

/**
 * Coerce a stored `completed` value to a boolean.
 *
 * Mirrors `rest_sanitize_boolean()` on the PHP side, where the strings
 * `"false"` and `"0"` count as false. Plain `Boolean()` would read `"false"`
 * as true, so a checklist written by older or external code could show a
 * completed row as still open.
 *
 * @since 0.36.0
 *
 * @param {*} value Raw completed value.
 *
 * @return {boolean} Whether the item is complete.
 */
function coerceCompleted( value ) {
	if ( 'string' === typeof value ) {
		const normalized = value.toLowerCase();

		if ( 'false' === normalized || '0' === normalized ) {
			return false;
		}
	}

	return Boolean( value );
}

/**
 * Coerce one stored entry into a checklist item.
 *
 * Returns null for entries that carry no usable id, which is how they get
 * dropped. Text is trimmed to the stored cap and `completed` is normalized to
 * a boolean so a stored `"1"` or `1` does not leak into the checkbox state.
 *
 * @since 0.36.0
 *
 * @param {*} entry Raw entry from a parsed checklist.
 *
 * @return {{id: string, text: string, completed: boolean}|null} Item, or null when unusable.
 */
export function sanitizeChecklistItem( entry ) {
	if ( ! entry || 'object' !== typeof entry ) {
		return null;
	}

	const id = String( entry.id ?? '' ).trim();

	// Counted as codepoints, matching `mb_strlen()` on the PHP side, so an id
	// built from multi-byte characters is measured the same way in both places.
	if ( ! id || Array.from( id ).length > MAX_CHECKLIST_ID_LENGTH ) {
		return null;
	}

	const text = 'string' === typeof entry.text ? entry.text : '';

	return {
		id,
		text: Array.from( text ).slice( 0, MAX_CHECKLIST_TEXT_LENGTH ).join( '' ),
		completed: coerceCompleted( entry.completed ),
	};
}

/**
 * Parse a stored checklist value into items.
 *
 * The meta ships as a JSON string, so anything other than a JSON array of
 * usable items resolves to an empty list. That keeps a malformed write, or a
 * value the store has not loaded yet, from breaking the editor.
 *
 * @since 0.36.0
 *
 * @param {*} raw Raw meta value.
 *
 * @return {Object[]} Parsed checklist items.
 */
export function parseChecklist( raw ) {
	if ( 'string' !== typeof raw || '' === raw.trim() ) {
		return [];
	}

	let decoded;

	try {
		decoded = JSON.parse( raw );
	} catch {
		// Malformed JSON, so there is nothing usable to show.
		return [];
	}

	if ( ! Array.isArray( decoded ) ) {
		return [];
	}

	return decoded
		.map( sanitizeChecklistItem )
		.filter( ( item ) => null !== item )
		.slice( 0, MAX_CHECKLIST_ITEMS );
}

/**
 * Encode checklist items for storage.
 *
 * @since 0.36.0
 *
 * @param {Object[]} items Checklist items.
 *
 * @return {string} JSON-encoded checklist, capped to what PHP will keep.
 */
export function serializeChecklist( items ) {
	const stored = ( Array.isArray( items ) ? items : [] )
		.map( sanitizeChecklistItem )
		.filter( ( item ) => null !== item )
		.slice( 0, MAX_CHECKLIST_ITEMS );

	return JSON.stringify( stored );
}

/**
 * Build a new, empty checklist item.
 *
 * The id is generated rather than derived from the text so an item keeps its
 * identity while the author rewrites it, and so React keys stay stable.
 *
 * @since 0.36.0
 *
 * @param {string} text Initial item text.
 *
 * @return {{id: string, text: string, completed: boolean}} The new item.
 */
export function createChecklistItem( text = '' ) {
	return {
		id: uuidv4(),
		text,
		completed: false,
	};
}

/**
 * Count how much of a checklist is done.
 *
 * @since 0.36.0
 *
 * @param {Object[]} items Checklist items.
 *
 * @return {{completed: number, total: number}} Progress counts.
 */
export function getChecklistProgress( items ) {
	const list = Array.isArray( items ) ? items : [];

	return {
		completed: list.filter( ( item ) => Boolean( item?.completed ) ).length,
		total: list.length,
	};
}

/**
 * Append an item to a checklist.
 *
 * @since 0.36.0
 *
 * @param {Object[]} items Checklist items.
 * @param {string}   text  Text for the new item.
 *
 * @return {Object[]} New checklist, or the same list when it is already full.
 */
export function addChecklistItem( items, text = '' ) {
	const list = Array.isArray( items ) ? items : [];

	if ( list.length >= MAX_CHECKLIST_ITEMS ) {
		return list;
	}

	return [ ...list, createChecklistItem( text ) ];
}

/**
 * Change one item, addressed by id.
 *
 * @since 0.36.0
 *
 * @param {Object[]} items  Checklist items.
 * @param {string}   id     Id of the item to change.
 * @param {Object}   change Fields to merge into the item.
 *
 * @return {Object[]} New checklist.
 */
export function updateChecklistItem( items, id, change ) {
	const list = Array.isArray( items ) ? items : [];

	return list.map( ( item ) =>
		item.id === id ? { ...item, ...change } : item
	);
}

/**
 * Drop one item, addressed by id.
 *
 * @since 0.36.0
 *
 * @param {Object[]} items Checklist items.
 * @param {string}   id    Id of the item to drop.
 *
 * @return {Object[]} New checklist.
 */
export function removeChecklistItem( items, id ) {
	const list = Array.isArray( items ) ? items : [];

	return list.filter( ( item ) => item.id !== id );
}

/**
 * Move one item up or down the list.
 *
 * Returns the list unchanged when the item is already at the edge it is being
 * moved towards, so the buttons can stay enabled without reordering anything
 * unexpected.
 *
 * @since 0.36.0
 *
 * @param {Object[]} items  Checklist items.
 * @param {string}   id     Id of the item to move.
 * @param {number}   offset Negative to move up, positive to move down.
 *
 * @return {Object[]} New checklist.
 */
export function moveChecklistItem( items, id, offset ) {
	const list = Array.isArray( items ) ? items : [];
	const from = list.findIndex( ( item ) => item.id === id );
	const to = from + offset;

	if ( 0 > from || 0 > to || to >= list.length ) {
		return list;
	}

	const next = [ ...list ];
	const [ moved ] = next.splice( from, 1 );
	next.splice( to, 0, moved );

	return next;
}
