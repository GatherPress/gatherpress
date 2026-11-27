/**
 * Internal dependencies
 */
import { normalizeZeroOffsetTimezone } from '../helpers/timezone-shim';

/**
 * Fix the `wp.date` timezone before anything reads it.
 *
 * @since 0.37.0
 */
normalizeZeroOffsetTimezone();
