<?php
declare(ENCODING = 'utf-8');
namespace F3\Libtextcat\Tests\Unit;

/*                                                                        *
 * This script belongs to the FLOW3 package "Libtextcat".          *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License as published by the *
 * Free Software Foundation, either version 3 of the License, or (at your *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser       *
 * General Public License for more details.                               *
 *                                                                        *
 * You should have received a copy of the GNU Lesser General Public       *
 * License along with the script.                                         *
 * If not, see http://www.gnu.org/licenses/lgpl.html                      *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * Fingerprint test
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class FingerprintTest extends \F3\FLOW3\Tests\UnitTestCase {

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function textIsSplittedAndNgramsRanked() {
		$text = file_get_contents(__DIR__ . '/Fixtures/german.txt');
		$ranking = \F3\Libtextcat\Textcat::create($text, 400);
		$this->assertEquals(400, count($ranking));
		$this->assertEquals('_', key($ranking));
	}

	/**
	 * @test
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function classifyDetectsLanguages() {
		$textcat = new \F3\Libtextcat\Textcat();

		$text = file_get_contents(__DIR__ . '/Fixtures/german.txt');
		$this->assertEquals('de', $textcat->classify($text));

		$text = file_get_contents(__DIR__ . '/Fixtures/english.txt');
		$this->assertEquals('en', $textcat->classify($text));

		$text = file_get_contents(__DIR__ . '/Fixtures/french.txt');
		$this->assertEquals('fr', $textcat->classify($text));
	}

}
?>