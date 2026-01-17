package com.cms.mdl

import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.SharedPreferences
import android.graphics.Color
import android.os.Build
import android.util.Log
import androidx.core.app.NotificationCompat

/**
 * Helper class for managing native notifications
 * - Creates notifications with unique IDs based on phone number
 * - Stores phone -> notificationId mapping for cancellation
 * - Supports WhatsApp-style message stacking
 */
object NotificationHelper {
    
    private const val TAG = "NotificationHelper"
    private const val PREFS_NAME = "notification_ids"
    private const val PREFS_MESSAGES = "notification_messages"
    private const val CHANNEL_ID = "7bf3bcb6-e151-4e8a-ae41-fd98692b80a3"
    private const val GROUP_KEY = "com.cms.mdl.CHAT_GROUP"
    
    // Base notification ID (we use phone hash as offset)
    private const val BASE_NOTIFICATION_ID = 1000
    
    // Summary notification ID for grouped notifications
    private const val SUMMARY_NOTIFICATION_ID = 0
    
    private fun getPrefs(context: Context): SharedPreferences {
        return context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
    }
    
    private fun getMessagePrefs(context: Context): SharedPreferences {
        return context.getSharedPreferences(PREFS_MESSAGES, Context.MODE_PRIVATE)
    }
    
    /**
     * Generate a stable notification ID from phone number
     * Uses hash to ensure same phone always gets same ID
     */
    fun getNotificationId(cleanPhone: String): Int {
        if (cleanPhone.isEmpty()) return BASE_NOTIFICATION_ID
        
        // Use absolute value of hashCode + base to avoid negative IDs
        val hash = cleanPhone.hashCode()
        val id = BASE_NOTIFICATION_ID + (hash and 0x7FFFFFFF) % 100000
        Log.d(TAG, "Generated notificationId for $cleanPhone: $id")
        return id
    }
    
    /**
     * Store notification ID for a phone number
     */
    fun saveNotificationId(context: Context, cleanPhone: String, notificationId: Int) {
        getPrefs(context).edit().putInt(cleanPhone, notificationId).apply()
        Log.d(TAG, "Saved notificationId: $cleanPhone -> $notificationId")
    }
    
    /**
     * Get stored notification ID for a phone number
     */
    fun getStoredNotificationId(context: Context, cleanPhone: String): Int? {
        val prefs = getPrefs(context)
        return if (prefs.contains(cleanPhone)) {
            prefs.getInt(cleanPhone, -1)
        } else {
            null
        }
    }
    
    /**
     * Remove notification ID mapping
     */
    fun removeNotificationId(context: Context, cleanPhone: String) {
        getPrefs(context).edit().remove(cleanPhone).apply()
        Log.d(TAG, "Removed notificationId mapping for: $cleanPhone")
    }
    
    /**
     * Add message to history for stacking display
     */
    fun addMessageToHistory(context: Context, cleanPhone: String, message: String) {
        val prefs = getMessagePrefs(context)
        val existing = prefs.getString(cleanPhone, "") ?: ""
        
        // Keep last 10 messages, separated by newline
        val messages = existing.split("\n").filter { it.isNotEmpty() }.toMutableList()
        messages.add(message)
        
        // Keep only last 10
        while (messages.size > 10) {
            messages.removeAt(0)
        }
        
        prefs.edit().putString(cleanPhone, messages.joinToString("\n")).apply()
    }
    
    /**
     * Get message history for display
     */
    fun getMessageHistory(context: Context, cleanPhone: String): List<String> {
        val prefs = getMessagePrefs(context)
        val messages = prefs.getString(cleanPhone, "") ?: ""
        return messages.split("\n").filter { it.isNotEmpty() }
    }
    
    /**
     * Clear message history for a phone
     */
    fun clearMessageHistory(context: Context, cleanPhone: String) {
        getMessagePrefs(context).edit().remove(cleanPhone).apply()
    }
    
    /**
     * Get message count for a phone
     */
    fun getMessageCount(context: Context, cleanPhone: String): Int {
        return getMessageHistory(context, cleanPhone).size
    }
    
    /**
     * Create and show a native notification
     */
    fun showNotification(
        context: Context,
        cleanPhone: String,
        title: String,
        body: String,
        singleMessage: String? = null
    ) {
        val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        val notificationId = getNotificationId(cleanPhone)
        
        // Save the ID mapping
        saveNotificationId(context, cleanPhone, notificationId)
        
        // Add message to history
        val messageToAdd = singleMessage ?: body
        if (messageToAdd.isNotBlank()) {
            addMessageToHistory(context, cleanPhone, messageToAdd.take(100))
        }
        
        // Get all messages for inbox style
        val messages = getMessageHistory(context, cleanPhone)
        val messageCount = messages.size
        
        // Create intent for notification click
        val intent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            putExtra("phone", cleanPhone)
        }
        
        val pendingIntent = PendingIntent.getActivity(
            context,
            notificationId,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        
        // Build notification with inbox style for multiple messages
        // Note: Phone data is stored in Intent (pendingIntent) for cancellation matching
        // We also use stable notification ID (based on phone hash) for reliable cancellation
        val builder = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_launcher_foreground) // App icon
            .setContentTitle(title)
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .setGroup(GROUP_KEY)
            .setColor(Color.parseColor("#6366F1"))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_MESSAGE)
            .setDefaults(NotificationCompat.DEFAULT_ALL) // Sound, vibrate, lights
        
        if (messageCount > 1) {
            // Multiple messages: use inbox style
            val inboxStyle = NotificationCompat.InboxStyle()
                .setBigContentTitle(title)
                .setSummaryText("$messageCount pesan baru")
            
            // Add last few messages (most recent last)
            messages.takeLast(5).forEach { msg ->
                inboxStyle.addLine(msg)
            }
            
            builder.setStyle(inboxStyle)
                .setContentText("$messageCount pesan baru")
                .setNumber(messageCount)
        } else {
            // Single message: simple text with big text style for long messages
            builder.setContentText(body)
                .setStyle(NotificationCompat.BigTextStyle().bigText(body))
        }
        
        // Show the notification
        notificationManager.notify(notificationId, builder.build())
        Log.i(TAG, "✅ Showed notification ID $notificationId for $cleanPhone (${messageCount} messages)")
        
        // Also update group summary if API 24+
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
            showGroupSummary(context, notificationManager)
        }
    }
    
    /**
     * Show group summary notification for Android 7+
     */
    private fun showGroupSummary(context: Context, notificationManager: NotificationManager) {
        val summaryBuilder = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_launcher_foreground)
            .setGroup(GROUP_KEY)
            .setGroupSummary(true)
            .setAutoCancel(true)
            .setColor(Color.parseColor("#6366F1"))
        
        notificationManager.notify(SUMMARY_NOTIFICATION_ID, summaryBuilder.build())
    }
    
    /**
     * Cancel notification for a specific phone - AGGRESSIVE approach
     * Scans all active notifications to ensure complete removal
     */
    fun cancelNotification(context: Context, cleanPhone: String): Boolean {
        val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        var cancelledCount = 0
        
        Log.i(TAG, "🔇 Cancelling notification for: $cleanPhone")
        
        // Method 1: Cancel using stored ID
        val storedId = getStoredNotificationId(context, cleanPhone)
        if (storedId != null && storedId != -1) {
            try {
                notificationManager.cancel(storedId)
                cancelledCount++
                Log.d(TAG, "✅ Cancelled stored ID: $storedId")
            } catch (e: Exception) {
                Log.e(TAG, "Error cancelling stored ID $storedId: ${e.message}")
            }
        }
        
        // Method 2: Cancel using calculated ID (stable hash)
        val calculatedId = getNotificationId(cleanPhone)
        try {
            notificationManager.cancel(calculatedId)
            if (calculatedId != storedId) {
                cancelledCount++
                Log.d(TAG, "✅ Cancelled calculated ID: $calculatedId")
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error cancelling calculated ID $calculatedId: ${e.message}")
        }
        
        // Method 3: AGGRESSIVE - Scan ALL active notifications and cancel by matching phone
        // This ensures we catch any notification that might have been created differently
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            try {
                val activeNotifications = notificationManager.activeNotifications
                Log.d(TAG, "Scanning ${activeNotifications.size} active notifications...")
                
                for (sbn in activeNotifications) {
                    val n = sbn.notification
                    val extras = n.extras
                    var shouldCancel = false
                    
                    // Check in extras for phone matches
                    val extraPhone = extras?.getString("phone") ?: ""
                    val extraCleanPhone = extras?.getString("clean_phone") 
                        ?: extras?.getString("phone_clean") 
                        ?: ""
                    val extraGroupId = extras?.getString("group_id") 
                        ?: extras?.getString("chat_group") 
                        ?: ""
                    
                    // Match by clean phone (most reliable)
                    if (cleanPhone.isNotEmpty()) {
                        val extraClean = extraPhone.replace(Regex("[^0-9]"), "")
                        if (extraClean == cleanPhone || extraCleanPhone == cleanPhone) {
                            shouldCancel = true
                            Log.d(TAG, "✅ Found match in extras: phone=$extraPhone")
                        }
                        
                        // Match by group_id (contains phone)
                        val groupKey = "chat_$cleanPhone"
                        if (extraGroupId == groupKey || extraGroupId.contains(cleanPhone)) {
                            shouldCancel = true
                            Log.d(TAG, "✅ Found match in group_id: $extraGroupId")
                        }
                    }
                    
                    // Check in custom JSON string (OneSignal format)
                    if (!shouldCancel) {
                        val custom = extras?.getString("custom")
                        if (custom != null && cleanPhone.isNotEmpty()) {
                            try {
                                val customJson = org.json.JSONObject(custom)
                                val additionalData = customJson.optJSONObject("a")
                                if (additionalData != null) {
                                    val customPhone = additionalData.optString("phone", "")
                                    val customCleanPhone = additionalData.optString("clean_phone", "")
                                    val customGroupId = additionalData.optString("group_id", "")
                                    
                                    val customClean = customPhone.replace(Regex("[^0-9]"), "")
                                    if (customClean == cleanPhone || customCleanPhone == cleanPhone) {
                                        shouldCancel = true
                                        Log.d(TAG, "✅ Found match in custom JSON: phone=$customPhone")
                                    }
                                    
                                    val groupKey = "chat_$cleanPhone"
                                    if (customGroupId == groupKey || customGroupId.contains(cleanPhone)) {
                                        shouldCancel = true
                                        Log.d(TAG, "✅ Found match in custom group_id: $customGroupId")
                                    }
                                }
                            } catch (e: Exception) {
                                // Try simple string contains as fallback
                                if (custom.contains(cleanPhone)) {
                                    shouldCancel = true
                                    Log.d(TAG, "✅ Found match in custom string contains")
                                }
                            }
                        }
                    }
                    
                    // Last resort: Check title/content for phone match (for older notifications)
                    if (!shouldCancel && cleanPhone.length >= 8) {
                        val title = extras?.getCharSequence(android.app.Notification.EXTRA_TITLE)?.toString() ?: ""
                        val text = extras?.getCharSequence(android.app.Notification.EXTRA_TEXT)?.toString() ?: ""
                        val bigText = extras?.getCharSequence(android.app.Notification.EXTRA_BIG_TEXT)?.toString() ?: ""
                        
                        // Check if phone appears in any text content
                        val allText = "$title $text $bigText"
                        if (allText.contains(cleanPhone) || allText.contains("chat_$cleanPhone")) {
                            shouldCancel = true
                            Log.d(TAG, "✅ Found match in notification text content")
                        }
                    }
                    
                    // Cancel if matched
                    if (shouldCancel) {
                        val tag = sbn.tag
                        val id = sbn.id
                        if (tag != null) {
                            notificationManager.cancel(tag, id)
                        } else {
                            notificationManager.cancel(id)
                        }
                        cancelledCount++
                        Log.d(TAG, "❌ Aggressively cancelled: tag=$tag, id=$id")
                    }
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error scanning active notifications: ${e.message}", e)
            }
        }
        
        // Clean up stored data
        removeNotificationId(context, cleanPhone)
        clearMessageHistory(context, cleanPhone)
        
        // Update group summary if needed (cancel it if no more notifications in group)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
            try {
                val activeNotifications = notificationManager.activeNotifications
                val hasGroupNotifications = activeNotifications.any { sbn ->
                    // Check if notification is in our chat group
                    val extras = sbn.notification.extras
                    val isChatGroup = sbn.notification.group == GROUP_KEY || 
                                     extras?.getString("group_id") == GROUP_KEY ||
                                     extras?.getString("chat_group") == GROUP_KEY
                    
                    // Exclude the summary notification itself
                    val isNotSummary = sbn.id != SUMMARY_NOTIFICATION_ID
                    
                    isChatGroup && isNotSummary
                }
                
                // If no more group notifications, cancel summary
                if (!hasGroupNotifications) {
                    notificationManager.cancel(SUMMARY_NOTIFICATION_ID)
                    Log.d(TAG, "✅ Cancelled group summary (no more group notifications)")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error updating group summary: ${e.message}")
            }
        }
        
        // Final verification: List remaining notifications for debugging
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            try {
                val remaining = notificationManager.activeNotifications
                    .filter { sbn ->
                        val extras = sbn.notification.extras
                        val extraPhone = extras?.getString("phone") ?: ""
                        val extraCleanPhone = extras?.getString("clean_phone") ?: ""
                        val phoneClean = extraPhone.replace(Regex("[^0-9]"), "")
                        
                        phoneClean == cleanPhone || extraCleanPhone == cleanPhone
                    }
                
                if (remaining.isNotEmpty()) {
                    Log.w(TAG, "⚠️ WARNING: ${remaining.size} notification(s) still remain after cancellation:")
                    remaining.forEach { sbn ->
                        Log.w(TAG, "  - ID: ${sbn.id}, Tag: ${sbn.tag}, Title: ${sbn.notification.extras?.getCharSequence(android.app.Notification.EXTRA_TITLE)}")
                    }
                } else {
                    Log.d(TAG, "✅ Verification: No remaining notifications for $cleanPhone")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error verifying cancellation: ${e.message}")
            }
        }
        
        Log.i(TAG, "✅ Cancellation complete: $cancelledCount notification(s) removed for $cleanPhone")
        return cancelledCount > 0
    }
    
    /**
     * Cancel all chat notifications
     */
    fun cancelAllNotifications(context: Context) {
        val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        
        // Clear all stored IDs
        getPrefs(context).edit().clear().apply()
        getMessagePrefs(context).edit().clear().apply()
        
        // Cancel all notifications from this app
        notificationManager.cancelAll()
        Log.i(TAG, "❌ Cancelled ALL notifications")
    }
}
