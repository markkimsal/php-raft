<?php


class Raft_Server {

use \Evenement\EventEmitterTrait;

	public $conn         = NULL;
	public $name         = '';
	public $endpoint     = '';
	protected $listPeers = array();
	protected $listPeersByZmqId = array();

	public function __construct($name='Unknown') {
		$this->name = $name;
		$this->conn = new Raft_Connection();
	}

	public function joinCluster($endpoint) {
		$this->endpoint = $endpoint;
		$this->conn->clusterSocket($endpoint);
	}

	public function poll() {
//		Raft_Logger::log( sprintf("[%s] polling wire ...", $this->name), 'D');
		$read = $write = array();
		$poll = new ZMQPoll();
		$poll->add($this->conn->sockCluster, ZMQ::POLL_IN);
		foreach ($this->listPeers as $_p) {
			$poll->add($_p->conn->sockDealer, ZMQ::POLL_IN);
		}

		//if this interval is over HB_INTERVAL * 100 then it interferes
		//with the timer jitter and all nodes respond to timeouts at the
		//same time.
		$events = $poll->poll($read, $write, HB_INTERVAL * 50 );

		if($events > 0) {
			foreach($read as $socket) {
				$zmsg = new Zmsg($socket);
				$zmsg->recv();

				// BACKEND
				//  Handle worker activity on backend
				if($socket === $this->conn->sockCluster) {
					$this->onMsg($zmsg, $socket);
//					$this->handler->onMsg($zmsg, $this);
				} else {
//					Raft_Logger::log( sprintf("[%s] Cluster IN %s", $this->name, $zmsg), 'D' );
					$this->onReply($zmsg, $socket);
				}
			}
		}
		return TRUE;
	}

	//TODO make this on reply work
	public function onReply($zmsg, $socket) {
		$from     = $zmsg->pop();
		$type     = $zmsg->pop();
		if ($type == 'VOTE') {
			Raft_Logger::log( sprintf('[%s] got vote from  %s', $node->name, $from), 'E');
			$this->emit('recvVote', array($from));
		}

		if ($type == 'AppendEntriesReply') {
			$from       = $zmsg->pop();
			$term       = (int)$zmsg->pop();
			$success    = (int)$zmsg->pop();
			$matchIndex = $zmsg->pop();

//TODO this isn't the right comparison
			if ($matchIndex < $node->log->getCommitIndex()) {
				$p = $node->findPeer($from);
				if (!$p) {
					Raft_Logger::log( sprintf('[%s] cannot find peer %s', $node->name, $from), 'E');
					return;
				}

				Raft_Logger::log( sprintf('[%s] leader committing log', $node->name), 'D');
				$node->log->commitIndex($matchIndex);
				$node->log->debugLog();
				$p->matchIndex = (int)$matchIndex;
				$p->nextIndex  = (int)$matchIndex+1;
			}
		}
	}

	public function onMsg($zmsg, $socket) {
		$from     = $zmsg->pop();
		//$null   = $zmsg->pop();
		$type     = $zmsg->pop();

		if ($type == 'REQUEST') {
			$this->emit('clientRequest', array($zmsg->pop(), $from));
			return;
		}

		if ($type == 'VOTE') {
			$this->emit('recvVote', array($from));
		}

		if ($type == 'HELLO') {
			$gatewayEp = $zmsg->pop();
			$this->listPeersByZmqId[$from] = $gatewayEp;
			//$this->emit('clientHello', array($zmsg->pop(), $from));
			return;
		}

		if ($type == 'AppendEntries') {
			$term = (int)$zmsg->pop();
			$commitIdx = -1;
			$args = array(
				$term,
				$zmsg->pop(),
				$zmsg->pop(),
				(int)$zmsg->pop(),
				$zmsg->pop(),
				$zmsg->pop()
			);
			if ($zmsg->parts()) {
				$commitIdx = (int)$zmsg->pop();
			}
			$args[] = $commitIdx;
			$this->emit('appendEntries', $args);
		}

		if ($type == 'ELECT') {
			$term = (int)$zmsg->pop();
			$this->emit('election', array(
				$from, $term, $socket
			));

		}
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

	public function findPeerByZmqId($id) {
		if (!isset($this->listPeersByZmqId[$id])) {
			return FALSE;
		}
		return $this->findPeer($this->listPeersByZmqId[$id]);
	}

	public function addPeer($peer) {
		$this->listPeers[$peer->endpoint] = $peer;
		$peer->sayHello($this->endpoint);
	}

	public function sendElections($currentTerm) {
		foreach ($this->listPeers as $_p) {
			Raft_Logger::log( sprintf("[%s] sending election to %s @t%d", $this->name, $_p->endpoint, $currentTerm), 'D');
			$_p->conn->sendElection($this->conn->endpoint,  $currentTerm, 0, 0);
		}
	}
}
