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
        private const val CHANNEL_ID = "mdl_chat_whatsapp_inbox"
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
        OneSignal.initWithContext(this, ONESIGNAL_APP_ID)

        // Request notification permission (Android 13+)
        applicationScope.launch {
            OneSignal.Notifications.requestPermission(true)
        }
    }

    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val manager = getSystemService(NotificationManager::class.java)
            
            // 🔄 DELETE existing channel first to ensure sound settings are applied
            // Android doesn't update channel settings if channel already exists
            // This forces recreation with latest sound configuration
            try {
                val existingChannel = manager.getNotificationChannel(CHANNEL_ID)
                if (existingChannel != null) {
                    // Check if sound is disabled on existing channel
                    if (existingChannel.sound == null || existingChannel.importance < NotificationManager.IMPORTANCE_HIGH) {
                        android.util.Log.d("MainApplication", "Deleting existing channel to apply new sound settings")
                        manager.deleteNotificationChannel(CHANNEL_ID)
                    }
                }
            } catch (e: Exception) {
                android.util.Log.e("MainApplication", "Error checking existing channel: ${e.message}")
            }

            val channel = NotificationChannel(
                CHANNEL_ID,
                "Chat WhatsApp Masuk",
                NotificationManager.IMPORTANCE_HIGH // HIGH = heads-up + sound
            ).apply {
                description = "Notifikasi pesan WhatsApp masuk ke CMS"
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
