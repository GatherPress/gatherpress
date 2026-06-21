<?php
/**
 * Runs {@see Test_Geocoding} under the multisite PHPUnit configuration so merged
 * coverage includes the same lines as single-site runs.
 *
 * @package GatherPress\Core
 */

namespace GatherPress\Tests\Core;

/**
 * Class Test_Geocoding_Multisite.
 *
 * @coversDefaultClass \GatherPress\Core\Geocoding
 *
 * @group multisite
 */
class Test_Geocoding_Multisite extends Test_Geocoding {

}
