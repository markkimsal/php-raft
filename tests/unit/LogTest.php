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
	}

	public function test_commit_increases_commit_index() {
		$this->assertEquals(0, $this->log->getCommitIndex());
		$this->log->appendEntry('add', '2');
		$this->log->commitIndex(1);
		
		$this->assertEquals(1, $this->log->getCommitIndex());
	}

	public function test_commit_saves_correct_term() {
		$this->assertEquals(0, $this->log->getCommitIndex());
		$idx0 = $this->log->appendEntry('add', 2);
		$idx1 = $this->log->appendEntry('add', 3);

		$this->assertEquals(2, $this->log->getTermForIndex(0));
		$this->assertEquals(3, $this->log->getTermForIndex(1));

		$this->assertEquals(0, $idx0);
		$this->assertEquals(1, $idx1);
	}

	public function test_can_support_multiple_pending_entries() {
		$this->log->appendEntry('addX', 2);
		$this->log->appendEntry('addY', 2);
		$this->log->appendEntry('addZ', 2);

		$this->assertEquals(3, $this->log->getCountPending());
	}
}
