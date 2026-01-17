package com.cms.mdl

import android.app.Application
import com.onesignal.OneSignal
import com.onesignal.debug.LogLevel
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class MainApplication : Application() {

    companion object {
        private const val ONESIGNAL_APP_ID = "ea86398e-163f-4f16-b227-c596272b072c"
    }

    private val applicationScope = CoroutineScope(Dispatchers.IO)

    override fun onCreate() {
        super.onCreate()

        // OneSignal logging (set to WARN in production)
        OneSignal.Debug.logLevel = LogLevel.VERBOSE

        // OneSignal init - will create notification channels automatically
        OneSignal.initWithContext(this, ONESIGNAL_APP_ID)

        // Request notification permission (Android 13+)
        applicationScope.launch {
            OneSignal.Notifications.requestPermission(true)
        }
    }
}
