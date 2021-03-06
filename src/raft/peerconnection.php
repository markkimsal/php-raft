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

class Raft_PeerConnection {

	public $sockDealer = NULL;
	public $ctx         = NULL;
	public $_identity   = '';

	public function __construct() {
		$this->ctx   = new ZMQContext();
	}

	public function connect($endpoint) {
		$this->endpoint = $endpoint;
		$this->sockDealer  = new ZMQSocket($this->ctx, ZMQ::SOCKET_DEALER);
		$this->sockDealer->setSockOpt(ZMQ::SOCKOPT_IDENTITY, $this->getIdentity());
		$this->_identity = '';
		$this->sockDealer->connect($endpoint);
	}

	public function setIdentity($id) { 
		$this->_identity = $id;
		return $this;
	}

	public function getIdentity() { 
		//  Set random identity to make tracing easier
		if ($this->_identity == '') { 
			$identity =  sprintf ("%04X-%04X", rand(0, 0x10000), rand(0, 0x10000));
			$this->setIdentity( $identity );
		} 
		return $this->_identity;
	}

	public function hb() {
//		$this->sockDealer->send($this->getIdentity(), ZMQ::MODE_SNDMORE);
//		$this->sockDealer->send(NULL, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send("HEARTBEAT");
		//$this->sockDealer->send("HEARTBEAT", ZMQ::MODE_SNDMORE);
		//$this->sockDealer->send("", ZMQ::MODE_SNDMORE);
	}

	public function sendHello($gatewayEp) {
//		$this->sockDealer->send($this->getIdentity(), ZMQ::MODE_SNDMORE);
		$this->sockDealer->send("HELLO", ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($gatewayEp);
	}

	public function sendElection($from, $term, $logIdx, $logTerm) {
//		$this->sockDealer->send($from, ZMQ::MODE_SNDMORE);
//		$this->sockDealer->send(NULL, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send("ELECT", ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($term, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($logIdx, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($logTerm);
	}

/*
	public function sendVote($id, $term, $logTerm) {
		$this->sockDealer->send($this->getIdentity(), ZMQ::MODE_SNDMORE);
		$this->sockDealer->send(NULL, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send("ELECT", ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($term, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($logIdx, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($logTerm);
	}
*/

	public function sendVote($from, $term, $logTerm) {
//		$this->sockDealer->send($from, ZMQ::MODE_SNDMORE);
//		$this->sockDealer->send(NULL, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send("VOTE", ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($term, ZMQ::MODE_SNDMORE);
//		$this->sockDealer->send($logIdx, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($logTerm);
	}

	public function sendHeartbeat($term, $leaderId, $prevIndex, $prevTerm) {
		$this->sockDealer->send("AppendEntries", ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($term, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($leaderId, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($prevIndex, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($prevTerm, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send(null);
	}

	public function sendAppendEntries($ipfrom, $term, $leaderId, $prevIndex, $prevTerm, $entry) {
		$this->sockDealer->send("AppendEntries", ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($ipfrom, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($term, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($leaderId, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($prevIndex, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($prevTerm, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($entry);
	}

	/**
	 * AppendEntriesReply
	 * identity string
	 * term int
	 * success 1|0
	 * matchIndex int
	 */
	public function sendAppendReply($term, $matchIndex) {
//		$this->sockCluster->send($id, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send("AppendEntriesReply", ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($this->getIdentity(), ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($term, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send(1, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($matchIndex);
	}

	public function replyAppendGood($from, $term, $logIdx, $logTerm) {
//		$this->sockDealer->send($from, ZMQ::MODE_SNDMORE);
//		$this->sockDealer->send(NULL, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send("AppendEntriesReply", ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($term, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($logIdx, ZMQ::MODE_SNDMORE);
		$this->sockDealer->send($logTerm);
	}
}
