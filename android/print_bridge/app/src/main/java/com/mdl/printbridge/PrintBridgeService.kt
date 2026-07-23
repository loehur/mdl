package com.mdl.printbridge

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.IBinder
import android.util.Log
import androidx.core.app.NotificationCompat

class PrintBridgeService : Service() {

    companion object {
        const val ACTION_START = "com.mdl.printbridge.START"
        const val ACTION_STOP = "com.mdl.printbridge.STOP"
        const val ACTION_STATUS = "com.mdl.printbridge.STATUS"
        const val EXTRA_RUNNING = "running"
        const val EXTRA_LOG = "log"
        private const val NOTIF_ID = 3000
        private const val CHANNEL_ID = "print_bridge"
        private const val TAG = "PrintBridgeService"

        @Volatile
        var isRunning: Boolean = false
            private set

        fun start(context: Context) {
            val i = Intent(context, PrintBridgeService::class.java).setAction(ACTION_START)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(i)
            } else {
                context.startService(i)
            }
        }

        fun stop(context: Context) {
            context.startService(
                Intent(context, PrintBridgeService::class.java).setAction(ACTION_STOP)
            )
        }
    }

    private var httpServer: PrintHttpServer? = null
    private val bluetoothPrinter = BluetoothPrinter()
    private lateinit var prefs: Prefs

    override fun onCreate() {
        super.onCreate()
        prefs = Prefs(this)
        createChannel()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        when (intent?.action) {
            ACTION_STOP -> {
                stopServer()
                stopForeground(STOP_FOREGROUND_REMOVE)
                stopSelf()
                return START_NOT_STICKY
            }
            else -> {
                startForeground(NOTIF_ID, buildNotification())
                startServer()
            }
        }
        return START_STICKY
    }

    private fun startServer() {
        if (httpServer != null) {
            broadcastStatus(true)
            return
        }
        try {
            if (!PrintHttpServer.isPortFree()) {
                Log.w(TAG, "Port 3000 already in use")
                broadcastLog("Port 3000 sudah dipakai aplikasi lain")
            }
            val server = PrintHttpServer(prefs, bluetoothPrinter) { msg ->
                Log.i(TAG, msg)
                broadcastLog(msg)
            }
            server.start(30_000, false)
            httpServer = server
            isRunning = true
            broadcastStatus(true)
            broadcastLog("Server started on 127.0.0.1:3000")
        } catch (e: Exception) {
            Log.e(TAG, "Failed to start HTTP server", e)
            broadcastLog("Gagal start server: ${e.message}")
            isRunning = false
            broadcastStatus(false)
            stopForeground(STOP_FOREGROUND_REMOVE)
            stopSelf()
        }
    }

    private fun stopServer() {
        try {
            httpServer?.stop()
        } catch (_: Exception) {
        }
        httpServer = null
        bluetoothPrinter.closeQuietly()
        isRunning = false
        broadcastStatus(false)
        broadcastLog("Server stopped")
    }

    override fun onDestroy() {
        stopServer()
        bluetoothPrinter.shutdown()
        super.onDestroy()
    }

    override fun onBind(intent: Intent?): IBinder? = null

    private fun broadcastStatus(running: Boolean) {
        sendBroadcast(
            Intent(ACTION_STATUS)
                .setPackage(packageName)
                .putExtra(EXTRA_RUNNING, running)
        )
    }

    private fun broadcastLog(msg: String) {
        sendBroadcast(
            Intent(ACTION_STATUS)
                .setPackage(packageName)
                .putExtra(EXTRA_RUNNING, isRunning)
                .putExtra(EXTRA_LOG, msg)
        )
    }

    private fun createChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val ch = NotificationChannel(
                CHANNEL_ID,
                getString(R.string.notification_channel),
                NotificationManager.IMPORTANCE_LOW
            )
            ch.description = getString(R.string.notification_text)
            getSystemService(NotificationManager::class.java).createNotificationChannel(ch)
        }
    }

    private fun buildNotification(): Notification {
        val open = PendingIntent.getActivity(
            this,
            0,
            Intent(this, MainActivity::class.java),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle(getString(R.string.notification_title))
            .setContentText(getString(R.string.notification_text))
            .setSmallIcon(R.drawable.ic_launcher)
            .setContentIntent(open)
            .setOngoing(true)
            .build()
    }
}
