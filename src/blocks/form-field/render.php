<?php
/**
 * Render Form Field block.
 *
 * Dynamically renders a form field with customizable styles and attributes.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Form_Field;

$gatherpress_form_field = new Form_Field( $attributes );

$gatherpress_form_field->render();
