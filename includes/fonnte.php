<?php
/**
 * Helper untuk mengirim pesan WhatsApp via Fonnte API
 */

// GANTI DENGAN TOKEN FONNTE ANDA
define('FONNTE_TOKEN', 'ec2w8n8mDfQYfGMxTF3C');

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
    
    // MOCKING (SIMULASI PENGIRIMAN) - BATALKAN KOMENTAR JIKA INGIN TESTING TANPA MENGHABISKAN KUOTA WA
    // Ganti 'TOKEN_FONNTE_ANDA_DISINI' dengan string kosong atau placeholder jika ingin mengaktifkan mode simulasi
    if (FONNTE_TOKEN == 'ec2w8n8mDfQYfGMxTF3C' || FONNTE_TOKEN == '') {
        error_log("WA_MOCK_SENT to $target: $message");
        return ['status' => true, 'message' => 'Token belum diatur. Pesan disimpan di log.'];
    }
    
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.fonnte.com/send',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
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
