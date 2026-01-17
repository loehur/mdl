package com.cms.mdl

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationManager
import android.media.AudioAttributes
import android.os.Build
import android.provider.Settings
import com.onesignal.OneSignal
import com.onesignal.debug.LogLevel
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class MainApplication : Application() {

    companion object {
        private const val ONESIGNAL_APP_ID = "ea86398e-163f-4f16-b227-c596272b072c"
        // This MUST match the channel ID registered in OneSignal Dashboard
        private const val CHANNEL_ID = "7bf3bcb6-e151-4e8a-ae41-fd98692b80a3"
    }

    private val applicationScope = CoroutineScope(Dispatchers.IO)

    override fun onCreate() {
        super.onCreate()

        // 🔔 Create notification channel BEFORE OneSignal init (Android 8+)
        // This ensures our channel with sound is used
        createNotificationChannel()

        // OneSignal logging (set to WARN in production)
        OneSignal.Debug.logLevel = LogLevel.VERBOSE

        // OneSignal init
        // NOTE: Channel is already created above, and AndroidManifest meta-data forces OneSignal to use our CHANNEL_ID
        // No need to cleanup after init - it could interfere with notifications
        OneSignal.initWithContext(this, ONESIGNAL_APP_ID)

        // Request notification permission (Android 13+)
        applicationScope.launch {
            OneSignal.Notifications.requestPermission(true)
        }
    }

    /**
     * Clean up old notification channels - keep only our CHANNEL_ID
     * Called BEFORE OneSignal init to prevent OneSignal from using old channels
     */
    private fun cleanupOldChannels() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val manager = getSystemService(NotificationManager::class.java)
            
            try {
                val existingChannels = manager.notificationChannels
                android.util.Log.d("MainApplication", "Found ${existingChannels.size} existing notification channels")
                
                var deletedCount = 0
                for (channel in existingChannels) {
                    // Delete ALL channels except our current one
                    if (channel.id != CHANNEL_ID) {
                        android.util.Log.i("MainApplication", "🗑️ Deleting old channel: ${channel.id} (${channel.name})")
                        manager.deleteNotificationChannel(channel.id)
                        deletedCount++
                    } else {
                        android.util.Log.d("MainApplication", "✅ Keeping our channel: ${channel.id} (${channel.name})")
                    }
                }
                
                if (deletedCount > 0) {
                    android.util.Log.i("MainApplication", "✅ Cleaned up $deletedCount old notification channel(s)")
                }
            } catch (e: Exception) {
                android.util.Log.e("MainApplication", "❌ Error deleting old channels: ${e.message}", e)
            }
        }
    }
    
    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val manager = getSystemService(NotificationManager::class.java)

            // 🔄 DELETE all other notification channels to keep only ONE channel: "MDL Cases"
            // Android keeps channels even after app uninstall, so we must clean them up on each app start
            // This removes old channels like "MDL Chat - Customer", "MDL Chat Channel", "Chat WhatsApp Masuk", etc.
            cleanupOldChannels()

            // 🔄 DELETE existing channel first to ensure sound settings are applied
            // Android doesn't update channel settings if channel already exists
            // This forces recreation with latest sound configuration
            try {
                val existingChannel = manager.getNotificationChannel(CHANNEL_ID)
                if (existingChannel != null) {
                    // Delete if name is different (to update name) or settings are wrong
                    if (existingChannel.name != "MDL Cases" || 
                        existingChannel.sound == null || 
                        existingChannel.importance < NotificationManager.IMPORTANCE_HIGH) {
                        android.util.Log.d("MainApplication", "Deleting existing channel to apply new name/settings")
                        manager.deleteNotificationChannel(CHANNEL_ID)
                    }
                }
            } catch (e: Exception) {
                android.util.Log.e("MainApplication", "Error checking existing channel: ${e.message}")
            }

            val channel = NotificationChannel(
                CHANNEL_ID,
                "MDL Cases",
                NotificationManager.IMPORTANCE_HIGH // HIGH = heads-up + sound
            ).apply {
                description = "Notifikasi untuk semua case dan pesan WhatsApp masuk"
                enableVibration(true)
                vibrationPattern = longArrayOf(0, 250, 250, 250) // Vibration pattern
                setShowBadge(true)
                enableLights(true)
                lightColor = android.graphics.Color.parseColor("#6366F1") // Indigo
                lockscreenVisibility = android.app.Notification.VISIBILITY_PUBLIC

                // 🔊 Set notification sound explicitly using system default
                val soundUri = Settings.System.DEFAULT_NOTIFICATION_URI
                val audioAttributes = AudioAttributes.Builder()
                    .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                    .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                    .build()
                setSound(soundUri, audioAttributes)
            }

            manager.createNotificationChannel(channel)

            // Log channel details for debugging
            val createdChannel = manager.getNotificationChannel(CHANNEL_ID)
            android.util.Log.d("MainApplication", """
                Notification channel created/verified:
                - ID: $CHANNEL_ID
                - Sound: ${createdChannel?.sound}
                - Importance: ${createdChannel?.importance}
                - Vibration: ${createdChannel?.shouldVibrate()}
            """.trimIndent())
        }
    }
}
