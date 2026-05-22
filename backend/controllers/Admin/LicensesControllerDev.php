<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\System\NexaController;

/**
 * LicensesController - Admin Controller
 * URL: /{username}/licenses
 * Template: templates/dashboard/licenses/index.html
 */
class LicensesController extends NexaController
{
    public function index(array $params = []): void
    {
        $username = $params['username'] ?? $this->getSession()->getUserSlug();
        $this->assignVars(['page_title' => 'Licenses', 'username' => $username]);
    }
 /**
     * Daftar paket harga
     * GET /api/licenses/plans
     */
    public function plans($data = [], $params = []): array
    {
        $monthly = 25000;
        $yearly  = 200000;

        // Hitung otomatis: hemat berapa vs bayar bulanan selama 12 bulan
        $monthlyPerYear  = $monthly * 12;                                          // 348.000
        $savedAmount     = $monthlyPerYear - $yearly;                              // 49.000
        $savedPercent    = (int) round(($savedAmount / $monthlyPerYear) * 100);    // 14%

        // Format angka ke format Rupiah tanpa desimal (49.000 → "Rp 49.000")
        $savedFormatted  = 'Rp ' . number_format($savedAmount, 0, ',', '.');

        return [
            'status' => 'success',
            'plans'  => [
                [
                    'id'          => 'trial',
                    'name'        => 'Trial',
                    'description' => 'Coba gratis selama 7 hari',
                    'price'       => 0,
                    'currency'    => 'IDR',
                    'days'        => 7,
                    'features'    => ['Akses penuh fitur', 'Maks 1 perangkat'],
                ],
                [
                    'id'          => 'monthly',
                    'name'        => 'Bulanan',
                    'description' => 'Langganan per bulan, bayar tiap 30 hari',
                    'price'       => $monthly,
                    'currency'    => 'IDR',
                    'days'        => 30,
                    'features'    => ['Akses penuh fitur', 'Maks 1 perangkat', 'Support email'],
                ],
                [
                    'id'          => 'yearly',
                    'name'        => 'Tahunan',
                    'description' => 'Hemat ' . $savedPercent . '% dibanding bulanan',
                    'price'       => $yearly,
                    'currency'    => 'IDR',
                    'days'        => 365,
                    'save'        => 'Hemat ' . $savedFormatted . ' vs bulanan',
                    'features'    => ['Akses penuh fitur', 'Maks 3 perangkat', 'Support prioritas', 'Update gratis'],
                ],
            ],
            'timestamp' => time(),
        ];
    }

    /**
     * Cek license key: validasi trial & expiry, registrasi device
     * POST /api/licenses/key
     * body: { "licenses": "ABC-123-XYZ", "device_id": "<sha256>" }
     */
    public function key($data = [], $params = []): array
    {
        $licenseKey = $data['licenses'] ?? '';
        $deviceId   = trim($data['device_id'] ?? '');
        $appId      = trim($data['app_id'] ?? '');

        if (empty($licenseKey)) {
            return ['status' => 'error', 'message' => 'License key required', 'timestamp' => time()];
        }

        if (empty($deviceId)) {
            return ['status' => 'error', 'message' => 'Device ID required', 'timestamp' => time()];
        }

        if (empty($appId)) {
            return ['status' => 'error', 'message' => 'App ID required', 'timestamp' => time()];
        }

        $license = $this->Storage('licenses')
            ->select(['*'])
            ->where('license_key', $licenseKey)
            ->first();

        if (!$license) {
            return ['status' => 'error', 'message' => 'License not found', 'timestamp' => time()];
        }

        $now = time();
        $isTrial    = !empty($license['trial']);
        $expiredAt  = (int) $license['expired_at'];
        $trialDays  = (int) $license['trial'];
        $createdAt  = strtotime($license['created_at']);

        // Hitung expired_at otomatis dari trial jika belum diset
        if ($isTrial && $expiredAt === 0) {
            $expiredAt = $createdAt + ($trialDays * 86400);
            $this->Storage('licenses')
                ->where('id', $license['id'])
                ->update(['expired_at' => $expiredAt]);
        }

        // Cek apakah sudah expired
        $isExpired = $expiredAt > 0 && $now > $expiredAt;

        if ($isExpired) {
            if ($license['status'] === 'active') {
                $this->Storage('licenses')
                    ->where('id', $license['id'])
                    ->update(['status' => 'inactive']);
            }

            // Cek apakah ada langganan aktif
            $activeSub = null;
            try {
                $activeSub = $this->Storage('subscriptions')
                    ->where('license_id', $license['id'])
                    ->where('status', 'active')
                    ->where('expired_at', '>', $now)
                    ->first();
            } catch (\Throwable $_) {}

            if ($activeSub) {
                // Ada langganan aktif — perpanjang license
                $this->Storage('licenses')
                    ->where('id', $license['id'])
                    ->update(['status' => 'active', 'expired_at' => (int) $activeSub['expired_at']]);

                return [
                    'status'      => 'valid',
                    'message'     => 'Subscription active',
                    'license_key' => $license['license_key'],
                    'userid'      => $license['userid'],
                    'trial'       => false,
                    'plan'        => $activeSub['plan'],
                    'expired_at'  => (int) $activeSub['expired_at'],
                    'remaining'   => max(0, (int) ceil(((int)$activeSub['expired_at'] - $now) / 86400)) . ' days',
                    'max_devices' => (int) $license['max_devices'],
                    'timestamp'   => $now,
                ];
            }

            return [
                'status'     => 'expired',
                'message'    => $isTrial ? 'Trial habis. Silakan berlangganan.' : 'License expired. Silakan perpanjang.',
                'subscribe'  => '/api/licenses/subscribe',
                'expired_at' => $expiredAt,
                'timestamp'  => $now,
            ];
        }

        // ── Device + App validation ────────────────────────────────────────
        $maxDevices = (int) $license['max_devices'];

        // Cek apakah kombinasi device+app ini sudah terdaftar
        $existingDevice = $this->Storage('license_devices')
            ->where('license_id', $license['id'])
            ->where('device_id', $deviceId)
            ->where('app_id', $appId)
            ->first();

        if (!$existingDevice) {
            // Cek apakah device ini sudah terdaftar dengan APP LAIN untuk license yang sama
            $sameDeviceDiffApp = $this->Storage('license_devices')
                ->where('license_id', $license['id'])
                ->where('device_id', $deviceId)
                ->first();

            if ($sameDeviceDiffApp) {
                return [
                    'status'      => 'app_mismatch',
                    'message'     => 'License ini sudah terdaftar untuk aplikasi lain (' . $sameDeviceDiffApp['app_id'] . '). Gunakan license key yang berbeda.',
                    'registered_app' => $sameDeviceDiffApp['app_id'],
                    'timestamp'   => $now,
                ];
            }

            // Hitung total slot terpakai (per kombinasi unik device+app)
            $deviceCount = $this->Storage('license_devices')
                ->where('license_id', $license['id'])
                ->count();

            if ($maxDevices > 0 && $deviceCount >= $maxDevices) {
                return [
                    'status'      => 'device_limit',
                    'message'     => 'Batas perangkat tercapai. License ini sudah terdaftar di ' . $deviceCount . ' perangkat.',
                    'max_devices' => $maxDevices,
                    'timestamp'   => $now,
                ];
            }

            // Daftarkan device+app baru
            $this->Storage('license_devices')->insert([
                'license_id'    => $license['id'],
                'device_id'     => $deviceId,
                'app_id'        => $appId,
                'registered_at' => $now,
                'last_seen_at'  => $now,
            ]);
        } else {
            // Update last_seen
            $this->Storage('license_devices')
                ->where('id', $existingDevice['id'])
                ->update(['last_seen_at' => $now]);
        }
        // ──────────────────────────────────────────────────────────────────

        // Cek subscription aktif — sebagai sumber kebenaran expired_at & remaining
        $activeSub = null;
        try {
            $activeSub = $this->Storage('subscriptions')
                ->where('license_id', $license['id'])
                ->where('status', 'active')
                ->where('expired_at', '>', $now)
                ->orderBy('expired_at', 'DESC')
                ->first();
        } catch (\Throwable $_) { /* tabel subscriptions belum ada */ }

        // Subscription aktif = sumber kebenaran. Sinkronkan licenses.expired_at jika beda.
        if ($activeSub) {
            $subExpiry = (int) $activeSub['expired_at'];
            if ($subExpiry !== $expiredAt) {
                $this->Storage('licenses')
                    ->where('id', $license['id'])
                    ->update(['expired_at' => $subExpiry, 'trial' => null]);
            }
            $expiredAt = $subExpiry;
            $isTrial   = false;
        }

        return [
            'status'      => 'valid',
            'message'     => $isTrial ? 'Trial active' : 'License active',
            'license_key' => $license['license_key'],
            'trial'       => $isTrial,
            'userid'     => $license['userid'],
            'trial_days'  => $isTrial ? $trialDays : null,
            'plan'        => $activeSub ? $activeSub['plan'] : null,
            'expired_at'  => $expiredAt > 0 ? $expiredAt : null,
            'remaining'   => $expiredAt > 0 ? max(0, (int) ceil(($expiredAt - $now) / 86400)) . ' days' : 'unlimited',
            'max_devices' => $maxDevices,
            'timestamp'   => $now,
        ];
    }

    /**
     * Berlangganan bulanan / tahunan
     * POST /api/licenses/subscribe
     * body: { "license_key": "ABC-123-XYZ", "plan": "monthly" }
     */
    public function subscribe($data = [], $params = []): array
    {
        $licenseKey = $data['license_key'] ?? $data['licenses'] ?? '';
        $plan       = $data['plan'] ?? 'monthly';

        if (empty($licenseKey)) {
            return ['status' => 'error', 'message' => 'License key required', 'timestamp' => time()];
        }

        if (!in_array($plan, ['monthly', 'yearly'])) {
            return ['status' => 'error', 'message' => 'Plan tidak valid. Gunakan: monthly / yearly', 'timestamp' => time()];
        }

        $license = $this->Storage('licenses')
            ->where('license_key', $licenseKey)
            ->first();

        if (!$license) {
            return ['status' => 'error', 'message' => 'License not found', 'timestamp' => time()];
        }

        $now      = time();
        $days     = $plan === 'yearly' ? 365 : 30;
        $amount   = $plan === 'yearly' ? 299000.00 : 29000.00;

        // Mulai dari sekarang atau perpanjang dari expired_at yang ada
        $currentExpiry = (int) $license['expired_at'];
        $startedAt  = $now;
        $expiredAt  = ($currentExpiry > $now ? $currentExpiry : $now) + ($days * 86400);

        // Nonaktifkan langganan lama
        $this->Storage('subscriptions')
            ->where('license_id', $license['id'])
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        // Buat langganan baru
        $this->Storage('subscriptions')->insert([
            'userid'     => $license['userid'],
            'license_id' => $license['id'],
            'plan'       => $plan,
            'amount'     => $amount,
            'started_at' => $startedAt,
            'expired_at' => $expiredAt,
            'status'     => 'active',
        ]);

        // Update license
        $this->Storage('licenses')
            ->where('id', $license['id'])
            ->update(['status' => 'active', 'expired_at' => $expiredAt, 'trial' => null]);

        return [
            'status'      => 'subscribed',
            'message'     => 'Langganan ' . $plan . ' berhasil diaktifkan',
            'plan'        => $plan,
            'amount'      => $amount,
            'started_at'  => $startedAt,
            'expired_at'  => $expiredAt,
            'remaining'   => $days . ' days',
            'license_key' => $licenseKey,
            'timestamp'   => $now,
        ];
    }


    /**
     * Konfirmasi order dari Midtrans & perpanjang license
     * POST /api/licenses/order
     * body: {
     *   "licenses"           : "ABC-123-XYZ",
     *   "order_id"           : "ORDER-MONTHLY-1234567890",
     *   "transaction_status" : "settlement",   // dari Midtrans onSuccess result
     *   "plan"               : "monthly",
     *   "days"               : 30,
     *   "price"              : 29000,
     *   "currency"           : "IDR"
     * }
     */
    public function order($data = [], $params = []): array
    {
        $licenseKey = trim($data['licenses'] ?? $data['license_key'] ?? '');
        $orderId    = trim($data['order_id'] ?? '');
        $planId     = trim($data['plan'] ?? $data['id'] ?? '');
        $days       = max(1, (int) ($data['days'] ?? 30));
        $price      = (float) ($data['price'] ?? 0);
        $currency   = strtoupper(trim($data['currency'] ?? 'IDR'));
        $txStatus   = strtolower(trim($data['transaction_status'] ?? 'settlement'));

        if (empty($licenseKey)) {
            return ['status' => 'error', 'message' => 'License key required', 'timestamp' => time()];
        }
        if (empty($orderId)) {
            return ['status' => 'error', 'message' => 'Order ID required', 'timestamp' => time()];
        }
        if (empty($planId)) {
            return ['status' => 'error', 'message' => 'Plan ID required', 'timestamp' => time()];
        }

        // ── Verifikasi signature_key dari redirect Midtrans (lokal, tanpa network) ──
        $serverKey = trim($_ENV['MIDTRANS_SERVER_KEY'] ?? '');
        if ($serverKey === '') {
            return ['status' => 'error', 'message' => 'MIDTRANS_SERVER_KEY belum dikonfigurasi di .env', 'timestamp' => time()];
        }
        $signatureKey = trim($data['signature_key'] ?? '');
        $statusCode   = trim($data['status_code'] ?? '');
        $grossAmount  = trim($data['gross_amount'] ?? (string) $price);
        $txStatus     = strtolower(trim($data['transaction_status'] ?? 'settlement'));
        $fraudStatus  = 'accept';

        if (!empty($signatureKey) && !empty($statusCode) && !empty($grossAmount)) {
            // signature_key = SHA512(order_id + status_code + gross_amount + ServerKey)
            $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
            if (!hash_equals($expected, $signatureKey)) {
                return ['status' => 'error', 'message' => 'Signature tidak valid.', 'timestamp' => time()];
            }
            $midtransStatus = $txStatus;
        } else {
            // Fallback: server-to-server check
            $midtransApiUrl = 'https://api.sandbox.midtrans.com/v2/' . rawurlencode($orderId) . '/status';
            $ch = curl_init($midtransApiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/json',
                    'Authorization: Basic ' . base64_encode($serverKey . ':'),
                ],
            ]);
            $response  = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            if (!$curlError && !empty($response)) {
                $tx = json_decode($response, true);
                if (is_array($tx)) {
                    $midtransStatus = strtolower($tx['transaction_status'] ?? $txStatus);
                    $fraudStatus    = strtolower($tx['fraud_status'] ?? 'accept');
                } else {
                    $midtransStatus = $txStatus;
                }
            } else {
                error_log('[LicensesController::order] cURL/API unavailable: ' . $curlError);
                return ['status' => 'error', 'message' => 'Tidak dapat memverifikasi pembayaran. Coba lagi atau hubungi support.', 'timestamp' => time()];
            }
        }

        // settlement/capture = dibayar; pending = menunggu
        $isPaid = in_array($midtransStatus, ['settlement', 'capture'], true)
                  && $fraudStatus !== 'deny';

        if (!$isPaid) {
            return [
                'status'             => 'pending',
                'message'            => 'Pembayaran belum dikonfirmasi. Status: ' . $midtransStatus,
                'transaction_status' => $midtransStatus,
                'timestamp'          => time(),
            ];
        }

        // Cek apakah order_id sudah pernah diproses (idempotency)
        try {
            $existingOrder = $this->Storage('subscriptions')
                ->where('order_id', $orderId)
                ->first();

            if ($existingOrder) {
                $license = $this->Storage('licenses')->where('license_key', $licenseKey)->first();
                return [
                    'status'      => 'already_processed',
                    'message'     => 'Order sudah diproses sebelumnya.',
                    'plan'        => $existingOrder['plan'],
                    'expired_at'  => $license ? (int) $license['expired_at'] : null,
                    'timestamp'   => time(),
                ];
            }
        } catch (\Throwable $e) {
            error_log('[LicensesController::order] subscriptions check error: ' . $e->getMessage());
        }

        // Ambil license
        $license = $this->Storage('licenses')->where('license_key', $licenseKey)->first();
        if (!$license) {
            return ['status' => 'error', 'message' => 'License not found', 'timestamp' => time()];
        }

        $now       = time();
        $startedAt = $now;

        // Cek apakah ada subscription aktif — jika ya, extend dari expiry yang ada (sisa hari tidak hilang)
        $currentActiveSub = null;
        try {
            $currentActiveSub = $this->Storage('subscriptions')
                ->where('license_id', $license['id'])
                ->where('status', 'active')
                ->where('expired_at', '>', $now)
                ->orderBy('expired_at', 'DESC')
                ->first();
        } catch (\Throwable $_) {}

        $baseExpiry = ($currentActiveSub && (int) $currentActiveSub['expired_at'] > $now)
            ? (int) $currentActiveSub['expired_at']
            : $now;

        $expiredAt = $baseExpiry + ($days * 86400);

        // Coba simpan ke subscriptions (opsional — tidak gagal jika tabel belum ada)
        try {
            $this->Storage('subscriptions')
                ->where('license_id', $license['id'])
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            $this->Storage('subscriptions')->insert([
                'userid'     => $license['userid'],
                'license_id' => $license['id'],
                'plan'       => $planId,
                'amount'     => $price,
                'currency'   => $currency,
                'order_id'   => $orderId,
                'started_at' => $startedAt,
                'expired_at' => $expiredAt,
                'status'     => 'active',
            ]);
        } catch (\Throwable $e) {
            error_log('[LicensesController::order] subscriptions insert error (table may not exist): ' . $e->getMessage());
        }

        // Perbarui license: aktif, hilangkan trial, set expiry baru — SELALU dijalankan
        $this->Storage('licenses')
            ->where('id', $license['id'])
            ->update(['status' => 'active', 'expired_at' => $expiredAt, 'trial' => null]);

        $remaining = max(0, (int) ceil(($expiredAt - $now) / 86400));

        return [
            'status'      => 'success',
            'message'     => 'License berhasil diperpanjang — paket ' . $planId . ' (' . $days . ' hari)',
            'plan'        => $planId,
            'license_key' => $licenseKey,
            'started_at'  => $startedAt,
            'expired_at'  => $expiredAt,
            'remaining'   => $remaining . ' days',
            'order_id'    => $orderId,
            'timestamp'   => $now,
        ];
    }

    public function Fetch(){
         return $this->NexaRender();
     }

   
     public function FetchEvents(array $params = []){
           $this->eventsAccess($params);
     }

     public function FetchControllers(){
        return $this->eventsControllers();
     }


    public function FetchModels(){
         $this->eventsModel();
    }

}
