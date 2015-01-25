<?php
//include(dirname(__FILE__).'/../src/raft/node.php');

array_shift($argv);
$ep        = array_shift($argv);
$entry     = array_shift($argv);

/* Create new queue object */
$socket = new ZMQSocket(new ZMQContext(), ZMQ::SOCKET_REQ);
$socket->connect($ep);

/* Assign socket 1 to the queue, send and receive */
$socket->send("REQUEST", ZMQ::MODE_SNDMORE);
var_dump($socket->send($entry)->recv());
