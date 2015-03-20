<?php

class Raft_Peernode {

	public $endpoint   = '';
	public $nextIndex  = 0;
	public $matchIndex = 0;
	public $conn       = NULL;

	public function __construct($ep) {
		$this->endpoint = $ep;
	}

	public function connect($conn=NULL) {
		if ($conn === NULL) {
			$this->conn = new Raft_PeerConnection();
		} else {
			$this->conn = $conn;
		}
		$this->conn->connect($this->endpoint);
	}

	/**
	 * Set the next index to one more than the 
	 * leader's commitIndex
	 */
	public function setNextIndex($leaderNode) {
		$this->nextIndex = $leaderNode->commitIndex+1;
	}

	public function sayHello($sourceEndpoint) {
		$this->conn->sendHello($sourceEndpoint);
	}
}
