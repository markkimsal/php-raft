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


	public function onMsg($msg, $node) {
		$from     = $msg->unwrap();
		//$null   = $msg->unwrap();
		$type     = $msg->unwrap();

		if ($type == 'REQUEST') {
			Raft_Logger::log( sprintf('[%s] got request', $node->name), 'D');
			$node->appendEntry($msg->unwrap(), $from);
			return;
		}
		//clients don't send their endpoint address, only zmqid
		$from = $type;
		$type = $msg->unwrap();

		if ($type == 'HEARTBEAT') {
			$node->resetHb();
			$node->votes = 0;
			Raft_Logger::log( sprintf('[%s] got hb', $node->name), 'D');
		}

		if ($type == 'VOTE') {
			$node->votes++;
			if ($node->votes >= floor(count($node->getPeers())/2) +1) {
				Raft_Logger::log( sprintf('[%s] is leader', $node->name), 'D');
				$node->transitionToLeader();
				$node->resetHb();
				$node->pingPeers();
			}
		}

		if ($type == 'ELECT') {
			$term = (int)$msg->unwrap();
			if ($term <= $node->currentTerm) {
				Raft_Logger::log( sprintf('[%s] rejecting old term election %s <= %s from %s', $node->name, $term, $node->currentTerm,  $from), 'D');
			}
			if ($term > $node->currentTerm) {
				Raft_Logger::log( sprintf('[%s] got election from %s', $node->name, $from), 'D');
				$p = $node->findPeer($from);
				if (!$p) {
					Raft_Logger::log( sprintf('[%s] cannot find peer %s', $node->name, $from), 'E');
					return;
				}
				Raft_Logger::log( sprintf('[%s] casting vote for %s @t%s', $node->name, $from, $term), 'D');
				$p->sendVote($from, $term, 0);
				$node->currentTerm = $term;
				$node->state = 'follower';
				$node->setLeaderNode($from);
				$node->resetHb();
				$node->votes++;
			}
		}
	}
}
