<?php
declare(ENCODING = 'utf-8');
namespace Libtextcat;

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
 * Fingerprint functions
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class Textcat {

	const MINDOCSIZE = 25;
	const MAXNGRAMSIZE = 5;
	const MAXNGRAMS = 400;
	const MAXOUTOFPLACE = 400;
	const MAXSCORE = 2147483647;
	const THRESHOLDVALUE = 1.03;

	/**
	 * Array of fingerprints indexed by category name
	 * @var array
	 */
	protected $categories;

	/**
	 * Set of languages with UTF-8 encoded fingerprints (in Resources/Private)
	 * @var array
	 */
	protected $availableCategories = array(
			'english' => 'en',
			'german' => 'de',
			'french' => 'fr',
			'spanish' => 'es',
			'italian' => 'it',
			'portuguese' => 'pt',
			'danish' => 'da',
			'swedish' => 'sv',
			'norwegian' => 'no',
			'finnish' => 'fi'
		);

	/**
	 * Constructor
	 *
	 * Initializes the categories from static .lm files.
	 *
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function __construct() {
		$this->categories = array();
		foreach ($this->availableCategories as $categoryName => $language) {
			$this->categories[$categoryName] = $this->readFingerprint($categoryName);
		}
	}

	/**
	 * Classify the given text into the configured categories by
	 * doing a "N-Gram-Based Text Categorization" (http://odur.let.rug.nl/~vannoord/TextCat/)
	 * inspired by libtextcat. The text has to be in UTF-8 encoding!
	 *
	 * @param string $text The text to classify in UTF-8 encoding
	 * @return string|boolean The classified language as ISO 2-letter code or FALSE if no classification could be made
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function classify($text) {
		$fingerprint = self::create($text, self::MAXNGRAMS);
		if ($fingerprint === FALSE) {
			return FALSE;
		}
		$minscore = self::MAXSCORE;
		$threshold = $minscore;
		$candidates = array();
		foreach ($this->categories as $name => $category) {
			$score = self::compare($category, $fingerprint, $threshold);
			$candidates[$name] = $score;
			if ($score < $minscore) {
				$minscore = $score;
				$threshold = intval($score * self::THRESHOLDVALUE);
			}
		}
		asort($candidates);
		return $this->availableCategories[key($candidates)];
	}

	/**
	 * Parse a fingerprint .lm file
	 *
	 * @param string $category
	 * @return array
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	protected function readFingerprint($category) {
		$fingerprint = array();

		if (file_exists(__DIR__ . '/../Resources/Private/' . $category . '.lm')) {
			$filename = __DIR__ . '/../Resources/Private/' . $category . '.lm';
		} else {
			$filename = 'resource://Libtextcat/Private/' . $category . '.lm';
		}
		$fp = fopen($filename, 'r');
		for ($rank = 1; ($row = fgets($fp)) !== FALSE; $rank++) {
			list($ngram) = explode("\t", $row, 2);
			$fingerprint[$ngram] = $rank;
		}
		fclose($fp);
		return $fingerprint;
	}

	/**
	 * Compare the fingerprint of the given category against the unknown
	 * fingerprint.
	 *
	 * @param array $category
	 * @param array $unknown
	 * @param integer $cutoff
	 * @return integer
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	static public function compare(&$category, &$unknown, $cutoff) {
		$sum = 0;
		$i = 0;
		foreach ($unknown as $str => $score) {
			if (isset($category[$str])) {
				$sum += abs($category[$str] - $i);
			} else {
				$sum += self::MAXOUTOFPLACE;
			}
			if ($sum > $cutoff) {
				return self::MAXSCORE;
			}
			$i++;
		}
		return $sum;
	}

	/**
	 * Create a fingerprint:
	 * - record the frequency of each unique n-gram in a hash table
	 * - take the most frequent n-grams
	 * - sort them alphabetically, recording their relative rank
	 *
	 * @param string $buffer
	 * @return array|false Fingerprint as array indexed by ngrams with score
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	static public function create($buffer, $maxngrams) {
		$size = mb_strlen($buffer, 'UTF-8');
		if ($size < self::MINDOCSIZE) {
			return FALSE;
		}

			// Throw out all invalid chars
		$tmp = preg_replace('/[\d\s]+/', '_', $buffer);

			// Create a hash table containing n-gram counts
		$table = self::createngramtable($tmp);

			// Sort n-grams alphabetically, for easy comparison
		arsort($table);

			// Cut-off table at MAXNGRAMS count
		return array_slice($table, 0, min(count($table), self::MAXNGRAMS));
	}

	/**
	 * Create a table of ngrams with count (frequency) by iterating
	 * over all positions in buffer and indexing each ngram from
	 * 1 to MAXNGRAMSIZE.
	 *
	 * @param string $buffer
	 * @return array
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	static protected function createngramtable($buffer) {
		$table = array();

			// Get all n-grams where 1<=n<=MAXNGRAMSIZE. Allow underscores only at borders.
		$size = mb_strlen($buffer, 'UTF-8');
		for ($j = 0; $j < $size - 1; $j++) {
			for ($i = 1; $i <= self::MAXNGRAMSIZE; $i++) {
				if ($j + $i > $size) break;
				$p = mb_substr($buffer, $j, $i, 'UTF-8');
				if (!isset($table[$p])) {
					$table[$p] = 1;
				} else {
					$table[$p]++;
				}
				if ($i > 1 && mb_substr($p, -1, 1, 'UTF-8') === '_') break;
			}
		}
		return $table;
	}

}
?>