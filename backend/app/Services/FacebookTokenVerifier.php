<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class FacebookTokenVerifier implements FacebookTokenVerifierInterface
{
    public function verify(string $accessToken): array
    {
        $appId = (string) config('services.facebook.app_id');
        $appSecret = (string) config('services.facebook.app_secret');
        $graphVersion = (string) config('services.facebook.graph_version');

        if ($appId === '' || $appSecret === '' || $graphVersion === '') {
            throw new InvalidArgumentException('Cấu hình Facebook Login chưa đầy đủ.');
        }

        $baseUrl = 'https://graph.facebook.com/'.$graphVersion;

        try {
            $debugResponse = Http::acceptJson()
                ->timeout(10)
                ->get($baseUrl.'/debug_token', [
                    'input_token' => $accessToken,
                    'access_token' => $appId.'|'.$appSecret,
                ]);
        } catch (ConnectionException) {
            throw new InvalidArgumentException('Không thể kết nối tới Facebook để xác minh tài khoản.');
        }

        if (! $debugResponse->successful()) {
            throw new InvalidArgumentException('Token Facebook không hợp lệ hoặc đã hết hạn.');
        }

        $debugData = $debugResponse->json('data', []);
        if (
            ! is_array($debugData)
            || ($debugData['is_valid'] ?? false) !== true
            || (string) ($debugData['app_id'] ?? '') !== $appId
            || empty($debugData['user_id'])
        ) {
            throw new InvalidArgumentException('Token Facebook không thuộc ứng dụng KomiBook.');
        }

        $userId = (string) $debugData['user_id'];
        $appSecretProof = hash_hmac('sha256', $accessToken, $appSecret);

        try {
            $profileResponse = Http::acceptJson()
                ->timeout(10)
                ->get($baseUrl.'/'.$userId, [
                    'fields' => 'id,name,email',
                    'access_token' => $accessToken,
                    'appsecret_proof' => $appSecretProof,
                ]);
        } catch (ConnectionException) {
            throw new InvalidArgumentException('Không thể tải hồ sơ Facebook đã xác minh.');
        }

        if (! $profileResponse->successful()) {
            throw new InvalidArgumentException('Không thể tải hồ sơ Facebook đã xác minh.');
        }

        $profile = $profileResponse->json();
        if (! is_array($profile) || (string) ($profile['id'] ?? '') !== $userId) {
            throw new InvalidArgumentException('Hồ sơ Facebook không khớp với token đã xác minh.');
        }

        $name = trim((string) ($profile['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Tài khoản Facebook thiếu thông tin tên.');
        }

        $email = isset($profile['email']) ? trim((string) $profile['email']) : null;
        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = null;
        }

        return [
            'id' => $userId,
            'email' => $email,
            'name' => $name,
        ];
    }
}
