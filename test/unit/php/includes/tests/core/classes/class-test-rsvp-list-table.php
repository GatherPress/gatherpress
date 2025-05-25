<?php
/**
 * Class handles unit tests for GatherPress\Core\RSVP_List_Table.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\RSVP_List_Table;
use GatherPress\Tests\Base;
use WP_Screen;

/**
 * Class Test_RSVP_List_Table.
 *
 * @coversDefaultClass \GatherPress\Core\RSVP_List_Table
 */
class Test_RSVP_List_Table extends Base {
    public function test_column_default(): void {
        // Test event column
        $event_col = $this->list_table->column_default( $rsvp, 'event' );
        $this->assertStringContainsString( 'Test Event', $event_col );

        // Test approved column
    }
} 