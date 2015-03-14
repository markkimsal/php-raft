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

class Raft_Msghandler {

	public function onMsgReply($msg, $node) {
		$type     = $msg->pop();
		if ($type == 'VOTE') {
			$node->votes++;
			if ($node->votes >= floor(count($node->getPeers())/2) +1) {
				Raft_Logger::log( sprintf('[%s] is leader', $node->name), 'D');
				$node->transitionToLeader();
				$node->resetHb();
				$node->pingPeers();
			}
		}
		if ($type == 'AppendEntriesReply') {
			$from       = $msg->pop();
			$term       = (int)$msg->pop();
			$success    = (int)$msg->pop();
			$matchIndex = $msg->pop();
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

	public function onMsg($msg, $node) {
		$from     = $msg->pop();
		//$null   = $msg->pop();
		$type     = $msg->pop();

		if ($type == 'REQUEST') {
			Raft_Logger::log( sprintf('[%s] got request', $node->name), 'D');
			$node->appendEntry($msg->pop(), $from);
			return;
		}

		if ($type == 'AppendEntries') {
			$term = (int)$msg->pop();
			if ($term <= $node->currentTerm) {
				//TODO: update peer log, respond
				$node->resetHb();
				$node->votes = 0;
				if (!$node->isLeader()) {
					$leaderId  = $msg->pop();
					$prevIdx   = (int)$msg->pop();
					$prevTerm  = $msg->pop();
					$entry     = $msg->pop();
					$commitIdx = -1;
					if ($msg->parts()) {
						$commitIdx = (int)$msg->pop();
					}
					if ($node->log->getTermForIndex($prevIdx) != $prevTerm) {
						$node->log->debugLog();
						Raft_Logger::log( sprintf('[%s] reject entry based on term diff \'%s\' \'%s\'', $node->name, $node->log->getTermForIndex($prevIdx), $prevTerm), 'D');
						return;
					}
					Raft_Logger::log( sprintf('[%s] peer updating log', $node->name), 'D');
					$node->appendEntry($entry, $from);
					$node->conn->sendAppendReply($from, $term, $node->log->getCommitIndex());
					if ($commitIdx > -1) {
						$node->log->commitIndex($commitIdx);
						$node->log->debugLog();
					}
				}
			} else {
					Raft_Logger::log( sprintf('[%s] reject entry based on term %d', $node->name, $node->currentTerm), 'D');
			}
		}

/*
		if ($type == 'HEARTBEAT') {
			$node->resetHb();
			$node->votes = 0;
			Raft_Logger::log( sprintf('[%s] got hb', $node->name), 'D');
		}
*/


		if ($type == 'ELECT') {
			$term = (int)$msg->pop();
			if ($term <= $node->currentTerm) {
				Raft_Logger::log( sprintf('[%s] rejecting old term election %s <= %s from %s', $node->name, $term, $node->currentTerm,  $from), 'D');
			}
			if ($term > $node->currentTerm) {
				Raft_Logger::log( sprintf('[%s] got election from %s', $node->name, $from), 'D');

				Raft_Logger::log( sprintf('[%s] casting vote for %s @t%s', $node->name, $from, $term), 'D');
				$node->conn->sendVote($from, $term, 0);
				$node->currentTerm = $term;
/*
				$p = $node->findPeer($from);
				if (!$p) {
					Raft_Logger::log( sprintf('[%s] cannot find peer %s', $node->name, $from), 'E');
					return;
				}
				Raft_Logger::log( sprintf('[%s] casting vote for %s @t%s', $node->name, $from, $term), 'D');
				$p->conn->sendVote($from, $term, 0);
				$node->currentTerm = $term;
				$node->state = 'follower';
				$node->setLeaderNode($from);
				$node->resetHb();
				$node->votes++;
*/
			}
		}
	}
}
