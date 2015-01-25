
#php ./src/raft/node.php S0 ipc:///tmp/node0.ipc ipc:///tmp/node1.ipc & node0=$!
#php ./src/raft/node.php S1 ipc:///tmp/node1.ipc ipc:///tmp/node0.ipc & node1=$!

php ./src/raft/node.php S0 tcp://127.0.0.1:5580 tcp://127.0.0.1:5581 tcp://127.0.0.1:5582 tcp://127.0.0.1:5583 & node0=$!
sleep 0.1
php ./src/raft/node.php S1 tcp://127.0.0.1:5581 tcp://127.0.0.1:5580 tcp://127.0.0.1:5582 tcp://127.0.0.1:5583 & node1=$!
php ./src/raft/node.php S2 tcp://127.0.0.1:5582 tcp://127.0.0.1:5580 tcp://127.0.0.1:5581 tcp://127.0.0.1:5583 & node2=$!
php ./src/raft/node.php S3 tcp://127.0.0.1:5583 tcp://127.0.0.1:5580 tcp://127.0.0.1:5581 tcp://127.0.0.1:5582 & node3=$!

sleep 8;
kill $node0;
sleep 8;
kill $node1;
kill $node2;
kill $node3;
