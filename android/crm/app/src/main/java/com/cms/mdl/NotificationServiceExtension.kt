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

        Log.d(TAG, "=== Notification Received ===")
        Log.d(TAG, "Notification ID: ${notification.notificationId}")
        Log.d(TAG, "Additional Data: ${data?.toString()}")
        
        // Log all extras for debugging
        try {
            val extras = notification.additionalData
            if (extras != null) {
                val keys = extras.keys()
                val keyList = mutableListOf<String>()
                while (keys.hasNext()) {
                    keyList.add(keys.next())
                }
                Log.d(TAG, "Data keys: ${keyList.joinToString(", ")}")
                for (key in keyList) {
                    Log.d(TAG, "  $key = ${extras.get(key)}")
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error logging extras: ${e.message}")
        }

        // Check for CANCEL command
        if (data != null && data.has("type") && data.getString("type") == "cancel_chat") {
            val groupKey = data.optString("group_id") ?: data.optString("chat_group") ?: data.optString("collapse_id") ?: ""
            val phone = data.optString("phone") ?: ""
            val cleanPhone = data.optString("clean_phone") ?: data.optString("phone_clean") ?: phone.replace(Regex("[^0-9]"), "")
            
            Log.i(TAG, "🔔 CANCEL processing for group: $groupKey, phone: $phone, cleanPhone: $cleanPhone")
            Log.d(TAG, "Full data payload: ${data.toString()}")

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
                            val extraGroupId = extras.getString("group_id") ?: extras.getString("chat_group") ?: extras.getString("collapse_id")
                            val extraCleanPhone = extras.getString("clean_phone") ?: extras.getString("phone_clean")
                            
                            // Match by phone (original or clean)
                            if (extraPhone != null && phone.isNotEmpty()) {
                                val extraPhoneClean = extraPhone.replace(Regex("[^0-9]"), "")
                                if (extraPhone == phone || 
                                    extraPhoneClean == cleanPhone || 
                                    (cleanPhone.isNotEmpty() && extraPhoneClean == cleanPhone) ||
                                    (extraPhoneClean.isNotEmpty() && extraPhoneClean == cleanPhone)) {
                                    matched = true
                                    Log.d(TAG, "✅ Matched via direct phone extra: $extraPhone (clean: $extraPhoneClean)")
                                }
                            }
                            
                            // Match by clean_phone if available
                            if (!matched && extraCleanPhone != null && cleanPhone.isNotEmpty()) {
                                if (extraCleanPhone == cleanPhone) {
                                    matched = true
                                    Log.d(TAG, "✅ Matched via direct clean_phone extra: $extraCleanPhone")
                                }
                            }
                            
                            // Match by group_id
                            if (!matched && extraGroupId != null && groupKey.isNotEmpty()) {
                                if (extraGroupId == groupKey || 
                                    extraGroupId.contains(groupKey) || 
                                    groupKey.contains(extraGroupId)) {
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
                                            val customGroupId = additionalData.optString("group_id") ?: additionalData.optString("chat_group") ?: additionalData.optString("collapse_id")
                                            val customCleanPhone = additionalData.optString("clean_phone") ?: additionalData.optString("phone_clean")
                                            
                                            // Match by phone (original or clean)
                                            if (customPhone.isNotEmpty() && phone.isNotEmpty()) {
                                                val customPhoneClean = customPhone.replace(Regex("[^0-9]"), "")
                                                if (customPhone == phone || 
                                                    customPhoneClean == cleanPhone ||
                                                    (cleanPhone.isNotEmpty() && customPhoneClean == cleanPhone)) {
                                                    matched = true
                                                    Log.d(TAG, "✅ Matched via custom JSON (phone): $customPhone (clean: $customPhoneClean)")
                                                }
                                            }
                                            
                                            // Match by clean_phone
                                            if (!matched && customCleanPhone != null && cleanPhone.isNotEmpty()) {
                                                if (customCleanPhone == cleanPhone) {
                                                    matched = true
                                                    Log.d(TAG, "✅ Matched via custom JSON (clean_phone): $customCleanPhone")
                                                }
                                            }
                                            
                                            // Match by group_id
                                            if (!matched && customGroupId.isNotEmpty() && groupKey.isNotEmpty()) {
                                                if (customGroupId == groupKey || 
                                                    customGroupId.contains(groupKey) || 
                                                    groupKey.contains(customGroupId)) {
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
                                // Log for debugging - show what we're comparing
                                val debugPhone = extras.getString("phone") ?: "N/A"
                                val debugGroup = extras.getString("group_id") ?: extras.getString("chat_group") ?: "N/A"
                                val debugCustom = extras.getString("custom") ?: "N/A"
                                Log.v(TAG, "No match for SBN ID: ${statusBarNotification.id}")
                                Log.v(TAG, "  Looking for: phone=$phone, cleanPhone=$cleanPhone, groupKey=$groupKey")
                                Log.v(TAG, "  Found in extras: phone=$debugPhone, group=$debugGroup")
                                Log.v(TAG, "  Custom: ${if (debugCustom.length > 100) debugCustom.substring(0, 100) + "..." else debugCustom}")
                            }
                        } catch (e: Exception) {
                            Log.e(TAG, "Error checking notification ID ${statusBarNotification.id}: ${e.message}", e)
                        }
                    }
                    
                    // If no matches found but we have phone number, try aggressive matching
                    // This is a fallback for cases where data format doesn't match exactly
                    if (cancelledCount == 0 && (phone.isNotEmpty() || cleanPhone.isNotEmpty())) {
                        Log.w(TAG, "⚠️ No exact matches found. Trying aggressive phone-based cancellation...")
                        var aggressiveCount = 0
                        
                        for (statusBarNotification in activeNotifications) {
                            try {
                                val extras = statusBarNotification.notification.extras
                                val allExtras = extras.keySet()
                                
                                // Check if ANY extra contains the phone number (partial match)
                                var foundPhone = false
                                for (key in allExtras) {
                                    val value = extras.get(key)?.toString() ?: ""
                                    if (value.isNotEmpty()) {
                                        // Check if value contains phone (original or clean)
                                        if ((phone.isNotEmpty() && value.contains(phone)) ||
                                            (cleanPhone.isNotEmpty() && value.contains(cleanPhone))) {
                                            foundPhone = true
                                            break
                                        }
                                    }
                                }
                                
                                if (foundPhone) {
                                    val tag = statusBarNotification.tag ?: "onesignal"
                                    val id = statusBarNotification.id
                                    Log.i(TAG, "❌ Aggressive cancel: ID=$id, Tag=$tag (phone match found in extras)")
                                    notificationManager.cancel(tag, id)
                                    aggressiveCount++
                                }
                            } catch (e: Exception) {
                                Log.e(TAG, "Error in aggressive cancellation: ${e.message}")
                            }
                        }
                        
                        if (aggressiveCount > 0) {
                            Log.i(TAG, "🔔 Aggressive cleanup: Cancelled $aggressiveCount notification(s)")
                            cancelledCount = aggressiveCount
                        }
                    }
                    
                    // LAST RESORT: If still no matches, try to find by checking ALL extras
                    // for any mention of the phone number or groupKey
                    if (cancelledCount == 0 && (groupKey.isNotEmpty() || cleanPhone.isNotEmpty())) {
                        Log.w(TAG, "⚠️ Still no matches. Trying last resort: deep scan all extras...")
                        var lastResortCount = 0
                        
                        for (statusBarNotification in activeNotifications) {
                            try {
                                val extras = statusBarNotification.notification.extras
                                var shouldCancel = false
                                
                                // Deep scan: check every value in extras
                                for (key in extras.keySet()) {
                                    val value = extras.get(key)?.toString() ?: ""
                                    
                                    // Check if value contains our identifiers
                                    if (value.isNotEmpty()) {
                                        // Check for groupKey
                                        if (groupKey.isNotEmpty() && value.contains(groupKey)) {
                                            shouldCancel = true
                                            Log.d(TAG, "Last resort match: found groupKey in key=$key")
                                            break
                                        }
                                        
                                        // Check for clean phone (more reliable than original phone)
                                        if (cleanPhone.isNotEmpty() && cleanPhone.length >= 8) {
                                            // Check if value contains the phone number
                                            if (value.contains(cleanPhone)) {
                                                shouldCancel = true
                                                Log.d(TAG, "Last resort match: found cleanPhone in key=$key")
                                                break
                                            }
                                        }
                                    }
                                }
                                
                                if (shouldCancel) {
                                    val tag = statusBarNotification.tag ?: "onesignal"
                                    val id = statusBarNotification.id
                                    Log.i(TAG, "❌ Last resort cancel: ID=$id, Tag=$tag")
                                    notificationManager.cancel(tag, id)
                                    lastResortCount++
                                }
                            } catch (e: Exception) {
                                Log.e(TAG, "Error in last resort cancellation: ${e.message}")
                            }
                        }
                        
                        if (lastResortCount > 0) {
                            Log.w(TAG, "🔔 Last resort cleanup: Cancelled $lastResortCount notification(s)")
                            cancelledCount = lastResortCount
                        }
                    }
                    
                    Log.i(TAG, "🔔 Cleanup complete. Total cancelled: $cancelledCount notification(s) for group: $groupKey")
                     
                } catch (e: Exception) {
                    Log.e(TAG, "Error in notification manager: ${e.message}", e)
                }
            } else {
                Log.w(TAG, "⚠️ Cannot cancel: groupKey is empty")
            }
            
            // Prevent THIS notification from showing (Silent)
            // Since we used collapse_id to replace the old notification,
            // preventing this one will effectively remove the notification
            event.preventDefault()
            Log.i(TAG, "✅ Cancel notification prevented from showing (replaced old notification)")
            return
        }
        
        // If this is NOT a cancel notification, log it for debugging
        Log.d(TAG, "Regular notification received (not cancel_chat)")
    }
}
