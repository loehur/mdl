/**
 * KODE UNTUK MODIFIKASI NODE.JS WEBSOCKET SERVER
 * File: waserver (atau server.js di project waserver Anda)
 * 
 * Tambahkan logika ini di bagian yang menangani incoming message dari PHP backend
 */

// Contoh handler di endpoint /incoming (Express)
app.post('/incoming', (req, res) => {
    const data = req.body;

    // MODIFIKASI: Jika target_id = '0', broadcast ke SEMUA client
    if (data.target_id === '0') {
        console.log('[BROADCAST] Sending to ALL clients:', data.type);

        // Kirim ke semua WebSocket client yang terkoneksi
        wss.clients.forEach((client) => {
            if (client.readyState === WebSocket.OPEN) {
                client.send(JSON.stringify(data));
            }
        });

        res.json({ success: true, message: 'Broadcast to all clients' });

    } else {
        // Logika lama: Kirim ke specific client berdasarkan ID
        const targetClient = connectedClients[data.target_id];

        if (targetClient && targetClient.readyState === WebSocket.OPEN) {
            targetClient.send(JSON.stringify(data));
            res.json({ success: true, message: 'Message sent to client' });
        } else {
            res.json({ success: false, message: 'Target client not connected' });
        }
    }
});


/**
 * ALTERNATIF: Jika menggunakan Socket.io
 */
io.on('connection', (socket) => {
    // ... existing code ...
});

// Handler incoming dari PHP backend
app.post('/incoming', (req, res) => {
    const data = req.body;

    if (data.target_id === '0') {
        // Broadcast ke SEMUA client Socket.io
        io.emit('message', data);
        res.json({ success: true, message: 'Broadcast to all' });
    } else {
        // Kirim ke room/user spesifik
        io.to(data.target_id).emit('message', data);
        res.json({ success: true, message: 'Sent to specific user' });
    }
});
