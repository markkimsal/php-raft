<?php

class Raft_Rpc_AppendEntries {

	public static function make($peer, $node) {
		$rpc = new Raft_Rpc_AppendEntries();
		$rpc->peerNode     = $peer;
		$rpc->term         = $node->currentTerm;
		$rpc->leaderId     = $node->conn->endpoint;
		$rpc->prevLogIndex = $peer->nextIndex -1;
//var_dump($peer);
		$rpc->prevLogTerm  = $node->log->getTermForIndex($rpc->prevLogIndex);
		$rpc->entry        = $node->log->getEntryForIndex($peer->nextIndex-1);
		return $rpc;
	}

}
