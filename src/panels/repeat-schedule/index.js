/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';

/**
 * Internal dependencies
 */
import { usePostTypeSupports } from '../../helpers/event';
import RepeatSchedule from '../../components/RepeatSchedule';

/**
 * The Repeat schedule panel.
 *
 * A collapsible sidebar panel of its own for how an event repeats, beside
 * Event settings, which keeps the first date's fields.
 *
 * @since TBD
 *
 * @return {JSX.Element|null} The panel, on a post type with event dates.
 */
export const RepeatSchedulePanel = () => {
	const isEvent = usePostTypeSupports( 'gatherpress-event-date' );

	return (
		isEvent && (
			<PluginDocumentSettingPanel
				name="gatherpress-repeat-schedule"
				title={ __( 'Repeat schedule', 'gatherpress' ) }
				className="gatherpress-repeat-schedule-panel"
			>
				<RepeatSchedule />
			</PluginDocumentSettingPanel>
		)
	);
};

registerPlugin( 'gatherpress-repeat-schedule', {
	render: RepeatSchedulePanel,
} );
