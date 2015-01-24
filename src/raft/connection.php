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

class Raft_Connection {

	public $sockCluster = NULL;
	public $ctx         = NULL;
	public $_identity   = '';
	public $endpoint    = '';


	public function __construct() {
		$this->ctx   = new ZMQContext();
	}

	public function clusterSocket($endpoint) {
		$this->endpoint = $endpoint;
		$this->sockCluster  = new ZMQSocket($this->ctx, ZMQ::SOCKET_DEALER);
		$this->_identity = '';
		$this->sockCluster->setSockOpt(ZMQ::SOCKOPT_IDENTITY, $this->getIdentity());
		$this->sockCluster->bind($endpoint);
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

	public function connectCluster($endpoint) {
		$this->sockCluster->connect($endpoint);
	}

	public function hb() {
		$this->sockCluster->send($this->getIdentity(), ZMQ::MODE_SNDMORE);
		$this->sockCluster->send(NULL, ZMQ::MODE_SNDMORE);
		$this->sockCluster->send("HEARTBEAT");
		//$this->sockCluster->send("HEARTBEAT", ZMQ::MODE_SNDMORE);
		//$this->sockCluster->send("", ZMQ::MODE_SNDMORE);
	}


	public function sendVote($id, $term, $logTerm) {
		$this->sockCluster->send($id, ZMQ::MODE_SNDMORE);
		$this->sockCluster->send(NULL, ZMQ::MODE_SNDMORE);
		$this->sockCluster->send("VOTE", ZMQ::MODE_SNDMORE);
		$this->sockCluster->send($term, ZMQ::MODE_SNDMORE);
		$this->sockCluster->send($logTerm);
	}
}
