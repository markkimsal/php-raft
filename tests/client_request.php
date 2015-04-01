<?php

$ctx   = new ZMQContext();

$endpoint = 'tcp://127.0.0.1:5580';
$sockDealer  = new ZMQSocket($ctx, ZMQ::SOCKET_DEALER);
$sockDealer->connect($endpoint);

$sockDealer->send("REQUEST", ZMQ::MODE_SNDMORE);
$sockDealer->send('FOOBAR');

