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

define('HB_INTERVAL', 2.0);
define('LE_INTERVAL', 0.9);

include_once(dirname(__FILE__).'/server.php');
include_once(dirname(__FILE__).'/connection.php');
include_once(dirname(__FILE__).'/peernode.php');
include_once(dirname(__FILE__).'/peerconnection.php');
include_once(dirname(__FILE__).'/msghandler.php');
include_once(dirname(__FILE__).'/log.php');
include_once(dirname(__FILE__).'/zmsg.php');
include_once(dirname(__FILE__).'/../helper/logger.php');

class Raft_Node {

	public $server        = NULL;
	public $name          = '';
	public $votes         = 0;
	protected $hb_at      = 0.0;
	protected $handler    = NULL;
	protected $leaderNode = NULL;

	public $currentTerm  = 0;
	public $votedFor     = NULL;
	public $log          = NULL;

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
		$this->server  = new Raft_Server($name);
		$this->log     = new Raft_Log();
	}

	public function begin($endpoint) {
		Raft_Logger::log(sprintf("[%s] binding dealer connection to %s ...", $this->name, $endpoint), 'D');
		$this->server->joinCluster($endpoint);

		$this->server->on('appendEntries',      array($this, 'appendEntries'));
		$this->server->on('appendEntriesReply', array($this, 'appendEntriesReply'));
		$this->server->on('clientRequest',      array($this, 'clientRequest'));
		$this->server->on('election',           array($this, 'election'));
		$this->server->on('recvVote',           array($this, 'recvVote'));
	}

	public function addPeer($peer) {
		Raft_Logger::log(sprintf("[%s] opening router connection to %s ...", $this->name, $peer->endpoint), 'D');
//		$connPeer =  new Raft_PeerConnection();
//		$connPeer->connect($endpoint);
		$this->server->addPeer($peer);
		$peer->setNextIndex($this);
	}

	public function run() {
		$running = TRUE;

		while($running) {
			$running = $this->timer();
			$running = $this->poll();
		}
	}

	public function timer() {
		$mt = microtime(true);
		if($mt > $this->hb_at) {
		//Raft_Logger::log( sprintf("[%s] %0.4f  %0.4f", $this->name, $mt, $this->hb_at), 'D');
			if ($this->isFollower() || $this->isCandidate()) {
				$this->transitionToCandidate();
				$this->server->sendElections($this->currentTerm);
			}

			if ($this->isLeader()) {
				$this->pingPeers();
			}
			$this->resetHb();
		}
		return TRUE;
	}

	public function poll() {
		return $this->server->poll();
	}

	/**
	 * If you're a candidate (or leader)
	 * count the number of votes for this term.
	 * if number of votes is greater than sizeof PeerPool / 2 + 1 then congrats,
	 * they will welcome their new leader.
	 */
	public function recvVote($from) {
		//don't take votes if you're not a candidate (or leader)
		if ($this->isFollower()) {
			Raft_Logger::log( sprintf('[%s] got wild vote from %s when I was just a follower.', $this->name, $from), 'W');
			return;
		}

		Raft_Logger::log( sprintf('[%s] got vote from  %s', $this->name, $from), 'D');
		//TODO count votes per term
		$this->votes++;
		if ($this->votes >= floor(count($this->getPeers())/2) +1) {
			Raft_Logger::log( sprintf('[%s] is leader', $this->name), 'D');
			$this->transitionToLeader();
			$this->resetHb();
			$this->pingPeers();
		}
	}

	/**
	 * @void
	 */
	public function appendEntries($term, $leaderId, $prevIdx, $prevTerm, $entry, $commitIdx, $endpoint) {

		if ($term > $this->currentTerm) {
			Raft_Logger::log( sprintf('[%s] reject entry based on term %d', $this->name, $this->currentTerm), 'D');
			return;
		}

		//TODO: update peer log, respond
		$this->resetHb();
		$this->votes = 0;
		if ($this->isLeader()) {
			//we shouldn't get append entries when we're the leader.
			Raft_Logger::log( sprintf('[%s] reject appendEntries because we are the leader. aren\'t we?', $this->name), 'E');
			return;
		}

		if ($this->log->getTermForIndex($prevIdx) != $prevTerm) {
			$this->log->debugLog();
			Raft_Logger::log( sprintf('[%s] reject entry based on term diff - my prev term:\'%s\' leader prev term:\'%s\' leader prev idx: \'%s\'', $this->name, $this->log->getTermForIndex($prevIdx), $prevTerm, $prevIdx), 'D');
			return;
		}

		if (empty($entry)) {
			return;
		}

		Raft_Logger::log( sprintf('[%s] appending entry %s from %s', $this->name, print_r($entry, 1), $endpoint), 'D');

		$this->appendEntry($entry, 'from');
		$peer = $this->findPeer($endpoint);
		$peer->conn->sendAppendReply($this->currentTerm, $this->log->getCommitIndex());

		if ($commitIdx > -1) {
			$this->log->commitIndex($commitIdx);
			$this->log->debugLog();
		}
	}

	/**
	 * @void
	 */
	public function clientRequest($body, $from) {
		Raft_Logger::log( sprintf('[%s] client request %s', $this->name, print_r($body,1)), 'D');
		//TODO: if leader
		$this->appendEntry($body, $from);
	}


	public function election($from, $term, $socket) {
		if ($term <= $this->currentTerm) {
			Raft_Logger::log( sprintf('[%s] rejecting old term election %s <= %s from %s', $this->name, $term, $this->currentTerm,  $from), 'D');
			return;
		}
		Raft_Logger::log( sprintf('[%s] got election from %s', $this->name, $from), 'D');

		$p = $this->server->findPeerByZmqId($from);
		if (!$p) {
			Raft_Logger::log( sprintf('[%s] cannot find peer %s', $this->name, $from), 'E');
			return;
		}
		Raft_Logger::log( sprintf('[%s] casting vote for %s @t%s', $this->name, $from, $term), 'D');
		$p->conn->sendVote($from, $term, 0);
		$this->currentTerm = $term;
		$this->state = 'follower';
		$this->setLeaderNode($from);
		$this->resetHb();
		$this->votes++;
	}

	public function resetHb() {
		$mt = microtime(true);
		if ($this->isLeader()) {
			$this->hb_at = $mt + (LE_INTERVAL - (LE_INTERVAL * rand(0.10, 0.50)));
		} else {
			$this->hb_at = $mt + (HB_INTERVAL - (HB_INTERVAL * rand(0.10, 0.50)));
		}
//		Raft_Logger::log( sprintf("[%s] %0.4f  %0.4f *", $this->name, $mt, $this->hb_at), 'D');
	}

	/**
	 * Return array of nodes joined to this cluster
	 */
	public function getPeers() {
		return $this->server->getPeers();
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
		$this->nextTerm();
	}

	public function transitionToLeader() {
		$this->state = 'leader';
		$this->votes = 0;
	}

	public function nextTerm() {
		$this->currentTerm++;
		$this->votes = 0;
	}

    /**
     * Create RPC objects for each connected peer
     * with the proper parameter for AppendEntries
     * @return Array list of Raft_Rpc_AppendEntries objects
     */
    public function getAppendEntries($entry=NULL) {
        $listPeers = $this->getPeers();
        $ret = array();
        foreach ($listPeers as $_p) {
			$ret[] = Raft_Rpc_AppendEntries::make($_p, $this, $entry);
        }
        return $ret;
    }

	/**
	 * Save a pending entry
	 * @void
	 */
	public function appendEntry($entry, $from) {
		//TODO save client's zmqid to reply after appending entry to raft log
		if (!$this->isLeader()) {
			//Raft_Logger::log( sprintf("[%s] AE %s from  %s", $this->name, $entry, $from), 'D');
			//$this->conn->replyToClient($from, "FAIL");
			$idx = $this->log->appendEntry($entry, $this->currentTerm);
			return;
		}

		//if client wants us to append an entry, then tell quorum about it and commit

		$idx = $this->log->appendEntry($entry, $this->currentTerm);
		$this->log->debugLog();
		foreach ($this->getPeers() as $_peer) {
			Raft_Logger::log( sprintf("[%s] sending AE to %s", $this->name, $_peer->endpoint), 'D');
			$_peer->conn->sendAppendEntries(
				$this->server->endpoint,
				$this->currentTerm,
				$this->server->endpoint,
				$_peer->nextIndex - 1,
				$this->log->getTermForIndex($_peer->nextIndex - 1),
				$entry
			);
		}
	}

	public function appendEntriesReply($from, $term, $success, $matchIndex) {

			Raft_Logger::log( sprintf('[%s] got reply from peer %s', $this->name, $from), 'D');
//TODO this isn't the right comparison
		if ($matchIndex < $this->log->getCommitIndex()) {
			$p = $this->findPeer($from);
			if (!$p) {
				Raft_Logger::log( sprintf('[%s] cannot find peer %s', $this->name, $from), 'E');
				return;
			}

			Raft_Logger::log( sprintf('[%s] leader committing log', $this->name), 'D');
			$this->log->commitIndex($matchIndex);
			$this->log->debugLog();
			$p->matchIndex = (int)$matchIndex;
			$p->nextIndex  = (int)$matchIndex+1;
		}
	}

	public function pingPeers() {
		foreach ($this->getPeers() as $_peer) {
			Raft_Logger::log( sprintf("[%s] sending hb to %s", $this->name, $_peer->endpoint), 'D');
			$_peer->conn->sendHeartbeat(
				$this->currentTerm,
				$this->server->endpoint,
				$_peer->nextIndex - 1,
				$this->log->getTermForIndex($_peer->nextIndex - 1)
			);
		}
	}
}
