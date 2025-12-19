# Jalankan di SERVER untuk update code

# 1. Pull latest code
git pull origin main

# 2. Check webhook file version
echo "Checking webhook code..."
grep -n "whatsapp.message.updated" api/app/Controllers/Webhook/WhatsApp.php

# 3. Check for handleMessageUpdated method
grep -n "handleMessageUpdated" api/app/Controllers/Webhook/WhatsApp.php

# 4. If both found, code is updated
echo "✅ Code should be updated"
