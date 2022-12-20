/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-i18n/
 */
import { __ } from "@wordpress/i18n";
import { useBlockProps } from "@wordpress/block-editor";
import { Button, Modal } from "@wordpress/components";
import { useState } from "@wordpress/element";

/**
 * The save function defines the way in which the different attributes should
 * be combined into the final markup, which is then serialized by the block
 * editor into `post_content`.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#save
 *
 * @return {WPElement} Element to render.
 */
export default function save() {
	const blockProps = useBlockProps.save();
	return (
		<div {...blockProps}>
			<p>
				{__(
					"AttendanceSelector – hello from the saved content!",
					"gatherpress"
				)}
			</p>
			<div
				data-gp_block_name="attendance-selector"
				data-gp_block_attrs="[]"
				style={{ border: '1px solid' }}
			>
				<div class="gp-attendance-selector">
					<div
						role="group"
						class="components-button-group gp-buttons wp-block-buttons"
					>
						<div class="gp-buttons__container wp-block-button">
							<a
								href="#"
								class="gp-buttons__button wp-block-button__link"
								aria-expanded="false"
								tabindex="0"
							>
								Attend
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>
	);
}
