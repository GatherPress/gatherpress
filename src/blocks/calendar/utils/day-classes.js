/**
 * Class names of a calendar day cell in the editor.
 *
 * @package
 * @since TBD
 */

/**
 * Build the class names of a day cell, as Calendar_Day::get_day_cell_classes() does.
 *
 * @since TBD
 *
 * @param {Object}  day            Day descriptor.
 * @param {string}  day.date       Y-m-d date of the day.
 * @param {boolean} day.isEmpty    Whether the cell pads the grid.
 * @param {boolean} day.isWeekend  Whether the day is a weekend day.
 * @param {string}  day.weekday    Weekday slug.
 * @param {Array}   day.posts      Entries of the day.
 * @param {string}  today          Today's Y-m-d date on the site.
 * @param {Object}  options        Layout of the day.
 * @param {boolean} options.ribbon Whether the day uses the Ribbon layout.
 * @param {boolean} options.grid   Whether the day is in a time grid.
 *
 * @return {Array<string>} Class names.
 */
export function getDayClassNames( day, today, { ribbon, grid } ) {
	const posts = day.posts ?? [];
	const isDated = ! day.isEmpty && Boolean( day.date );

	return [
		'gatherpress-calendar__day',
		day.isEmpty ? 'gatherpress--is-empty' : '',
		isDated && day.date === today ? 'gatherpress--is-today' : '',
		isDated && day.date < today ? 'gatherpress--is-past' : '',
		isDated && day.date > today ? 'gatherpress--is-future' : '',
		0 < posts.length ? 'gatherpress--has-posts' : '',
		posts.some( ( p ) => 1 < ( p.totalDays ?? 1 ) || p.isAllDay )
			? 'gatherpress--has-multi-day-event'
			: '',
		day.isWeekend ? 'gatherpress--is-weekend' : '',
		day.weekday ? `gatherpress--is-${ day.weekday }` : '',
		ribbon || grid ? 'is-style-ribbon' : '',
		grid ? 'gatherpress--has-time-grid' : '',
	].filter( Boolean );
}
