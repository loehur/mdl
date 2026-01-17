package com.cms.mdl

import android.util.Log
import com.onesignal.notifications.INotificationReceivedEvent
import com.onesignal.notifications.INotificationServiceExtension

/**
 * OneSignal Notification Service Extension
 * 
 * Intercepts all incoming push notifications to:
 * 1. Create native notifications with stable IDs (for proper cancellation)
 * 2. Handle cancel_chat commands to dismiss notifications
 * 3. Support WhatsApp-style message stacking
 */
class NotificationServiceExtension : INotificationServiceExtension {
    
    companion object {
        private const val TAG = "NotificationExtension"
    }

    override fun onNotificationReceived(event: INotificationReceivedEvent) {
        val notification = event.notification
        val data = notification.additionalData
        val context = event.context

        Log.d(TAG, "=== Push Received ===")
        Log.d(TAG, "Notification ID: ${notification.notificationId}")
        
        // Log all data keys for debugging
        if (data != null) {
            val keys = data.keys().asSequence().toList()
            Log.d(TAG, "Data keys: ${keys.joinToString(", ")}")
            keys.forEach { key ->
                Log.d(TAG, "  $key = ${data.opt(key)}")
            }
        }

        // Extract type from data
        val type = data?.optString("type") ?: ""
        
        when (type) {
            "cancel_chat" -> handleCancelChat(event, data)
            "wa_masuk" -> handleIncomingMessage(event, data)
            else -> {
                // Unknown type - let OneSignal handle it normally
                Log.d(TAG, "Unknown type '$type', passing to OneSignal")
            }
        }
    }
    
    /**
     * Handle cancel_chat: Cancel notification using stored ID
     */
    private fun handleCancelChat(event: INotificationReceivedEvent, data: org.json.JSONObject?) {
        val context = event.context
        
        // Extract phone identifiers
        val phone = data?.optString("phone") ?: ""
        val cleanPhone = data?.optString("clean_phone") 
            ?: data?.optString("phone_clean") 
            ?: phone.replace(Regex("[^0-9]"), "")
        val groupId = data?.optString("group_id") 
            ?: data?.optString("chat_group") 
            ?: ""
        
        Log.i(TAG, "🔇 CANCEL received: phone=$phone, cleanPhone=$cleanPhone, groupId=$groupId")
        
        if (cleanPhone.isNotEmpty()) {
            // Cancel using NotificationHelper (uses stored ID)
            val cancelled = NotificationHelper.cancelNotification(context, cleanPhone)
            Log.i(TAG, "✅ Cancel result: $cancelled")
        } else {
            Log.w(TAG, "⚠️ Cannot cancel: no phone identifier")
        }
        
        // ALWAYS prevent this notification from showing
        event.preventDefault()
        Log.d(TAG, "Cancel notification prevented from display")
    }
    
    /**
     * Handle wa_masuk: Create native notification with stable ID
     */
    private fun handleIncomingMessage(event: INotificationReceivedEvent, data: org.json.JSONObject?) {
        val context = event.context
        
        // Extract notification content from data
        val title = data?.optString("notif_title") ?: "Pesan Baru"
        val body = data?.optString("notif_body") ?: data?.optString("notif_message") ?: ""
        val singleMessage = data?.optString("notif_message") ?: ""
        
        // Extract phone identifiers
        val phone = data?.optString("phone") ?: ""
        val cleanPhone = data?.optString("clean_phone") 
            ?: data?.optString("phone_clean") 
            ?: phone.replace(Regex("[^0-9]"), "")
        
        // Extract additional info
        val caseType = data?.optInt("case", 0) ?: 0
        val notifCount = data?.optInt("notif_count", 1) ?: 1
        
        Log.i(TAG, "📩 Message received: phone=$cleanPhone, title=$title")
        Log.d(TAG, "  body=$body, case=$caseType, count=$notifCount")
        
        if (cleanPhone.isEmpty()) {
            Log.w(TAG, "⚠️ No phone identifier, skipping notification")
            event.preventDefault()
            return
        }
        
        if (body.isBlank() && singleMessage.isBlank()) {
            Log.w(TAG, "⚠️ Empty message body, skipping notification")
            event.preventDefault()
            return
        }
        
        try {
            // Create native notification using helper
            NotificationHelper.showNotification(
                context = context,
                cleanPhone = cleanPhone,
                title = title,
                body = body,
                singleMessage = singleMessage
            )
            
            Log.i(TAG, "✅ Native notification created for $cleanPhone")
            
        } catch (e: Exception) {
            Log.e(TAG, "❌ Error creating notification: ${e.message}", e)
        }
        
        // ALWAYS prevent OneSignal from showing its own notification
        // We've created our own native notification
        event.preventDefault()
        Log.d(TAG, "OneSignal notification prevented (native shown instead)")
    }
}
