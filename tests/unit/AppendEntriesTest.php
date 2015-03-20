<?php

include_once(dirname(dirname(__DIR__)).'/src/raft/peernode.php');
include_once(dirname(dirname(__DIR__)).'/src/raft/node.php');

class Raft_AppendEntries_Test extends PHPUnit_Framework_TestCase {

	public function setUp() {
		$this->ep = 'tcp://127.0.0.1:5581';
		$peer = new Raft_Peernode($this->ep);

		$conn = $this->getMock('Raft_Peerconnection', array('connect', 'sendHello'));
		$peer->connect($conn);

		$this->leader = new Raft_Node('S1');
		$this->leader->transitionToLeader();
		$this->leader->addPeer($peer);
	}

	public function test_append_entries_new_peer() {
		$listRpc = $this->leader->getAppendEntries('setX');
		$this->assertEquals( 1, count($listRpc));
		$this->assertEquals( $this->ep , $listRpc[0]->peerNode->endpoint);
	}

	public function test_append_entries_next_index_is_one_more_than_leader() {
		$ep = 'tcp://127.0.0.1:5582';
		$peer = new Raft_Peernode($ep);

		$conn = $this->getMock('Raft_Peerconnection', array('connect', 'sendHello'));
		$peer->connect($conn);

		$this->leader->addPeer($peer);

		$listRpc = $this->leader->getAppendEntries('setX');
		$this->assertEquals( 2, count($listRpc));
		$this->assertEquals( 1 , $listRpc[1]->peerNode->nextIndex);
	}

	public function test_append_entries_prev_term_comes_from_peer_not_leader() {
		$ep = 'tcp://127.0.0.1:5582';
		$peer = new Raft_Peernode($ep);
		$conn = $this->getMock('Raft_Peerconnection', array('connect', 'sendHello'));
		$peer->connect($conn);

		$this->leader->addPeer($peer);

		$key = $this->leader->log->appendEntry('setY', 1);
		$this->leader->log->commitIndex($key);
		$this->leader->log->appendEntry('setY', 2);

//		echo $this->leader->log->debugLog();
		$listRpc = $this->leader->getAppendEntries('setX');
		$this->assertEquals( 1 , $listRpc[1]->prevLogTerm);
	}
}
