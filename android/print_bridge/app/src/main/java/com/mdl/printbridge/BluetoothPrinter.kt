package com.mdl.printbridge

import android.annotation.SuppressLint
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothSocket
import android.util.Log
import java.io.IOException
import java.util.UUID
import java.util.concurrent.Executors
import java.util.concurrent.TimeUnit
import java.util.concurrent.locks.ReentrantLock
import kotlin.concurrent.withLock

/**
 * Classic Bluetooth SPP writer for ESC/POS thermal printers.
 */
class BluetoothPrinter {
    companion object {
        private const val TAG = "BluetoothPrinter"
        private val SPP_UUID: UUID =
            UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")
        private const val CHUNK = 512
    }

    private val lock = ReentrantLock()
    private val executor = Executors.newSingleThreadExecutor()
    @Volatile private var socket: BluetoothSocket? = null
    @Volatile private var connectedAddress: String? = null

    @SuppressLint("MissingPermission")
    fun write(address: String, data: ByteArray, timeoutMs: Long = 15000L) {
        val future = executor.submit<Unit> {
            lock.withLock {
                ensureConnected(address)
                val out = socket?.outputStream
                    ?: throw IOException("Bluetooth socket tidak siap")
                var offset = 0
                while (offset < data.size) {
                    val end = minOf(offset + CHUNK, data.size)
                    out.write(data, offset, end - offset)
                    out.flush()
                    offset = end
                    // Small pause helps some cheap printers
                    if (offset < data.size) Thread.sleep(20)
                }
            }
        }
        try {
            future.get(timeoutMs, TimeUnit.MILLISECONDS)
        } catch (e: Exception) {
            closeQuietly()
            throw IOException(e.message ?: "Gagal kirim ke printer Bluetooth", e)
        }
    }

    @SuppressLint("MissingPermission")
    private fun ensureConnected(address: String) {
        if (socket?.isConnected == true && connectedAddress == address) return
        closeQuietly()

        val adapter = BluetoothAdapter.getDefaultAdapter()
            ?: throw IOException("Bluetooth tidak tersedia")
        if (!adapter.isEnabled) throw IOException("Bluetooth mati")

        val device: BluetoothDevice = try {
            adapter.getRemoteDevice(address)
        } catch (e: IllegalArgumentException) {
            throw IOException("Alamat printer tidak valid: $address", e)
        }

        var lastError: Exception? = null
        // Try secure then insecure RFCOMM
        for (secure in listOf(true, false)) {
            try {
                val s = if (secure) {
                    device.createRfcommSocketToServiceRecord(SPP_UUID)
                } else {
                    device.createInsecureRfcommSocketToServiceRecord(SPP_UUID)
                }
                adapter.cancelDiscovery()
                s.connect()
                socket = s
                connectedAddress = address
                Log.i(TAG, "Connected to $address (secure=$secure)")
                return
            } catch (e: Exception) {
                lastError = e
                Log.w(TAG, "Connect secure=$secure failed: ${e.message}")
                try {
                    // Fallback reflection channel 1 (common for cheap printers)
                    if (secure) {
                        val m = device.javaClass.getMethod("createRfcommSocket", Int::class.javaPrimitiveType)
                        val s = m.invoke(device, 1) as BluetoothSocket
                        adapter.cancelDiscovery()
                        s.connect()
                        socket = s
                        connectedAddress = address
                        Log.i(TAG, "Connected via reflection channel 1")
                        return
                    }
                } catch (e2: Exception) {
                    lastError = e2
                }
            }
        }
        throw IOException("Tidak bisa connect ke printer $address: ${lastError?.message}")
    }

    fun closeQuietly() {
        try {
            socket?.close()
        } catch (_: Exception) {
        }
        socket = null
        connectedAddress = null
    }

    fun shutdown() {
        closeQuietly()
        executor.shutdownNow()
    }
}
