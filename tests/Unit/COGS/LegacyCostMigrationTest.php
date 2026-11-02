<?php
/**
 * Unit tests for the legacy cost migration's per-product decision.
 *
 * @package Profitly
 */

declare( strict_types=1 );

namespace Profitly\Tests\Unit\COGS;

use PHPUnit\Framework\TestCase;
use Profitly\COGS\LegacyCostMigration;

/**
 * @covers \Profitly\COGS\LegacyCostMigration
 */
final class LegacyCostMigrationTest extends TestCase {

	/**
	 * @dataProvider decision_provider
	 *
	 * @param string     $legacy   Legacy cost.
	 * @param float|null $core     Core cost.
	 * @param string     $expected Expected decision.
	 */
	public function test_decide( string $legacy, ?float $core, string $expected ): void {
		$this->assertSame( $expected, LegacyCostMigration::decide( $legacy, $core ) );
	}

	/**
	 * @return array<string, array{0: string, 1: float|null, 2: string}>
	 */
	public function decision_provider(): array {
		return array(
			"legacy '0' means no cost entered"  => array( '0', null, LegacyCostMigration::DECISION_SKIP ),
			"legacy '0.00'"                     => array( '0.00', null, LegacyCostMigration::DECISION_SKIP ),
			"legacy '0' never conflicts"        => array( '0', 10.0, LegacyCostMigration::DECISION_SKIP ),
			'legacy empty'                      => array( '', null, LegacyCostMigration::DECISION_SKIP ),
			'legacy non-numeric'                => array( 'abc', null, LegacyCostMigration::DECISION_SKIP ),
			'legacy negative'                   => array( '-5', null, LegacyCostMigration::DECISION_SKIP ),
			'legacy exponent'                   => array( '1e3', null, LegacyCostMigration::DECISION_SKIP ),
			'core null is copied'               => array( '10', null, LegacyCostMigration::DECISION_COPY ),
			'same whole number'                 => array( '10', 10.0, LegacyCostMigration::DECISION_SAME ),
			'same with trailing zeros'          => array( '10.00', 10.0, LegacyCostMigration::DECISION_SAME ),
			'same with decimals'                => array( '19.99', 19.99, LegacyCostMigration::DECISION_SAME ),
			'different is a conflict'           => array( '10', 12.5, LegacyCostMigration::DECISION_CONFLICT ),
			'sub-cent difference is a conflict' => array( '10.001', 10.002, LegacyCostMigration::DECISION_CONFLICT ),
			'core zero is set, so conflict'     => array( '10', 0.0, LegacyCostMigration::DECISION_CONFLICT ),
		);
	}
}
