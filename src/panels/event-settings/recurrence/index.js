/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { PanelRow, ToggleControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { usePostTypeSupports } from '../../../helpers/event';
import ApplyScope from './apply-scope';
import RsvpImpact from './rsvp-impact';
import FrequencyControl from './frequency';
import WeekdaysControl from './weekdays';
import MonthlyControl from './monthly';
import EndConditionControl from './end-condition';

/**
 * Default recurrence rule, written to the `gatherpress_recurrence` blob the
 * moment the "Repeat" toggle is switched on, and used to fill any field
 * missing from a stored or malformed blob.
 *
 * @since 0.36.0
 * @type {Object}
 */
const DEFAULT_RULE = {
	frequency: 'daily',
	interval: 1,
	weekdays: [],
	monthly_mode: 'day_of_month',
	monthly_day: 1,
	monthly_ordinal: 1,
	monthly_weekday: 1,
	end_type: 'never',
	until: '',
	count: 0,
};

/**
 * Clamp an integer to an inclusive range, matching `Rule::from_array()`'s own
 * clamping (interval below 1 becomes 1; the server additionally rejects
 * anything above `MAX_INTERVAL` / `MAX_COUNT` outright, so the UI clamps at
 * both ends rather than letting an over-range value round-trip into a
 * rejected save). Callers always pass an already-coerced `Number(...)` value
 * from a `type="number"` control, whose own sanitization keeps a non-numeric
 * entry from ever reaching here as `NaN`. There is no separate not-a-number
 * guard because there is no reachable path that would exercise it.
 *
 * @since 0.36.0
 *
 * @param {number} value Value to clamp.
 * @param {number} min   Inclusive lower bound.
 * @param {number} max   Inclusive upper bound.
 *
 * @return {number} The clamped value.
 */
function clampInt( value, min, max ) {
	if ( value < min ) {
		return min;
	}

	return value > max ? max : value;
}

/**
 * Normalize a typed day-of-month entry to an in-range integer, or reject it.
 *
 * `Rule::is_valid_monthly_shape()` requires 1 through 31, and a blob that
 * fails it is discarded server-side without surfacing anything in the editor:
 * the ten mirrors and the projection are cleared while the panel still shows
 * an enabled recurrence. A native number input accepts typed and pasted values
 * its `min`/`max` never constrain, so `0`, `32`, `-1`, `1.9` and `31abc` all
 * reach here.
 *
 * Numeric input is normalized (truncated toward zero, then clamped into
 * range). Input with no numeric value at all is rejected as `null`, which
 * `isPersistable()` treats as an incomplete rule so the last known-good blob
 * stays on the post. That covers a blank field mid-edit and pasted text.
 *
 * @since 0.36.0
 *
 * @param {*} value Raw control value, of unknown shape.
 *
 * @return {number|null} A day of the month between 1 and 31, or null when the
 *                       value carries no numeric day at all.
 */
function normalizeMonthlyDay( value ) {
	const raw = String( value ).trim();
	const parsed = Number( raw );

	if ( '' === raw || ! Number.isFinite( parsed ) ) {
		return null;
	}

	return clampInt( Math.trunc( parsed ), 1, 31 );
}

/**
 * Frequencies `Rule::is_valid()` recognizes.
 *
 * @since 0.36.0
 * @type {string[]}
 */
const VALID_FREQUENCIES = [ 'daily', 'weekly', 'monthly', 'yearly' ];

/**
 * End types `Rule::is_valid()` recognizes.
 *
 * @since 0.36.0
 * @type {string[]}
 */
const VALID_END_TYPES = [ 'never', 'until', 'count' ];

/**
 * Worst-case candidate days per occurrence, by frequency, mirroring
 * `Rule::BUDGET_DAYS_PER_FREQUENCY`.
 *
 * @since 0.36.0
 * @type {Object}
 */
const BUDGET_DAYS_PER_FREQUENCY = {
	daily: 1,
	weekly: 7,
	monthly: 31,
	yearly: 366,
};

/**
 * The expander's iteration backstop, mirroring `Expander::MAX_ITERATIONS`.
 *
 * @since 0.36.0
 * @type {number}
 */
const EXPANDER_MAX_ITERATIONS = 200000;

/**
 * Read a decoded JSON value the way `Rule::to_int()` reads it.
 *
 * Not a cast. `Rule::from_array()` does not use PHP's `(int)`; it uses
 * `to_int()`, which accepts an actual integer or a canonical integer string
 * and returns null for everything else, and a single null rejects the whole
 * rule. So a fractional interval, a boolean count, and a leading-digits string
 * are all refusals on the server, not values. Truncating them here is what let
 * this panel present a rule as enabled while the server had rejected the blob
 * and cleared its projection.
 *
 * One case the two sides cannot agree on: JSON `2.0` decodes to a PHP float,
 * which `is_int()` refuses, and to the JavaScript number 2, which is an
 * integer. Nothing this side can inspect distinguishes it from `2`, and the
 * blob this panel writes never emits that form.
 *
 * @since 0.36.0
 *
 * @param {*} value Decoded value of unknown type.
 *
 * @return {number|null} The integer, or null when the server would refuse it.
 */
function toServerInt( value ) {
	if ( Number.isInteger( value ) ) {
		return value;
	}

	if ( 'string' === typeof value && /^-?(?:0|[1-9][0-9]*)$/.test( value ) ) {
		return Number( value );
	}

	return null;
}

/**
 * Parse an end date exactly as strictly as `Rule::from_array()` does.
 *
 * The PHP side uses `DateTimeImmutable::createFromFormat( '!Y-m-d', ... )`
 * and rejects on warnings, so a relative string, trailing garbage, or a
 * rolled-over calendar date like 2026-02-31 is no end date at all. The
 * round-trip through `Date.UTC` reproduces the rollover check.
 *
 * @since 0.36.0
 *
 * @param {*} until Decoded `until` value.
 *
 * @return {boolean} Whether the value is a real `Y-m-d` calendar date.
 */
function isParseableUntil( until ) {
	const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec( until );

	if ( ! match ) {
		return false;
	}

	const [ , year, month, day ] = match.map( Number );
	const date = new Date( Date.UTC( year, month - 1, day ) );

	return (
		date.getUTCFullYear() === year &&
		date.getUTCMonth() === month - 1 &&
		date.getUTCDate() === day
	);
}

/**
 * Read a decoded blob the way `Rule::from_array()` reads it.
 *
 * Every field goes through the same cast and clamp the server applies, and
 * a missing field takes the server's own default (an empty string, zero, or
 * an empty list), never this panel's authoring default. Filling a missing
 * `end_type` with 'never' here would validate a partial blob the server
 * rejects.
 *
 * @since 0.36.0
 *
 * @param {Object} parsed Decoded blob object.
 *
 * @return {Object} The rule as the server would read it.
 */
function coerceStoredRule( parsed ) {
	const interval = toServerInt( parsed.interval ?? 1 );
	const weekdays = Array.isArray( parsed.weekdays )
		? [ ...new Set( parsed.weekdays.map( toServerInt ) ) ].sort(
			( first, second ) => first - second,
		)
		: [];

	return {
		frequency: String( parsed.frequency ?? '' ),
		interval: null === interval ? null : Math.max( 1, interval ),
		weekdays,
		monthly_mode: String( parsed.monthly_mode ?? '' ),
		monthly_day: toServerInt( parsed.monthly_day ?? 0 ),
		monthly_ordinal: toServerInt( parsed.monthly_ordinal ?? 0 ),
		monthly_weekday: toServerInt( parsed.monthly_weekday ?? 0 ),
		end_type: String( parsed.end_type ?? '' ),
		// Kept raw, not narrowed to a string. `from_array()` decides
		// `$has_until` from the raw field, so a non-string `until` is a
		// present-but-unparsable end date that rejects the rule, where
		// blanking it here would read as no end date at all.
		until: parsed.until ?? '',
		count: toServerInt( parsed.count ?? 0 ),
	};
}

/**
 * Mirror `Rule::is_valid_monthly_shape()` on a server-coerced rule.
 *
 * @since 0.36.0
 *
 * @param {Object} rule Server-coerced rule from `coerceStoredRule()`.
 *
 * @return {boolean} Whether the monthly mode and its companion fields agree.
 */
function isValidMonthlyShape( rule ) {
	if ( 'day_of_month' === rule.monthly_mode ) {
		return 1 <= rule.monthly_day && 31 >= rule.monthly_day;
	}

	if ( 'nth_weekday' === rule.monthly_mode ) {
		return (
			[ 1, 2, 3, 4, -1 ].includes( rule.monthly_ordinal ) &&
			0 <= rule.monthly_weekday &&
			6 >= rule.monthly_weekday
		);
	}

	return false;
}

/**
 * Mirror `Rule::is_valid_end_shape()`, plus `from_array()`'s rejection of a
 * rule carrying both an end date and a count, on a server-coerced rule.
 *
 * An unparsable `until` reads as no end date at all, exactly as it does on
 * the PHP side, so it neither satisfies an until rule nor collides with a
 * count one.
 *
 * @since 0.36.0
 *
 * @param {Object} rule Server-coerced rule from `coerceStoredRule()`.
 *
 * @return {boolean} Whether the end-of-series fields are internally consistent.
 */
function isValidEndShape( rule ) {
	const hasUntil = isParseableUntil( rule.until );

	if ( 'until' === rule.end_type ) {
		return hasUntil && 0 === rule.count;
	}

	if ( 'count' === rule.end_type ) {
		if ( 1 > rule.count || 730 < rule.count || hasUntil ) {
			return false;
		}

		const perOccurrence =
			BUDGET_DAYS_PER_FREQUENCY[ rule.frequency ] * rule.interval;
		const worstCaseDays = rule.count * perOccurrence;
		const budget = worstCaseDays + 366;

		return budget <= EXPANDER_MAX_ITERATIONS;
	}

	return ! hasUntil && 0 === rule.count;
}

/**
 * Decide whether the server would accept this stored rule, mirroring
 * `Rule::from_array()` and `Rule::is_valid()`.
 *
 * The panel never presents a stored rule the server has rejected:
 * `Meta::write_recurrence()` clears every derived mirror and the projected
 * occurrence rows for a rejected blob, so the post is not recurring no
 * matter what the blob says. If the two sides ever disagree, this side is
 * the one to fix.
 *
 * @since 0.36.0
 *
 * @param {Object} rule Server-coerced rule from `coerceStoredRule()`.
 *
 * @return {boolean} Whether `Rule::from_array()` would return a rule.
 */
function isServerValidRule( rule ) {
	// `to_int()` returning null for any numeric field rejects the whole rule
	// on the server, so a refusal has to be a refusal here too rather than a
	// coerced value that renders as an enabled schedule.
	const integers = [
		rule.interval,
		rule.monthly_day,
		rule.monthly_ordinal,
		rule.monthly_weekday,
		rule.count,
	];

	if (
		integers.some( ( field ) => ! Number.isInteger( field ) ) ||
		rule.weekdays.some( ( day ) => ! Number.isInteger( day ) )
	) {
		return false;
	}

	// `from_array()` runs both of these on the raw field's presence, before
	// the shape checks below ever see a parsed date. A nonempty `until` that
	// does not parse is rejected rather than erased, and RFC 5545 forbids a
	// rule carrying both an end date and a count, so an unparsable `until`
	// cannot slip a count rule through by failing to parse.
	const hasRawUntil = '' !== ( rule.until ?? '' );

	if (
		hasRawUntil &&
		( ! isParseableUntil( rule.until ) || 0 < rule.count )
	) {
		return false;
	}

	if (
		! VALID_FREQUENCIES.includes( rule.frequency ) ||
		52 < rule.interval ||
		! VALID_END_TYPES.includes( rule.end_type )
	) {
		return false;
	}

	if (
		'weekly' === rule.frequency &&
		( 0 === rule.weekdays.length ||
			rule.weekdays.some( ( day ) => 0 > day || 6 < day ) )
	) {
		return false;
	}

	if ( 'monthly' === rule.frequency && ! isValidMonthlyShape( rule ) ) {
		return false;
	}

	return isValidEndShape( rule );
}

/**
 * Parse the `gatherpress_recurrence` blob into panel state.
 *
 * A missing, empty, or malformed blob is treated as "no recurrence" rather
 * than surfacing a parse error: there is no rule in it to display or to
 * repair. A decoded object is validated against the server schema
 * (`isServerValidRule()`) before it is presented as enabled. A rule the
 * server rejects comes back disabled with `invalidStored` set, and the panel
 * explains the state instead of silently normalizing values (a stored
 * `monthly_day` of 99 clamped to 31 would present an enabled rule while the
 * server has cleared the mirrors and the projection).
 *
 * A valid blob is merged onto `DEFAULT_RULE` so fields the active frequency
 * does not use still render their controls with sane values, and its
 * numeric fields take the server's own reading of them.
 *
 * @since 0.36.0
 *
 * @param {string|undefined} raw Raw `gatherpress_recurrence` meta value.
 *
 * @return {Object} `{ enabled, rule, invalidStored }` panel state.
 */
function parseRecurrenceBlob( raw ) {
	if ( ! raw ) {
		return {
			enabled: false,
			rule: { ...DEFAULT_RULE },
			invalidStored: false,
		};
	}

	try {
		const parsed = JSON.parse( raw );

		if ( ! parsed || 'object' !== typeof parsed ) {
			return {
				enabled: false,
				rule: { ...DEFAULT_RULE },
				invalidStored: false,
			};
		}

		const coerced = coerceStoredRule( parsed );

		if ( ! isServerValidRule( coerced ) ) {
			return {
				enabled: false,
				rule: { ...DEFAULT_RULE },
				invalidStored: true,
			};
		}

		const rule = {
			...DEFAULT_RULE,
			...parsed,
			interval: coerced.interval,
			weekdays: coerced.weekdays,
			count: coerced.count,
			// An unparsable date is no date to the server; displaying the
			// raw string would hand it back to the user on a switch to
			// "On date".
			until: isParseableUntil( coerced.until ) ? coerced.until : '',
		};
		rule.monthly_day = normalizeMonthlyDay( rule.monthly_day );

		return { enabled: true, rule, invalidStored: false };
	} catch {
		return {
			enabled: false,
			rule: { ...DEFAULT_RULE },
			invalidStored: false,
		};
	}
}

/**
 * Report whether a candidate rule is one the server would accept.
 *
 * The write-side predicate *is* the read-side one, `isServerValidRule()`,
 * rather than a hand-rolled subset of it: the panel's docblock invariant
 * ("the panel never presents a stored rule the server has rejected") holds
 * only if it never *writes* one either. A subset once let a weekly rule at
 * interval 52 with 730 occurrences through, every value inside the controls'
 * own min/max, while `Rule::is_valid_end_shape()`'s iteration budget rejected
 * it: `Meta::write_recurrence()` cleared all ten mirrors, the projection was
 * deleted, and the editor said nothing.
 *
 * `applyRuleChange()` withholds the write while this is `false`, so the last
 * known-good blob stays on the post, and every withheld state renders an
 * explanatory message beside the control that caused it.
 *
 * @since 0.36.0
 *
 * @param {Object} rule Candidate rule values.
 *
 * @return {boolean} True when `Rule::from_array()` would accept the rule.
 */
function isPersistable( rule ) {
	// The monthly day is the one field the controls can hold as null
	// mid-edit; coerceStoredRule() would read null as 0 and lose the
	// distinction.
	if (
		'monthly' === rule.frequency &&
		'day_of_month' === rule.monthly_mode &&
		! Number.isInteger( rule.monthly_day )
	) {
		return false;
	}

	return isServerValidRule( coerceStoredRule( rule ) );
}

/**
 * Report whether a count rule exceeds the expander's iteration budget.
 *
 * The one rejection an organizer can reach with every control inside its own
 * advertised range, so it needs its own message: without one, the withheld
 * write would be indistinguishable from a saved rule. Mirrors the budget arm
 * of `Rule::is_valid_end_shape()`.
 *
 * @since 0.36.0
 *
 * @param {Object} rule Candidate rule values.
 *
 * @return {boolean} True when the end type is `count` and the budget is exceeded.
 */
function exceedsExpanderBudget( rule ) {
	if ( 'count' !== rule.end_type || 1 > rule.count ) {
		return false;
	}

	const perOccurrence =
		BUDGET_DAYS_PER_FREQUENCY[ rule.frequency ] * rule.interval;

	return ( rule.count * perOccurrence ) + 366 > EXPANDER_MAX_ITERATIONS;
}

/**
 * A settings panel for configuring an event's recurrence rule.
 *
 * Reads and writes the single `gatherpress_recurrence` JSON-string blob on
 * `core/editor` post meta (see `Rule::from_array()` and `Meta::META_KEY` for
 * the authoritative shape). The ten derived `gatherpress_recurrence_*`
 * mirrors are server-computed and read-only, so this panel never reads or
 * writes them. Its source of truth is the blob itself, kept in local state
 * so edits round-trip within a single editing session.
 *
 * Recurrence is refused outright on a fixed-offset timezone (e.g.
 * `UTC+5:30`), detected by the presence of a `:` in the
 * `gatherpress/datetime` store's timezone value. A named tz-database
 * identifier such as `America/New_York` never contains one.
 *
 * @since 0.36.0
 *
 * @return {JSX.Element|null} The recurrence panel, or null when the current
 *                            post type does not support `gatherpress-event-date`.
 */
const RecurrencePanel = () => {
	const isEventDateSupported = usePostTypeSupports( 'gatherpress-event-date' );
	const { editPost } = useDispatch( 'core/editor' );

	const { postId, recurrenceMeta, timezone } = useSelect( ( select ) => {
		return {
			postId: select( 'core/editor' )?.getCurrentPostId(),
			recurrenceMeta:
				select( 'core/editor' )?.getEditedPostAttribute( 'meta' )
					?.gatherpress_recurrence,
			timezone: select( 'gatherpress/datetime' )?.getTimezone(),
		};
	}, [] );

	const [ { enabled, rule, invalidStored }, setState ] = useState( () =>
		parseRecurrenceBlob( recurrenceMeta ),
	);

	// Tracks the blob this component itself last wrote, and which post it
	// wrote it to, so the effect below can tell "the store caught up with our
	// own write" apart from "the entity resolved, or was changed, out from
	// under us". Without this, a panel that mounts before `core/editor`
	// resolves the post entity renders "Repeat: off" for a saved recurring
	// event and never corrects itself: a late entity resolution would clobber
	// the saved state.
	//
	// The post ID is half of the identity because the blob alone is not
	// unique. Gutenberg can change the current post without unmounting this
	// panel, exactly as the occurrences panel in this same directory
	// documents, and two posts can carry byte-identical blobs: the same
	// weekly Tuesday schedule serializes to the same string. Comparing only
	// the string, a switch between two such posts looked like "our own write
	// came back" and the effect returned without resyncing, leaving the
	// previous post's local edits on screen as though they belonged to the
	// new one.
	const lastWrittenRef = useRef( { postId, blob: recurrenceMeta } );

	useEffect( () => {
		// A post change resyncs unconditionally. There is no local state
		// worth preserving across it, and any that survives belongs to a post
		// the panel is no longer editing.
		if (
			postId === lastWrittenRef.current.postId &&
			recurrenceMeta === lastWrittenRef.current.blob
		) {
			return;
		}

		lastWrittenRef.current = { postId, blob: recurrenceMeta };
		setState( parseRecurrenceBlob( recurrenceMeta ) );
	}, [ postId, recurrenceMeta ] );

	const isFixedOffsetTimezone = !! timezone?.includes( ':' );

	/**
	 * Persist panel state to the `gatherpress_recurrence` meta blob.
	 *
	 * @param {boolean} nextEnabled Whether recurrence is turned on.
	 * @param {Object}  nextRule    Rule values to serialize when enabled.
	 *
	 * @return {void}
	 */
	const persist = ( nextEnabled, nextRule ) => {
		const blob = nextEnabled ? JSON.stringify( nextRule ) : '';

		lastWrittenRef.current = { postId, blob };

		editPost( {
			meta: {
				gatherpress_recurrence: blob,
			},
		} );
	};

	/**
	 * Apply a partial rule update, enforcing the shape constraints
	 * `Rule::from_array()` requires structurally rather than by convention:
	 * clamped interval/count, `until`/`count` mutual exclusivity, no stale
	 * weekday or monthly state left over from a previous frequency, and no
	 * write at all while the edit leaves the rule momentarily incomplete
	 * (see `isPersistable()`). Local state still updates so the control
	 * reflects the choice and the validation message it earns, but the last
	 * known-good blob stays on the post until the rule is complete again.
	 *
	 * @param {Object} partial Partial rule field update.
	 *
	 * @return {void}
	 */
	const applyRuleChange = ( partial ) => {
		const merged = { ...rule, ...partial };

		if ( 'frequency' in partial ) {
			if ( 'weekly' !== merged.frequency ) {
				merged.weekdays = [];
			}

			if ( 'monthly' !== merged.frequency ) {
				merged.monthly_mode = DEFAULT_RULE.monthly_mode;
				merged.monthly_day = DEFAULT_RULE.monthly_day;
				merged.monthly_ordinal = DEFAULT_RULE.monthly_ordinal;
				merged.monthly_weekday = DEFAULT_RULE.monthly_weekday;
			}
		}

		if ( 'interval' in partial ) {
			merged.interval = clampInt( partial.interval, 1, 52 );
		}

		if ( 'count' in partial ) {
			merged.count = clampInt( partial.count, 1, 730 );
		}

		if ( 'monthly_day' in partial ) {
			merged.monthly_day = normalizeMonthlyDay( partial.monthly_day );
		}

		if ( 'end_type' in partial ) {
			if ( 'until' === merged.end_type ) {
				merged.count = 0;
			} else if ( 'count' === merged.end_type ) {
				merged.until = '';
			} else {
				merged.until = '';
				merged.count = 0;
			}
		}

		setState( { enabled: true, rule: merged, invalidStored: false } );

		if ( isPersistable( merged ) ) {
			persist( true, merged );
		}
	};

	/**
	 * Handle the "Repeat" toggle. Turning recurrence on always starts from
	 * `DEFAULT_RULE`. There is no separate "save" step, so the default
	 * blob is written immediately, matching every other control in this
	 * panel writing straight to `core/editor` meta on change.
	 *
	 * @param {boolean} value Next toggle state.
	 *
	 * @return {void}
	 */
	const handleToggle = ( value ) => {
		const nextRule = value ? { ...DEFAULT_RULE } : rule;

		setState( { enabled: value, rule: nextRule, invalidStored: false } );
		persist( value, nextRule );
	};

	if ( ! isEventDateSupported ) {
		return null;
	}

	return (
		<PanelRow>
			<div className="gatherpress-recurrence-panel">
				{ isFixedOffsetTimezone && (
					<output>
						{ __(
							'Recurring events require a named timezone (e.g. America/New_York) rather than a fixed UTC offset. Change the timezone in the Date & Time panel to enable repeat.',
							'gatherpress',
						) }
					</output>
				) }
				{ invalidStored && (
					<output>
						{ __(
							'The stored repeat rule for this event is invalid, so the event does not repeat. Turn on Repeat to author a new rule.',
							'gatherpress',
						) }
					</output>
				) }
				<ToggleControl
					label={ __( 'Repeat', 'gatherpress' ) }
					checked={ enabled && ! isFixedOffsetTimezone }
					disabled={ isFixedOffsetTimezone }
					onChange={ handleToggle }
				/>
				{ enabled && ! isFixedOffsetTimezone && (
					<>
						<FrequencyControl
							frequency={ rule.frequency }
							interval={ rule.interval }
							onFrequencyChange={ ( value ) =>
								applyRuleChange( { frequency: value } )
							}
							onIntervalChange={ ( value ) =>
								applyRuleChange( { interval: value } )
							}
						/>
						{ 'weekly' === rule.frequency && (
							<>
								<WeekdaysControl
									weekdays={ rule.weekdays }
									onChange={ ( value ) =>
										applyRuleChange( { weekdays: value } )
									}
								/>
								{ 0 === rule.weekdays.length && (
									<output>
										{ __(
											'Select at least one day of the week.',
											'gatherpress',
										) }
									</output>
								) }
							</>
						) }
						{ 'monthly' === rule.frequency && (
							<>
								<MonthlyControl
									monthlyMode={ rule.monthly_mode }
									monthlyDay={ rule.monthly_day }
									monthlyOrdinal={ rule.monthly_ordinal }
									monthlyWeekday={ rule.monthly_weekday }
									onChange={ applyRuleChange }
								/>
								{ 'day_of_month' === rule.monthly_mode &&
									! Number.isInteger( rule.monthly_day ) && (
									<output>
										{ __(
											'Enter a day of the month between 1 and 31.',
											'gatherpress',
										) }
									</output>
								) }
							</>
						) }
						<EndConditionControl
							endType={ rule.end_type }
							until={ rule.until }
							count={ rule.count }
							onChange={ applyRuleChange }
						/>
						{ 'until' === rule.end_type &&
							! isParseableUntil( rule.until ) && (
							<output>
								{ __(
									'Choose an end date to save this recurrence.',
									'gatherpress',
								) }
							</output>
						) }
						{ 'count' === rule.end_type && ! ( 1 <= rule.count ) && (
							<output>
								{ __(
									'Enter how many times this event repeats.',
									'gatherpress',
								) }
							</output>
						) }
						{ exceedsExpanderBudget( rule ) && (
							<output>
								{ __(
									'That many repeats at this interval covers too long a span. Reduce the number of occurrences or the interval.',
									'gatherpress',
								) }
							</output>
						) }
						<RsvpImpact postId={ postId } rule={ rule } />
						<ApplyScope postId={ postId } />
					</>
				) }
			</div>
		</PanelRow>
	);
};

export default RecurrencePanel;
