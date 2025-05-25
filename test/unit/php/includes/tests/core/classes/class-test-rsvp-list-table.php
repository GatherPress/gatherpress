        // Test event column
        $event_col = $this->list_table->column_default( $rsvp, 'event' );
        $this->assertStringContainsString( 'Test Event', $event_col );

        // Test approved column 