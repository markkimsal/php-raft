<?php
include_once(dirname(dirname(__DIR__)).'/src/raft/log.php');

class Raft_Log_Test extends PHPUnit_Framework_TestCase {

	public function setUp() {
		$this->log = new Raft_Log();
	}

	public function test_log_append_entry_is_pending() {
		$this->assertEquals(0, $this->log->getCommitIndex());
		$this->log->appendEntry('add', '2');
		
		$this->assertEquals(0, $this->log->getCommitIndex());
		$this->assertEquals('2', $this->log->getPendingTerm());
		$this->assertEquals('add', $this->log->getPendingEntry());
	}

	public function test_commit_increases_commit_index() {
		$this->assertEquals(0, $this->log->getCommitIndex());
		$this->log->appendEntry('add', '2');
		$this->log->commitEntry();
		
		$this->assertEquals(1, $this->log->getCommitIndex());
		$this->assertEquals(NULL, $this->log->getPendingTerm());
		$this->assertEquals(NULL, $this->log->getPendingEntry());
	}

}
