<?php
// Test webhook dengan sample data dari user sebelumnya

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'mdl_main';

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== Testing YCloud Webhook Flow ===\n\n";

// Sample data dari log user
$sampleJson = '{
    "id":"evt_6945402c76d27b0ef51b3229",
    "type":"whatsapp.inbound_message.received",
    "apiVersion":"v2",
    "createTime":"2025-12-19T12:08:12.919Z",
    "whatsappInboundMessage":{
        "id":"6945402c1ff3e6369b1c431b",
        "wamid":"wamid.HBgNNjI4MTI2ODA5ODMwMBUCABIYIEFDOTc1NjQwREZGNTRDMkM1NEQxNjg1OTA0MkMxNUYyAA==",
        "wabaId":"2115170262645237",
        "from":"+6281268098300",
        "customerProfile":{"name":"loehur"},
        "to":"+6281170706611",
        "sendTime":"2025-12-19T12:08:11.000Z",
        "type":"text",
        "text":{"body":"Apa kabar madinah laundry"}
    }
}';

$data = json_decode($sampleJson, true);
$msg = $data['whatsappInboundMessage'];

$waNumber = $msg['from'];
$contactName = $msg['customerProfile']['name'];
$sendTime = date('Y-m-d H:i:s', strtotime($msg['sendTime']));

echo "Step 1: Check/Create Customer\n";
echo "Number: $waNumber\n";
echo "Name: $contactName\n";
echo "Time: $sendTime\n\n";

// Check customer
$check = $mysqli->query("SELECT * FROM wa_customers WHERE wa_number = '$waNumber'");
if ($check->num_rows > 0) {
    $customer = $check->fetch_assoc();
    echo "✓ Customer exists: ID={$customer['id']}\n";
    
    // Update
    $sql = "UPDATE wa_customers SET 
            last_message_at = '$sendTime',
            total_messages = total_messages + 1,
            contact_name = '$contactName'
            WHERE id = {$customer['id']}";
    
    if ($mysqli->query($sql)) {
        echo "✓ Customer updated\n";
    } else {
        echo "✗ Update failed: " . $mysqli->error . "\n";
    }
    
    $customerId = $customer['id'];
} else {
    // Insert
    $sql = "INSERT INTO wa_customers (wa_number, contact_name, last_message_at, first_contact_at, total_messages, is_active) 
            VALUES ('$waNumber', '$contactName', '$sendTime', '$sendTime', 1, 1)";
    
    if ($mysqli->query($sql)) {
        $customerId = $mysqli->insert_id;
        echo "✓ New customer created: ID=$customerId\n";
    } else {
        echo "✗ Insert failed: " . $mysqli->error . "\n";
        exit;
    }
}

echo "\nStep 2: Check/Create Conversation\n";

$check = $mysqli->query("SELECT * FROM wa_conversations WHERE wa_number = '$waNumber'");
if ($check->num_rows > 0) {
    $conv = $check->fetch_assoc();
    echo "✓ Conversation exists: ID={$conv['id']}\n";
    $conversationId = $conv['id'];
} else {
    $sql = "INSERT INTO wa_conversations (customer_id, wa_number, contact_name, status) 
            VALUES ($customerId, '$waNumber', '$contactName', 'open')";
    
    if ($mysqli->query($sql)) {
        $conversationId = $mysqli->insert_id;
        echo "✓ New conversation created: ID=$conversationId\n";
    } else {
        echo "✗ Insert failed: " . $mysqli->error . "\n";
        exit;
    }
}

echo "\nStep 3: Insert Message\n";

$text = $mysqli->real_escape_string($msg['text']['body']);
$messageId = $msg['id'];
$wamid = $msg['wamid'];

$sql = "INSERT INTO wa_messages (
    conversation_id, customer_id, direction, message_type, 
    text, provider_message_id, wamid, created_at
) VALUES (
    $conversationId, $customerId, 'in', 'text',
    '$text', '$messageId', '$wamid', '$sendTime'
)";

if ($mysqli->query($sql)) {
    $msgId = $mysqli->insert_id;
    echo "✓ Message inserted: ID=$msgId\n";
} else {
    echo "✗ Insert failed: " . $mysqli->error . "\n";
    exit;
}

echo "\nStep 4: Update Conversation Last Message\n";

$sql = "UPDATE wa_conversations SET 
        last_message = '$text',
        last_message_at = '$sendTime'
        WHERE id = $conversationId";

if ($mysqli->query($sql)) {
    echo "✓ Conversation updated\n";
} else {
    echo "✗ Update failed: " . $mysqli->error . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✓ TEST COMPLETE - Check tables:\n\n";

// Show results
$customer = $mysqli->query("SELECT * FROM wa_customers WHERE id = $customerId")->fetch_assoc();
echo "Customer #{$customer['id']}: {$customer['wa_number']} - Last: {$customer['last_message_at']}\n";

$conv = $mysqli->query("SELECT * FROM wa_conversations WHERE id = $conversationId")->fetch_assoc();
echo "Conversation #{$conv['id']}: {$conv['last_message']}\n";

$msgCount = $mysqli->query("SELECT COUNT(*) as c FROM wa_messages WHERE customer_id = $customerId")->fetch_assoc();
echo "Messages: {$msgCount['c']}\n";

$mysqli->close();
