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

	protected $logEntry     = NULL;
	protected $logTerm      = NULL;
	protected $commitIndex  = 0;
	protected $pendingEntry = NULL;
	protected $pendingTerm  = NULL;

	public function __construct() {
		$this->logEntry    = new SplFixedArray(1000);
		$this->logTerm     = new SplFixedArray(1000);
	}

	public function getPendingEntry() {
		return $this->pendingEntry ;
	}

	public function getPendingTerm() {
		return $this->pendingTerm  ;
	}

	public function getCommitIndex() {
		return $this->logEntry->key();
	}

	public function appendEntry($entry, $term) {
		$this->pendingEntry = $entry;
		$this->pendingTerm  = $term;
	}

	public function commitEntry() {
		$idx = $this->getCommitIndex();
		$this->logEntry[$idx]   = $this->pendingEntry;
		$this->logTerm[$idx]    = $this->pendingTerm;
		$this->logEntry->next();
		$this->logTerm->next();

		$this->pendingEntry = NULL;
		$this->pendingTerm  = NULL;
	}

	public function getTermForIndex($i) {
		return $this->logTerm[$i];
	}

	public function debugLog() {
		$log = '';
		for ($x=0; $x < 20; $x++) {
			if ($this->logEntry->offsetExists($x)) {
				$log .= $this->logTerm->offsetGet($x);
				$log .= '|';
			}
		}
		if ($this->pendingEntry != NULL) {
				$log .= $this->pendingTerm;
				$log .= '.';
		}
		Raft_Logger::log($log);
		return $log;
	}
}
