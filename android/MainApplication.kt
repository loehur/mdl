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

        // 🔔 Create notification channel (Android 8+)
        createNotificationChannel()

        // OneSignal logging
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

            val channel = NotificationChannel(
                CHANNEL_ID,
                "Chat WhatsApp Masuk",
                NotificationManager.IMPORTANCE_HIGH // HIGH = heads-up + sound
            ).apply {
                description = "Notifikasi pesan WhatsApp masuk ke CMS"
                enableVibration(true)
                setShowBadge(true)
                enableLights(true)
                lightColor = android.graphics.Color.parseColor("#6366F1") // Indigo
                
                // 🔊 Set notification sound explicitly
                val soundUri = Settings.System.DEFAULT_NOTIFICATION_URI
                val audioAttributes = AudioAttributes.Builder()
                    .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                    .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                    .build()
                setSound(soundUri, audioAttributes)
            }

            val manager = getSystemService(NotificationManager::class.java)
            manager.createNotificationChannel(channel)
            
            android.util.Log.d("MainApplication", "Notification channel created: $CHANNEL_ID with sound")
        }
    }
}

