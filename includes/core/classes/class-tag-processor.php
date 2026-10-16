<?php
/**
 * Tag processor that can say where the current token starts.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use WP_HTML_Tag_Processor;

/**
 * Class Tag_Processor.
 *
 * WP_HTML_Tag_Processor finds tokens and rewrites attributes, but it cannot
 * insert markup and does not say where a token sits in the input. This adds
 * the position, so a caller can splice markup where the parser found a tag
 * rather than where a string search guessed one.
 *
 * @since 0.36.0
 */
final class Tag_Processor extends WP_HTML_Tag_Processor {
	/**
	 * Bookmark used to read a token's position.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const BOOKMARK = 'gatherpress-token';

	/**
	 * Byte offset of the current token in the input.
	 *
	 * Read through a bookmark, the processor's own record of a token's span,
	 * and released at once so the bookmark limit is never reached.
	 *
	 * @since 0.36.0
	 *
	 * @return int|null The offset, or null when the parser is not on a token.
	 */
	public function get_token_start(): ?int {
		// Nothing to bookmark before the first token or after the last.
		if ( null === $this->get_token_type() || ! $this->set_bookmark( self::BOOKMARK ) ) {
			return null;
		}

		$start = $this->bookmarks[ self::BOOKMARK ]->start;

		$this->release_bookmark( self::BOOKMARK );

		return $start;
	}
}
