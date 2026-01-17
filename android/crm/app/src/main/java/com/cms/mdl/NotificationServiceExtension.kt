package com.cms.mdl

import android.app.NotificationManager
import android.content.Context
import android.service.notification.StatusBarNotification
import android.os.Build
import android.util.Log
import com.onesignal.notifications.INotificationReceivedEvent
import com.onesignal.notifications.INotificationServiceExtension

class NotificationServiceExtension : INotificationServiceExtension {
    
    companion object {
        private const val TAG = "OneSignalExtension"
    }

    override fun onNotificationReceived(event: INotificationReceivedEvent) {
        val notification = event.notification
        val data = notification.additionalData
        val context = event.context

        Log.d(TAG, "Received notification data: ${data?.toString()}")

        // Check for CANCEL command
        if (data != null && data.has("type") && data.getString("type") == "cancel_chat") {
            val groupKey = data.optString("group_id")
            val phone = data.optString("phone")
            
            Log.i(TAG, "🔔 CANCEL processing for group: $groupKey, phone: $phone")

            if (groupKey.isNotEmpty()) {
                try {
                    val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
                    val activeNotifications = notificationManager.activeNotifications
                    
                    Log.d(TAG, "Scanning ${activeNotifications.size} active notifications for cleanup...")
                    
                    var cancelledCount = 0
                    
                    for (statusBarNotification in activeNotifications) {
                        try {
                            val n = statusBarNotification.notification
                            val extras = n.extras
                            
                            // Check for Phone match in extras (where OneSignal stores data)
                            // OneSignal usually puts data in 'custom' JSON string or specific keys
                            var matched = false
                            
                            // 1. Check direct 'phone' extra
                            val extraPhone = extras.getString("phone")
                            if (extraPhone != null && extraPhone == phone) {
                                matched = true
                            }
                            
                            // 2. Check 'custom' JSON string (Standard OneSignal)
                            // Format: {"i": "uuid", "a": { "group_id": "...", "phone": "..." }}
                            if (!matched) {
                                val custom = extras.getString("custom")
                                if (custom != null) {
                                    Log.v(TAG, "SBN ID ${statusBarNotification.id} custom: $custom")
                                    // Robust check: Check for groupKey or phone in the JSON string
                                    // We look for the exact groupKey "chat_xxxx"
                                    if (groupKey.isNotEmpty() && custom.contains(groupKey)) {
                                        matched = true
                                        Log.i(TAG, "✅ Matched via custom payload (groupKey)")
                                    } else if (phone.isNotEmpty() && custom.contains(phone)) {
                                        matched = true
                                        Log.i(TAG, "✅ Matched via custom payload (phone)")
                                    }
                                }
                            }
                            
                            // 3. Check grouping keys if provided
                            if (!matched && Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                                // OneSignal uses group key provided in payload
                                val sbnGroup = statusBarNotification.groupKey
                                if (sbnGroup != null && sbnGroup.contains(groupKey)) {
                                    matched = true
                                }
                            }

                            if (matched) {
                                Log.i(TAG, "❌ Cancelling notification ID: ${statusBarNotification.id}, Tag: ${statusBarNotification.tag}")
                                notificationManager.cancel(statusBarNotification.tag, statusBarNotification.id)
                                cancelledCount++
                            }
                        } catch (e: Exception) {
                            Log.e(TAG, "Error checking notification: ${e.message}")
                        }
                    }
                    
                     Log.i(TAG, "Cleanup complete. Cancelled $cancelledCount notification(s).")
                    
                } catch (e: Exception) {
                    Log.e(TAG, "Error in notification manager: ${e.message}")
                }
            }
            
            // Prevent THIS notification from showing (Silent)
            event.preventDefault()
            return
        }
    }
}
