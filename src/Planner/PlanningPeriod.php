<?php
/**
 * Forward-looking planning periods.
 *
 * @package Profitly
 */

declare( strict_types=1 );

namespace Profitly\Planner;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the planning period the merchant wants to plan for.
 *
 * {@see \Profitly\Reports\DateRangeFilter} answers "which past window am I
 * reporting on?" and always ends at now; this answers the opposite question —
 * "which upcoming window am I planning for?" — so it needs its own (small)
 * resolver. Both compute in the site timezone via {@see wp_timezone()}, and both
 * read their key from the query string without a nonce because selecting a window
 * changes no state.
 */
final class PlanningPeriod {

	/**
	 * The valid period keys accepted from the request.
	 */
	public const PERIODS = array( 'this_month', 'next_month', 'this_quarter', 'custom' );

	/**
	 * The default period when none/invalid is supplied.
	 */
	public const DEFAULT_PERIOD = 'this_month';

	/**
	 * Longest custom period accepted, in days (roughly three years).
	 */
	private const MAX_CUSTOM_DAYS = 1096;

	/**
	 * Resolve the requested planning period.
	 *
	 * @return array{key: string, label: string, start: DateTimeImmutable, end: DateTimeImmutable, days: int}
	 */
	public static function get_current(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only period selector, no state change.
		$key = isset( $_GET['period'] ) ? sanitize_key( wp_unslash( $_GET['period'] ) ) : '';

		$custom_start = isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : '';
		$custom_end   = isset( $_GET['end'] ) ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $key, self::PERIODS, true ) ) {
			$key = self::DEFAULT_PERIOD;
		}

		return self::get_period( $key, $custom_start, $custom_end );
	}

	/**
	 * Build the window for a specific period key.
	 *
	 * @param string $key          One of self::PERIODS (anything else falls back to the default).
	 * @param string $custom_start Custom range start as `Y-m-d` (only used for 'custom').
	 * @param string $custom_end   Custom range end as `Y-m-d` (only used for 'custom').
	 * @return array{key: string, label: string, start: DateTimeImmutable, end: DateTimeImmutable, days: int}
	 */
	public static function get_period( string $key, string $custom_start = '', string $custom_end = '' ): array {
		if ( ! in_array( $key, self::PERIODS, true ) ) {
			$key = self::DEFAULT_PERIOD;
		}

		$now = ( new DateTimeImmutable( 'now', wp_timezone() ) )->setTime( 0, 0, 0 );

		switch ( $key ) {
			case 'next_month':
				$start = $now->modify( 'first day of next month' );
				$end   = $start->modify( 'last day of this month' );
				break;

			case 'this_quarter':
				$quarter_start_month = ( ( (int) ceil( (int) $now->format( 'n' ) / 3 ) - 1 ) * 3 ) + 1;
				$start               = $now->setDate( (int) $now->format( 'Y' ), $quarter_start_month, 1 );
				$end                 = $start->modify( '+2 months' )->modify( 'last day of this month' );
				break;

			case 'custom':
				$start = self::parse_date( $custom_start, $now );
				$end   = self::parse_date( $custom_end, $start );

				if ( $end < $start ) {
					$end = $start;
				}
				break;

			case 'this_month':
			default:
				$start = $now->modify( 'first day of this month' );
				$end   = $start->modify( 'last day of this month' );
				break;
		}

		return array(
			'key'   => $key,
			'label' => self::label_for( $key ),
			'start' => $start,
			'end'   => $end,
			'days'  => self::days_between( $start, $end ),
		);
	}

	/**
	 * Human label for a period key.
	 *
	 * @param string $key One of self::PERIODS.
	 * @return string Translated label.
	 */
	public static function label_for( string $key ): string {
		switch ( $key ) {
			case 'next_month':
				return __( 'Next month', 'profitly' );

			case 'this_quarter':
				return __( 'This quarter', 'profitly' );

			case 'custom':
				return __( 'Custom range', 'profitly' );

			case 'this_month':
			default:
				return __( 'This month', 'profitly' );
		}
	}

	/**
	 * Count the calendar days in a window, inclusive of both endpoints.
	 *
	 * A period running 1–31 March is 31 days of selling, not 30, so both ends count.
	 *
	 * @param DateTimeImmutable $start Window start.
	 * @param DateTimeImmutable $end   Window end.
	 * @return int Number of days, at least 1 and at most MAX_CUSTOM_DAYS.
	 */
	public static function days_between( DateTimeImmutable $start, DateTimeImmutable $end ): int {
		$diff = $start->setTime( 0, 0, 0 )->diff( $end->setTime( 0, 0, 0 ) );

		return min( self::MAX_CUSTOM_DAYS, max( 1, (int) $diff->days + 1 ) );
	}

	/**
	 * Parse a `Y-m-d` string into a site-timezone midnight, falling back when invalid.
	 *
	 * @param string            $value    Raw date string.
	 * @param DateTimeImmutable $fallback Value to return when the input is unusable.
	 * @return DateTimeImmutable Parsed date.
	 */
	private static function parse_date( string $value, DateTimeImmutable $fallback ): DateTimeImmutable {
		$parsed = DateTimeImmutable::createFromFormat( 'Y-m-d', $value, wp_timezone() );

		if ( false === $parsed || $parsed->format( 'Y-m-d' ) !== $value ) {
			return $fallback;
		}

		return $parsed->setTime( 0, 0, 0 );
	}
}
