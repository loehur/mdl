<?php
$c = new mysqli('localhost','root','','mdl_main');
echo "Status Summary:\n";
echo str_repeat("-", 30) . "\n";
$r = $c->query('SELECT status, COUNT(*) as total FROM wa_messages GROUP BY status ORDER BY status');
while($d = $r->fetch_assoc()){ 
    $status = $d['status'] ?? 'NULL';
    echo str_pad($status, 15) . ": " . $d['total'] . "\n";
}
echo "\nRecent messages:\n";
echo str_repeat("-", 80) . "\n";
$r2 = $c->query('SELECT id, direction, status, sent_at, delivered_at, read_at, created_at FROM wa_messages ORDER BY id DESC LIMIT 5');
printf("%-5s %-10s %-10s %-20s %-20s %-20s\n", "ID", "Direction", "Status", "Sent", "Delivered", "Read");
echo str_repeat("-", 80) . "\n";
while($d = $r2->fetch_assoc()){ 
    printf("%-5s %-10s %-10s %-20s %-20s %-20s\n", 
        $d['id'], 
        $d['direction'], 
        $d['status'] ?? 'NULL',
        $d['sent_at'] ?? '-',
        $d['delivered_at'] ?? '-',
        $d['read_at'] ?? '-'
    );
}
