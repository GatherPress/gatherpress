/**
 * WordPress dependencies
 */
import {
	DatePicker,
	SelectControl,
	CheckboxControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export const PostDateQueryControls = ( { attributes, setAttributes } ) => {
	const {
		query: {
			date_query: {
				relation: relationFromQuery = '',
				date_primary: datePrimary = new Date(),
				date_secondary: dateSecondary = new Date(),
				inclusive: isInclusive = false,
			} = {},
		} = {},
	} = attributes;

	return (
		<>
			<h2>{ __( 'Post Date Query', 'gatherpress-query-loop' ) }</h2>
			<SelectControl
				label={ __( 'Date Relationship', 'gatherpress-query-loop' ) }
				value={ relationFromQuery }
				options={ [
					{ label: 'None', value: '' },
					{ label: 'Before', value: 'before' },
					{ label: 'After', value: 'after' },
					{ label: 'Between', value: 'between' },
				] }
				onChange={ ( relation ) => {
					setAttributes( {
						query: {
							...attributes.query,
							date_query:
								relation !== ''
									? {
											...attributes.query.date_query,
											relation,
									  }
									: '',
						},
					} );
				} }
			/>
			{ relationFromQuery !== '' && (
				<>
					{ relationFromQuery === 'between' && (
						<h4>{ __( 'Start date', 'gatherpress-query-loop' ) }</h4>
					) }
					<DatePicker
						currentDate={ datePrimary }
						onChange={ ( newDate ) => {
							setAttributes( {
								query: {
									...attributes.query,
									date_query: {
										...attributes.query.date_query,
										date_primary: newDate,
									},
								},
							} );
						} }
					/>

					{ relationFromQuery === 'between' && (
						<>
							<h4>{ __( 'End date', 'gatherpress-query-loop' ) }</h4>
							<DatePicker
								currentDate={ dateSecondary }
								onChange={ ( newDate ) => {
									setAttributes( {
										query: {
											...attributes.query,
											date_query: {
												...attributes.query.date_query,
												date_secondary: newDate,
											},
										},
									} );
								} }
							/>
						</>
					) }

					<br />
					<CheckboxControl
						label={ __(
							'Include selected date(s)',
							'gatherpress-query-loop'
						) }
						help={ __(
							'Should the selected date(s) be included in your query?',
							'gatherpress-query-loop'
						) }
						checked={ isInclusive }
						onChange={ ( newIsInclusive ) => {
							setAttributes( {
								query: {
									...attributes.query,
									date_query: {
										...attributes.query.date_query,
										inclusive: newIsInclusive,
									},
								},
							} );
						} }
					/>
				</>
			) }
		</>
	);
};
