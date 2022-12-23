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
			<div
				data-gp_block_name="attendance-selector"
				data-gp_block_attrs="[]"
				className="gatherpress-attendance-container"
			>
				<div className="gatherpress-attendance-selector-here">
					<div
						role="group"
						className="components-button-group gatherpress-buttons wp-block-buttons"
					>
						<div className="gatherpress-buttons__container wp-block-button">
							<a
								href="#"
								className="gatherpress-buttons__button wp-block-button__link"
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
