<?php
/**
 * Test implementation of the RSVP flag Base that never declares a slug.
 *
 * @package GatherPress\Core\Rsvp\Flag
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Rsvp\Flag;

use GatherPress\Core\Rsvp\Flag\Base;

/**
 * Test implementation of the RSVP flag Base that never declares a slug.
 *
 * Stands in for a subclass that forgets to override `SLUG`, which must be
 * refused rather than written as an empty term.
 */
class Test_Base_Without_Slug extends Base {
}
