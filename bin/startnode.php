<?php
include(dirname(__FILE__).'/../src/raft/node.php');

array_shift($argv);
$name      = array_shift($argv);
$self      = array_shift($argv);
$n1 = new Raft_Node($name);
$n1->begin($self);
sleep(1);
$listPeers = array();
foreach ($argv as $_n) {
	$_p = new Raft_Peernode($_n);
	$_p->connect();
	$n1->addPeer($_p);
}
$n1->run();

