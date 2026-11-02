<?php
/**
 * Unit tests for product cost resolution.
 *
 * @package Profitly
 */

declare( strict_types=1 );

namespace Profitly\Tests\Unit\COGS;

use PHPUnit\Framework\TestCase;
use Profitly\COGS\ProductCOGS;

/**
 * @covers \Profitly\COGS\ProductCOGS
 */
final class ProductCOGSTest extends TestCase {

	/**
	 * @dataProvider source_provider
	 *
	 * @param float|null  $own         Own cost.
	 * @param float|null  $parent_cost Parent cost.
	 * @param bool        $additive    Additive flag.
	 * @param string|null $expected    Expected source.
	 */
	public function test_resolve_cost_source( ?float $own, ?float $parent_cost, bool $additive, ?string $expected ): void {
		$this->assertSame( $expected, ProductCOGS::resolve_cost_source( $own, $parent_cost, $additive ) );
	}

	/**
	 * @return array<string, array{0: float|null, 1: float|null, 2: bool, 3: string|null}>
	 */
	public function source_provider(): array {
		return array(
			'own set, no parent'            => array( 10.0, null, false, ProductCOGS::SOURCE_OWN ),
			'own set beats parent'          => array( 10.0, 5.0, false, ProductCOGS::SOURCE_OWN ),
			'own null falls back to parent' => array( null, 5.0, false, ProductCOGS::SOURCE_PARENT ),
			'both null'                     => array( null, null, false, null ),
			'zero is a real own cost'       => array( 0.0, 5.0, false, ProductCOGS::SOURCE_OWN ),
			'additive with both set'        => array( 10.0, 5.0, true, ProductCOGS::SOURCE_BOTH ),
			'additive with only own set'    => array( 10.0, null, true, ProductCOGS::SOURCE_OWN ),
			'additive with only parent set' => array( null, 5.0, true, ProductCOGS::SOURCE_PARENT ),
			'additive with neither set'     => array( null, null, true, null ),
		);
	}
}
