<?php

class Raft_Rpc_AppendEntries {

	public static function make($peer, $node, $entry=NULL) {
		$rpc = new Raft_Rpc_AppendEntries();
		$rpc->peerNode     = $peer;
		$rpc->term         = $node->currentTerm;
		$rpc->leaderId     = $node->server->endpoint;
		$rpc->prevLogIndex = $peer->nextIndex -1;
//var_dump($peer);
		$rpc->prevLogTerm  = $node->log->getTermForIndex($rpc->prevLogIndex);
		//AppendEntries doubles as a heartbeat unless we want to
		//also pass some real information (like an entry)
		$rpc->entry        = $entry;
		return $rpc;
	}

}
