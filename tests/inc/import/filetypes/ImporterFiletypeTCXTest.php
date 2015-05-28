<?php

use Runalyze\Configuration;

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.0 on 2013-04-10 at 17:21:17.
 */
class ImporterFiletypeTCXTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var ImporterFiletypeTCX
	 */
	protected $object;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() {
		$this->object = new ImporterFiletypeTCX;
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown() { }

	/**
	 * Test: empty string
	 */
	public function testEmptyString() {
		$this->object->parseString('');

		$this->assertTrue( $this->object->failed() );
		$this->assertEmpty( $this->object->objects() );
		$this->assertNotEmpty( $this->object->getErrors() );
	}

	/**
	 * Test: incorrect xml-file 
	 */
	public function test_notGarmin() {
		$this->object->parseString('<any><xml><file></file></xml></any>');

		$this->assertTrue( $this->object->failed() );
		$this->assertEmpty( $this->object->objects() );
		$this->assertNotEmpty( $this->object->getErrors() );
	}

	/**
	 * Test: standard file
	 * Filename: "Standard.tcx" 
	 */
	public function test_generalFile() {
		$this->object->parseFile('../tests/testfiles/tcx/Standard.tcx');

		$this->assertFalse( $this->object->hasMultipleTrainings() );
		$this->assertFalse( $this->object->failed() );

		$this->assertEquals( 6523, $this->object->object()->getTimeInSeconds(), '', 30);
		$this->assertEquals( 7200 - 8*60 - 21, $this->object->object()->getElapsedTime() );
		$this->assertTrue( $this->object->object()->hasElapsedTime() );

		$this->assertEquals( 22.224, $this->object->object()->getDistance(), '', 0.1);
		$this->assertEquals( 1646, $this->object->object()->getCalories(), '', 10);
		$this->assertEquals( 145, $this->object->object()->getPulseAvg(), '', 2);
		$this->assertEquals( 172, $this->object->object()->getPulseMax(), '', 2);
		$this->assertTrue( $this->object->object()->hasArrayAltitude() );
		$this->assertTrue( $this->object->object()->hasArrayDistance() );
		$this->assertTrue( $this->object->object()->hasArrayHeartrate() );
		$this->assertTrue( $this->object->object()->hasArrayLatitude() );
		$this->assertTrue( $this->object->object()->hasArrayLongitude() );
		$this->assertTrue( $this->object->object()->hasArrayPace() );
		$this->assertTrue( $this->object->object()->hasArrayTime() );

		$this->assertEquals( 1, $this->object->object()->Sport()->id() );
		// TODO: missing values
	}

	/**
	 * Test: swimming
	 * Filename: "Swim-without-time_by-Timekiller.tcx" 
	 */
	public function test_swimTraining() {
		$this->object->parseFile('../tests/testfiles/tcx/Swim-without-time_by-Timekiller.tcx');

		$this->assertTrue( !$this->object->failed() );

		$this->assertEquals( 2100, $this->object->object()->getTimeInSeconds(), '', 30);
		$this->assertEquals( 2100, $this->object->object()->getElapsedTime(), '', 30);
		//$this->assertEquals( 5, $this->object->object()->Sport()->id() ); // "Other" is in the file

		$this->assertEquals( "Forerunner 310XT-000", $this->object->object()->getCreatorDetails() );
		$this->assertEquals( "2012-04-13T11:51:59Z", $this->object->object()->getActivityId() );
	}

	/**
	 * Test: indoor file
	 * Filename: "Indoor-Training.tcx" 
	 */
	public function test_indoorTraining() {
		$this->object->parseFile('../tests/testfiles/tcx/Indoor-Training.tcx');

		$this->assertFalse( $this->object->failed() );
		$this->assertEquals( 7204, $this->object->object()->getTimeInSeconds(), '', 70);
		$this->assertEquals( 7204, $this->object->object()->getElapsedTime() );
		$this->assertEquals( 122, $this->object->object()->getPulseAvg(), '', 2);
		$this->assertEquals( 149, $this->object->object()->getPulseMax(), '', 2);
		//$this->assertEquals( 2, $this->object->object()->Sport()->id() );
	}

	/**
	 * Test: multisport file
	 * Filename: "Multisport.tcx" 
	 */
	public function test_multisport() {
		$this->object->parseFile('../tests/testfiles/tcx/Multisport.tcx');

		$this->assertFalse( $this->object->failed() );
		$this->assertTrue( $this->object->hasMultipleTrainings() );
		$this->assertEquals( 3, $this->object->numberOfTrainings() );

		// Activity 1
		$this->assertEquals( mktime(18, 14, 21, 4, 18, 2013), $this->object->object(0)->getTimestamp() );
		//$this->assertNotEquals( Configuration::General()->runningSport(), $this->object->object(0)->get('sportid') );
		$this->assertEquals( 494, $this->object->object(0)->getTimeInSeconds(), '', 20 );
		$this->assertEquals( 2.355, $this->object->object(0)->getDistance(), '', 0.1 );

		// Activity 2
		$this->assertEquals( mktime(18, 24, 12, 4, 18, 2013), $this->object->object(1)->getTimestamp() );
		//$this->assertEquals( Configuration::General()->runningSport(), $this->object->object(1)->get('sportid') );
		$this->assertEquals( 3571, $this->object->object(1)->getTimeInSeconds(), '', 30 );
		$this->assertEquals( 11.46, $this->object->object(1)->getDistance(), '', 0.1 );

		// Activity 3
		$this->assertEquals( mktime(19, 35, 46, 4, 18, 2013), $this->object->object(2)->getTimestamp() );
		//$this->assertNotEquals( Configuration::General()->runningSport(), $this->object->object(2)->get('sportid') );
		$this->assertEquals( 420, $this->object->object(2)->getTimeInSeconds(), '', 10 );
		$this->assertEquals( 2.355, $this->object->object(2)->getDistance(), '', 0.1 );
	}

	/**
	 * Test: dakota file
	 * Filename: "Dakota.tcx" 
	 */
	public function test_dakota() {
		$this->object->parseFile('../tests/testfiles/tcx/Dakota.tcx');

		$this->assertFalse( $this->object->hasMultipleTrainings() );
		$this->assertFalse( $this->object->failed() );

		// Very slow parts (2m in 30s ...), not a good example
		//$this->assertEquals( 1371, $this->object->object()->getTimeInSeconds(), '', 30);
		$this->assertEquals( 2.34, $this->object->object()->getDistance(), '', 0.1);
		$this->assertTrue( $this->object->object()->hasArrayAltitude() );
		$this->assertTrue( $this->object->object()->hasArrayDistance() );
		$this->assertTrue( $this->object->object()->hasArrayLatitude() );
		$this->assertTrue( $this->object->object()->hasArrayLongitude() );
		$this->assertTrue( $this->object->object()->hasArrayPace() );
		$this->assertTrue( $this->object->object()->hasArrayTime() );
		$this->assertFalse( $this->object->object()->hasArrayPower() );

		//$this->assertNotEquals( 1, $this->object->object()->Sport()->id() );
		// TODO: missing values
	}

	/**
	 * Test: watt extension without namespace (minimized example)
	 * Filename: watt-extension-without-namespace.tcx
	 */
	public function test_wattExtensionWithoutNamespace() {
		$this->object->parseFile('../tests/testfiles/tcx/watt-extension-without-namespace.tcx');

		$this->assertFalse( $this->object->hasMultipleTrainings() );
		$this->assertFalse( $this->object->failed() );

		$this->assertTrue( $this->object->object()->hasArrayPower() );
		$this->assertEquals(
				array(0, 10, 20, 30, 41, 41, 41, 117, 155, 192, 182, 188, 186, 182, 178, 181, 180, 179, 178, 179, 180, 182, 181, 180, 180, 178),
				$this->object->object()->getArrayPower()
		);
	}

	/**
	 * Test: DistanceMeters are missing
	 * Filename: "missing-distances.tcx" 
	 */
	public function testMissingDistancePoints() {
		$this->object->parseFile('../tests/testfiles/tcx/missing-distances.tcx');

		$this->assertFalse( $this->object->hasMultipleTrainings() );
		$this->assertFalse( $this->object->failed() );

		$DistanceArray = $this->object->object()->getArrayDistance();
		foreach ($DistanceArray as $i => $km) {
			if ($i > 0) {
				$this->assertTrue( $km >= $DistanceArray[$i-1], 'Distance is decreasing');
			}
		}
	}

	/**
	 * Test: Runtastic file - don't look for pauses!
	 * Filename: "Runtastic.tcx" 
	 */
	public function testRuntasticFile() {
		$this->object->parseFile('../tests/testfiles/tcx/Runtastic.tcx');

		$this->assertFalse( $this->object->hasMultipleTrainings() );
		$this->assertFalse( $this->object->failed() );

		$this->assertEquals( 61, $this->object->object()->getTimeInSeconds(), '', 5);
		$this->assertEquals( 0.113, $this->object->object()->getDistance(), '', 0.01);
		$this->assertTrue( $this->object->object()->hasArrayAltitude() );
		$this->assertTrue( $this->object->object()->hasArrayLatitude() );
		$this->assertTrue( $this->object->object()->hasArrayLongitude() );
		$this->assertTrue( $this->object->object()->hasArrayHeartrate() );

		$this->assertEquals(
			array(23, 25, 27, 31, 32, 35, 37, 39, 45, 50),
			array_slice($this->object->object()->getArrayTime(), 10, 10)
		);
		$this->assertEquals(
			array(0.0, 0.0, 0.0, 0.052, 0.052, 0.052, 0.052, 0.052, 0.071, 0.085),
			array_slice($this->object->object()->getArrayDistance(), 10, 10)
		);
		$this->assertEquals(
			array(596, 596, 596, 596, 737, 737, 737, 737, 737, 357),
			array_slice($this->object->object()->getArrayPace(), 10, 10)
		);
	}

}