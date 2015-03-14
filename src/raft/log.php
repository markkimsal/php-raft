<?php
/**
 * Raft consensus algorithm
 * 
 * Copyright 2015 Mark Kimsal
 * 
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use, copy,
 * modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

/**
 * Raft_Log
 * Represents a lot of FSM actions
 */
class Raft_Log {

	protected $logEntry         = NULL;
	protected $logTerm          = NULL;
	protected $commitIndex      = 0;
	protected $uncommittedIndex = 0;

	public function __construct() {
		$this->logEntry         = new SplFixedArray(1000);
		$this->logTerm          = new SplFixedArray(1000);
	}

/*
	public function getPendingEntry($key) {
		return $this->listPendingEntry[$key];
	}

	public function getPendingTerm($key) {
		return $this->listPendingTerm[$key];
	}
*/

	public function getCommitIndex() {
		return $this->logEntry->key();
	}

	public function getUncommittedIndex() {
		return $this->uncommittedIndex;
	}


	public function appendEntry($entry, $term) {

		$idx = $this->uncommittedIndex;
		$this->logEntry[$idx]   = $entry;
		$this->logTerm[$idx]    = $term;
		//increment after returning;
		return $this->uncommittedIndex++;
	}

	public function commitIndex($idx) {
		$cidx = $this->getCommitIndex();
		if ($idx == ++$cidx) {
			$this->logEntry->next();
			$this->logTerm->next();
		}
	}

	public function getTermForIndex($i) {
		return (int)$this->logTerm[$i];
	}

	public function getEntryForIndex($i) {
		return $this->logEntry[$i];
	}


	public function debugLog() {
		$log = '';
		for ($x=0; $x < 20; $x++) {
			if ($this->logEntry->offsetExists($x)) {
				$log .= $this->logTerm->offsetGet($x);
				if ($x < $this->uncommittedIndex) {
					$log .= '.';
				} else {
					$log .= '|';
				}
			}
		}
/*
		if ($this->pendingEntry != NULL) {
				$log .= $this->pendingTerm;
				$log .= '.';
		}
*/
		Raft_Logger::log($log);
		return $log;
	}

	public function getCountPending() {
		return $this->uncommittedIndex - $this->getCommitIndex();
	}

}
