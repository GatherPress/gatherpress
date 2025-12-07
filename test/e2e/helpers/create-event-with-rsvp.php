<?php
/**
 * Helper script to create a GatherPress event with a properly initialized RSVP block.
 *
 * This script creates an event with all the necessary inner blocks and attributes
 * that the RSVP block requires to render correctly on the frontend.
 *
 * Usage: npm run wp-env run cli -- wp eval-file test/e2e/helpers/create-event-with-rsvp.php
 */

// Create event post.
$post_id = wp_insert_post(
	array(
		'post_type'   => 'gatherpress_event',
		'post_status' => 'publish',
		'post_title'  => 'Test Event with RSVP',
	)
);

if ( is_wp_error( $post_id ) ) {
	WP_CLI::error( 'Failed to create event post' );
	exit( 1 );
}

// Set event datetime meta (7 days in the future).
$future_date = gmdate( 'Y-m-d\TH:i:s', strtotime( '+7 days' ) );
update_post_meta( $post_id, 'gatherpress_datetime_start', $future_date );
update_post_meta( $post_id, 'gatherpress_datetime_end', gmdate( 'Y-m-d\TH:i:s', strtotime( '+7 days +2 hours' ) ) );
update_post_meta( $post_id, 'gatherpress_timezone', 'America/New_York' );

// Create minimal RSVP block with serializedInnerBlocks attribute.
// The visible content is the "no_status" template for logged-out users.
$post_content = <<<'BLOCKS'
<!-- wp:gatherpress/rsvp {"serializedInnerBlocks":""} -->
<div class="wp-block-gatherpress-rsvp"><!-- wp:gatherpress/modal-manager -->
<div class="wp-block-gatherpress-modal-manager"><!-- wp:buttons {"metadata":{"name":"Call to Action"},"align":"center","layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"tagName":"button","metadata":{"name":"RSVP Button"},"className":"gatherpress-modal--trigger-open"} -->
<div class="wp-block-button gatherpress-modal--trigger-open"><button type="button" class="wp-block-button__link wp-element-button">RSVP</button></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:gatherpress/modal {"metadata":{"name":"RSVP Modal"},"className":"gatherpress-modal--type-rsvp"} -->
<div class="wp-block-gatherpress-modal gatherpress-modal--type-rsvp"><!-- wp:gatherpress/modal-content -->
<div style="padding:20px;max-width:400px" class="wp-block-gatherpress-modal-content has-contrast-color has-white-background-color has-text-color has-background"><!-- wp:paragraph {"metadata":{"name":"RSVP Heading"},"style":{"spacing":{"margin":{"top":"0"},"padding":{"top":"0"}}}} -->
<p style="margin-top:0;padding-top:0"><strong>RSVP to this event</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"metadata":{"name":"RSVP Info"}} -->
<p>To set your attendance status, simply click the <strong>Attend</strong> button below.</p>
<!-- /wp:paragraph -->

<!-- wp:gatherpress/form-field {"fieldType":"checkbox","fieldName":"gatherpress_rsvp_anonymous","label":"List me as anonymous","autocomplete":"off","className":"gatherpress-rsvp-field-anonymous"} /-->

<!-- wp:buttons {"metadata":{"name":"Call to Action"},"align":"left","style":{"spacing":{"margin":{"bottom":"0"},"padding":{"bottom":"0"}}},"layout":{"type":"flex","justifyContent":"flex-start"}} -->
<div class="wp-block-buttons" style="margin-bottom:0;padding-bottom:0"><!-- wp:button {"tagName":"button","metadata":{"name":"RSVP Button"},"className":"gatherpress-rsvp--trigger-update"} -->
<div class="wp-block-button gatherpress-rsvp--trigger-update"><button type="button" class="wp-block-button__link wp-element-button">Attend</button></div>
<!-- /wp:button -->

<!-- wp:button {"tagName":"button","metadata":{"name":"Close Button"},"className":"is-style-outline gatherpress-modal--trigger-close"} -->
<div class="wp-block-button is-style-outline gatherpress-modal--trigger-close"><button type="button" class="wp-block-button__link wp-element-button">Close</button></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:gatherpress/modal-content --></div>
<!-- /wp:gatherpress/modal -->

<!-- wp:gatherpress/modal {"metadata":{"name":"Login Modal"},"className":"gatherpress-modal--login"} -->
<div class="wp-block-gatherpress-modal gatherpress-modal--login"><!-- wp:gatherpress/modal-content -->
<div style="padding:20px;max-width:400px" class="wp-block-gatherpress-modal-content has-contrast-color has-white-background-color has-text-color has-background"><!-- wp:paragraph {"metadata":{"name":"Login Heading"},"style":{"spacing":{"margin":{"top":"0"},"padding":{"top":"0"}}}} -->
<p style="margin-top:0;padding-top:0"><strong>Login Required</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"metadata":{"name":"Login Info"},"className":"gatherpress--has-login-url"} -->
<p class="gatherpress--has-login-url">This action requires an account. Please <a href="#gatherpress-login-url">Login</a> to RSVP to this event.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"metadata":{"name":"Register Info"},"className":"gatherpress--has-registration-url"} -->
<p class="gatherpress--has-registration-url">Don't have an account? <a href="#gatherpress-registration-url">Register here</a> to create one.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"metadata":{"name":"Call to Action"},"align":"left","style":{"spacing":{"margin":{"bottom":"0"},"padding":{"bottom":"0"}}},"layout":{"type":"flex","justifyContent":"flex-start"}} -->
<div class="wp-block-buttons" style="margin-bottom:0;padding-bottom:0"><!-- wp:button {"tagName":"button","metadata":{"name":"Close Button"},"className":"gatherpress-modal--trigger-close"} -->
<div class="wp-block-button gatherpress-modal--trigger-close"><button type="button" class="wp-block-button__link wp-element-button">Close</button></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:gatherpress/modal-content --></div>
<!-- /wp:gatherpress/modal --></div>
<!-- /wp:gatherpress/modal-manager --></div>
<!-- /wp:gatherpress/rsvp -->
BLOCKS;

// Update post with RSVP block.
wp_update_post(
	array(
		'ID'           => $post_id,
		'post_content' => $post_content,
	)
);

// Output the post ID for the test script to use with query string format.
// Using ?p={id} format works regardless of permalink settings.
echo $post_id;
