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
            val cleanPhone = data.optString("clean_phone", phone?.replace(Regex("[^0-9]"), "") ?: "")
            
            Log.i(TAG, "🔔 CANCEL processing for group: $groupKey, phone: $phone, cleanPhone: $cleanPhone")

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
                            
                            // Check for Phone/GroupKey match in extras (where OneSignal stores data)
                            var matched = false
                            
                            // Method 1: Check direct extras keys (OneSignal stores data here)
                            val extraPhone = extras.getString("phone")
                            val extraGroupId = extras.getString("group_id")
                            
                            if (extraPhone != null && phone != null) {
                                // Compare both original and cleaned phone
                                val extraCleanPhone = extraPhone.replace(Regex("[^0-9]"), "")
                                if (extraPhone == phone || extraCleanPhone == cleanPhone) {
                                    matched = true
                                    Log.d(TAG, "✅ Matched via direct phone extra: $extraPhone")
                                }
                            }
                            
                            if (!matched && extraGroupId != null && groupKey.isNotEmpty()) {
                                if (extraGroupId == groupKey || extraGroupId.contains(groupKey) || groupKey.contains(extraGroupId)) {
                                    matched = true
                                    Log.d(TAG, "✅ Matched via direct group_id extra: $extraGroupId")
                                }
                            }
                            
                            // Method 2: Check 'custom' JSON string (Standard OneSignal format)
                            // Format: {"i": "uuid", "a": { "group_id": "...", "phone": "..." }}
                            if (!matched) {
                                val custom = extras.getString("custom")
                                if (custom != null) {
                                    try {
                                        val customJson = org.json.JSONObject(custom)
                                        val additionalData = customJson.optJSONObject("a")
                                        
                                        if (additionalData != null) {
                                            val customPhone = additionalData.optString("phone")
                                            val customGroupId = additionalData.optString("group_id")
                                            
                                            // Match by phone
                                            if (customPhone.isNotEmpty() && phone != null) {
                                                val customCleanPhone = customPhone.replace(Regex("[^0-9]"), "")
                                                if (customPhone == phone || customCleanPhone == cleanPhone) {
                                                    matched = true
                                                    Log.d(TAG, "✅ Matched via custom JSON (phone): $customPhone")
                                                }
                                            }
                                            
                                            // Match by group_id
                                            if (!matched && customGroupId.isNotEmpty() && groupKey.isNotEmpty()) {
                                                if (customGroupId == groupKey || customGroupId.contains(groupKey) || groupKey.contains(customGroupId)) {
                                                    matched = true
                                                    Log.d(TAG, "✅ Matched via custom JSON (group_id): $customGroupId")
                                                }
                                            }
                                        }
                                        
                                        // Fallback: String contains check (less reliable but catches edge cases)
                                        if (!matched) {
                                            if (groupKey.isNotEmpty() && custom.contains(groupKey)) {
                                                matched = true
                                                Log.d(TAG, "✅ Matched via custom string contains (groupKey)")
                                            } else if (cleanPhone.isNotEmpty() && custom.contains(cleanPhone)) {
                                                matched = true
                                                Log.d(TAG, "✅ Matched via custom string contains (cleanPhone)")
                                            }
                                        }
                                    } catch (e: Exception) {
                                        Log.w(TAG, "Error parsing custom JSON: ${e.message}")
                                        // Fallback: simple string contains
                                        if (groupKey.isNotEmpty() && custom.contains(groupKey)) {
                                            matched = true
                                            Log.d(TAG, "✅ Matched via custom string fallback (groupKey)")
                                        }
                                    }
                                }
                            }
                            
                            // Method 3: Check Android notification grouping (API 24+)
                            if (!matched && Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                                val sbnGroup = statusBarNotification.groupKey
                                if (sbnGroup != null && groupKey.isNotEmpty()) {
                                    // OneSignal might use groupKey in the notification group
                                    if (sbnGroup.contains(groupKey) || groupKey.contains(sbnGroup)) {
                                        matched = true
                                        Log.d(TAG, "✅ Matched via groupKey: $sbnGroup")
                                    }
                                }
                            }
                            
                            // Method 4: Check notification tag (OneSignal uses collapse_id as tag sometimes)
                            if (!matched) {
                                val tag = statusBarNotification.tag
                                if (tag != null && groupKey.isNotEmpty() && tag.contains(groupKey)) {
                                    matched = true
                                    Log.d(TAG, "✅ Matched via notification tag: $tag")
                                }
                            }

                            if (matched) {
                                val tag = statusBarNotification.tag ?: "onesignal"
                                val id = statusBarNotification.id
                                Log.i(TAG, "❌ Cancelling notification ID: $id, Tag: $tag, Group: ${statusBarNotification.groupKey}")
                                notificationManager.cancel(tag, id)
                                cancelledCount++
                            } else {
                                // Log for debugging
                                Log.v(TAG, "No match for SBN ID: ${statusBarNotification.id}, Tag: ${statusBarNotification.tag}, Group: ${statusBarNotification.groupKey}")
                            }
                        } catch (e: Exception) {
                            Log.e(TAG, "Error checking notification ID ${statusBarNotification.id}: ${e.message}", e)
                        }
                    }
                    
                    Log.i(TAG, "🔔 Cleanup complete. Cancelled $cancelledCount notification(s) for group: $groupKey")
                     
                } catch (e: Exception) {
                    Log.e(TAG, "Error in notification manager: ${e.message}", e)
                }
            } else {
                Log.w(TAG, "⚠️ Cannot cancel: groupKey is empty")
            }
            
            // Prevent THIS notification from showing (Silent)
            event.preventDefault()
            return
        }
    }
}
