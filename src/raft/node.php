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

define('HB_INTERVAL', 2);
define('LE_INTERVAL', 1);

include_once(dirname(__FILE__).'/connection.php');
include_once(dirname(__FILE__).'/peerconnection.php');
include_once(dirname(__FILE__).'/msghandler.php');
include_once(dirname(__FILE__).'/../helper/logger.php');
include_once(dirname(__FILE__).'/zmsg.php');

class Raft_Node {

	public $conn          = NULL;
	public $name          = '';
	public $votes         = 0;
	protected $hb_at      = 0.0;
	protected $listPeers  = array();
	protected $handler    = NULL;
	protected $leaderNode = NULL;

	public $currentTerm  = 0;
	public $votedFor     = NULL;
	public $log          = array();

	/**
	 * index of highest log entry known to be committed
	 * (initialized to 0, increases monotonically)
	 */
	public $commitIndex  = 0;
	/**
	 * index of highest log entry applied to state machine
	 * (initialized to 0, increases monotonically)
	 */
	public $lastApplied  = 0;

	public $state        = 'follower';

	//leaders
	public $nextIndex   = array();
	public $matchIndex   = array();

	public function __construct($name='Unknown') {
		$this->name = $name;
		$this->resetHb();

		$this->handler = new Raft_Msghandler();
		$this->conn    = new Raft_Connection();
	}

	public function begin($endpoint) {
		Raft_Logger::log(sprintf("[%s] binding dealer connection to %s ...", $this->name, $endpoint), 'D');
		$this->conn->clusterSocket($endpoint);
	}

	public function addPeer($endpoint) {
		Raft_Logger::log(sprintf("[%s] opening router connection to %s ...", $this->name, $endpoint), 'D');
		$connPeer =  new Raft_PeerConnection();
		$connPeer->connect($endpoint);
		$this->listPeers[$endpoint] = $connPeer;
	}

	public function run() {
		$running = TRUE;

		while($running) {
			$running = $this->poll();
		}
	}


	public function poll() {
//		Raft_Logger::log( sprintf("[%s] polling wire ...", $this->name), 'D');
		$read = $write = array();
		$poll = new ZMQPoll();
		$poll->add($this->conn->sockCluster, ZMQ::POLL_IN);

		$events = $poll->poll($read, $write, HB_INTERVAL * 100 );

		if($events > 0) {
//			Raft_Logger::log( "got events from wire ...", 'D');

			foreach($read as $socket) {
				$zmsg = new Zmsg($socket);
				$zmsg->recv();

				// BACKEND
				//  Handle worker activity on backend
				if($socket === $this->conn->sockCluster) {
//					Raft_Logger::log( sprintf("[%s] Cluster IN %s", $this->name, $zmsg), 'D' );
					$this->handler->onMsg($zmsg, $this);
				}
			}
		}

		$mt = microtime(true);
		if($mt > $this->hb_at) {
		//Raft_Logger::log( sprintf("[%s] %0.4f  %0.4f", $this->name, $mt, $this->hb_at), 'D');
			if ($this->isFollower() || $this->isCandidate()) {
				$this->transitionToCandidate();
				foreach ($this->listPeers as $_p) {
					Raft_Logger::log( sprintf("[%s] sending election to %s", $this->name, $_p->endpoint), 'D');
					$_p->sendElection($this->conn->endpoint,  $this->currentTerm, 0, 0);
				}
			}

			if ($this->isLeader()) {
				$this->pingPeers();
			}
			$this->resetHb();
		}
		return TRUE;
	}

	public function resetHb() {
		$mt = microtime(true);
		if ($this->isLeader()) {
			$this->hb_at = $mt + (LE_INTERVAL - (LE_INTERVAL * rand(0.70, 0.90)));
		} else {
			$this->hb_at = $mt + (HB_INTERVAL - (HB_INTERVAL * rand(0.0, 0.50)));
		}
//		Raft_Logger::log( sprintf("[%s] %0.4f  %0.4f *", $this->name, $mt, $this->hb_at), 'D');
	}

	/**
	 * Return array of nodes joined to this cluster
	 */
	public function getPeers() {
		return $this->listPeers;
	}

	public function findPeer($ep) {
		$ps = $this->getPeers();
		foreach ($ps as $key => $_p) {
			if ($ep == $key) {
				return $_p;
			}
		}
		return FALSE;
	}

	public function pingPeers() {
		foreach ($this->listPeers as $_p) {
			Raft_Logger::log( sprintf("[%s] sending hb to %s", $this->name, $_p->endpoint), 'D');
			$_p->hb();
		}
	}

	public function isFollower() {
		return $this->state == 'follower';
	}

	public function isLeader() {
		return $this->state == 'leader';
	}

	public function isCandidate() {
		return $this->state == 'candidate';
	}

	public function setLeaderNode($ep) {
		return $this->leaderNode = $ep;
	}

	public function transitionToCandidate() {
		$this->state = 'candidate';
		$this->currentTerm++;
		$this->votes = 0;
	}

	public function transitionToLeader() {
		$this->state = 'leader';
		$this->votes = 0;
	}
}
