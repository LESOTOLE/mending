<?php
/**
 * Helper untuk mengirim pesan WhatsApp via Fonnte API
 */

// Load token dari file credentials terpisah (agar bisa di-gitignore)
require_once __DIR__ . '/credentials.php';

/**
 * Fungsi untuk mengirim pesan WhatsApp
 * 
 * @param string $target Nomor HP tujuan (contoh: 08123456789 atau 628123456789)
 * @param string $message Isi pesan yang akan dikirim
 * @return array Hasil response dari Fonnte (status, message)
 */
function sendWhatsAppFonnte($target, $message) {
    // Bersihkan nomor (hilangkan spasi, strip, dll)
    $target = preg_replace('/[^0-9]/', '', $target);
    
    // Jika diawali dengan 0, ubah ke 62
    if (substr($target, 0, 1) == '0') {
        $target = '62' . substr($target, 1);
    }
    
    // MOCKING (SIMULASI PENGIRIMAN) - Aktif jika token masih default/placeholder
    if (FONNTE_TOKEN == '__FONNTE_TOKEN_NOT_CONFIGURED__' || FONNTE_TOKEN == '') {
        error_log("WA_MOCK_SENT to $target: $message");
        return ['status' => true, 'message' => 'Token belum diatur. Pesan disimpan di log.'];
    }
    
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.fonnte.com/send',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 5, // Tambahan timeout agar tidak hang saat offline
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => array(
        'target' => $target,
        'message' => $message,
        'delay' => '1',
        'countryCode' => '62', // Default kode negara
      ),
      CURLOPT_HTTPHEADER => array(
        'Authorization: ' . FONNTE_TOKEN
      ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return ['status' => false, 'message' => 'cURL Error #: ' . $err];
    } else {
        $res = json_decode($response, true);
        if (isset($res['status']) && $res['status'] == true) {
            return ['status' => true, 'message' => 'Pesan terkirim'];
        } else {
            return ['status' => false, 'message' => $res['reason'] ?? 'Gagal mengirim pesan'];
        }
    }
}
?>
