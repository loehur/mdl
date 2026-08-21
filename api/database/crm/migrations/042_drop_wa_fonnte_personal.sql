-- Hapus tabel chat personal Fonnte (sudah diganti dual-line YCloud + wa_csw_by_line).
-- Jalankan SETELAH 041_wa_dual_ycloud_lines.sql (CSW admin sudah dimigrasi ke wa_csw_by_line).
--
-- Opsional — cek jumlah baris sebelum drop:
-- SELECT 'wa_fonnte_messages_in' AS t, COUNT(*) AS c FROM wa_fonnte_messages_in
-- UNION ALL SELECT 'wa_fonnte_messages_out', COUNT(*) FROM wa_fonnte_messages_out
-- UNION ALL SELECT 'wa_fonnte_conversations', COUNT(*) FROM wa_fonnte_conversations
-- UNION ALL SELECT 'wa_fonnte_csw', COUNT(*) FROM wa_fonnte_csw;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS wa_fonnte_messages_in;
DROP TABLE IF EXISTS wa_fonnte_messages_out;
DROP TABLE IF EXISTS wa_fonnte_conversations;
DROP TABLE IF EXISTS wa_fonnte_csw;

SET FOREIGN_KEY_CHECKS = 1;
