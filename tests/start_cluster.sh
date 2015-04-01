#!/bin/bash
#php ./bin/startnode.php S0 ipc:///tmp/node0.ipc ipc:///tmp/node1.ipc & node0=$!
#php ./bin/startnode.php S1 ipc:///tmp/node1.ipc ipc:///tmp/node0.ipc & node1=$!

php ./bin/startnode.php S0 tcp://127.0.0.1:5580 tcp://127.0.0.1:5581 tcp://127.0.0.1:5582 tcp://127.0.0.1:5583 & node0=$!
sleep 0.1
php ./bin/startnode.php S1 tcp://127.0.0.1:5581 tcp://127.0.0.1:5580 tcp://127.0.0.1:5582 tcp://127.0.0.1:5583 & node1=$!
php ./bin/startnode.php S2 tcp://127.0.0.1:5582 tcp://127.0.0.1:5580 tcp://127.0.0.1:5581 tcp://127.0.0.1:5583 & node2=$!
php ./bin/startnode.php S3 tcp://127.0.0.1:5583 tcp://127.0.0.1:5580 tcp://127.0.0.1:5581 tcp://127.0.0.1:5582 & node3=$!

function cleanup {
kill $node0;kill $node1;kill $node2;kill $node3;
exit;
}

trap cleanup SIGINT SIGTERM EXIT


while true
do
sleep 4
done
#sleep 4;
#kill $node0;
#sleep 12;
#kill $node1;
#kill $node2;
#kill $node3;
