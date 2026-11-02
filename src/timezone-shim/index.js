/**
 * Internal dependencies
 */
import { normalizeZeroOffsetTimezone } from '../helpers/timezone-shim';

/**
 * Fix the `wp.date` timezone before anything reads it.
 *
 * @since TBD
 */
normalizeZeroOffsetTimezone();
