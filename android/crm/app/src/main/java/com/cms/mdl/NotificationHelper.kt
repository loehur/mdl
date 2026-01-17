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
     * Cancel notification for a specific phone
     */
    fun cancelNotification(context: Context, cleanPhone: String): Boolean {
        val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        
        // First try stored ID
        val storedId = getStoredNotificationId(context, cleanPhone)
        if (storedId != null) {
            notificationManager.cancel(storedId)
            removeNotificationId(context, cleanPhone)
            clearMessageHistory(context, cleanPhone)
            Log.i(TAG, "❌ Cancelled notification ID $storedId for $cleanPhone (stored)")
            return true
        }
        
        // Fallback: calculate ID from phone
        val calculatedId = getNotificationId(cleanPhone)
        notificationManager.cancel(calculatedId)
        clearMessageHistory(context, cleanPhone)
        Log.i(TAG, "❌ Cancelled notification ID $calculatedId for $cleanPhone (calculated)")
        
        return true
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
