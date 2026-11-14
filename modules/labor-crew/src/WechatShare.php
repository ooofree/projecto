<?php
declare(strict_types=1);

namespace ProjectO\Modules\LaborCrew;

/**
 * Optional WeChat in-app share card support (JS-SDK).
 *
 * Reality check, per WeChat's official JS-SDK docs: inside WeChat, tapping
 * "···  → 发送给朋友" only renders a custom title/description/thumbnail when the
 * page calls wx.updateAppMessageShareData after a successful wx.config. That
 * requires ALL of:
 *   - a WeChat 认证服务号 (verified service account) with the share JSAPI rights,
 *   - the page's domain registered as a "JS接口安全域名" (ICP-filed domain;
 *     IP addresses and ports are explicitly NOT allowed),
 *   - server-side signing with a globally cached access_token + jsapi_ticket.
 * Open Graph tags alone do NOT control this path.
 *
 * So this class stays completely inert until an operator supplies credentials
 * through the environment. With no credentials the static page keeps its OG
 * metadata (which other clients and WeChat's link-preview crawler use) and the
 * UI tells the truth about what WeChat will show. Nothing here ever blocks or
 * breaks the page: no credentials means no external script, no network call.
 *
 * Environment:
 *   WECHAT_APPID       服务号 AppID
 *   WECHAT_APPSECRET   服务号 AppSecret（仅服务端使用，绝不输出到页面）
 *   WECHAT_JS_DOMAIN   已登记的 JS 接口安全域名（用于校验，可选）
 */
final class WechatShare
{
    private const TOKEN_URL = 'https://api.weixin.qq.com/cgi-bin/token';
    private const TICKET_URL = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket';

    public static function appId(): string
    {
        return trim((string) (getenv('WECHAT_APPID') ?: ''));
    }

    private static function appSecret(): string
    {
        return trim((string) (getenv('WECHAT_APPSECRET') ?: ''));
    }

    public static function isConfigured(): bool
    {
        return self::appId() !== '' && self::appSecret() !== '';
    }

    /** Human-readable explanation shown in the share dialog when unavailable. */
    public static function disabledReason(): ?string
    {
        if (self::isConfigured()) {
            return null;
        }

        return '微信内「···→发送给朋友」要显示自定义卡片，微信官方要求：认证服务号 + 已备案的 JS 接口安全域名（不支持 IP 和端口）+ JS-SDK 签名。当前未配置，微信会把直接粘贴的链接降级为纯链接。';
    }

    /**
     * Build the wx.config payload for a page URL. Returns null when not
     * configured or when WeChat cannot be reached — the caller then simply
     * omits the SDK and the page still works.
     *
     * @return array{appId:string,timestamp:int,nonceStr:string,signature:string}|null
     */
    public static function configFor(string $pageUrl): ?array
    {
        if (!self::isConfigured()) {
            return null;
        }
        // WeChat signs the full URL without the fragment.
        $url = explode('#', $pageUrl, 2)[0];
        $ticket = self::jsapiTicket();
        if ($ticket === null) {
            return null;
        }

        $nonce = bin2hex(random_bytes(8));
        $timestamp = time();
        // Field names must be lowercase and ASCII-sorted, values unescaped.
        $raw = 'jsapi_ticket=' . $ticket
            . '&noncestr=' . $nonce
            . '&timestamp=' . $timestamp
            . '&url=' . $url;

        return [
            'appId' => self::appId(),
            'timestamp' => $timestamp,
            'nonceStr' => $nonce,
            'signature' => sha1($raw),
        ];
    }

    /**
     * jsapi_ticket, cached globally on disk as WeChat requires (both the token
     * and the ticket are rate limited; refreshing per request would exhaust the
     * quota). Cache lives outside the web root.
     */
    private static function jsapiTicket(): ?string
    {
        $cacheFile = self::cachePath('jsapi_ticket');
        $cached = self::readCache($cacheFile);
        if ($cached !== null) {
            return $cached;
        }

        $token = self::accessToken();
        if ($token === null) {
            return null;
        }
        $response = self::getJson(self::TICKET_URL . '?access_token=' . urlencode($token) . '&type=jsapi');
        if (!is_array($response) || (int) ($response['errcode'] ?? -1) !== 0 || !isset($response['ticket'])) {
            error_log('labor.crew: WeChat jsapi_ticket failed: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
            return null;
        }
        $ticket = (string) $response['ticket'];
        // Refresh a little early so a request never uses an expired ticket.
        self::writeCache($cacheFile, $ticket, max(60, (int) ($response['expires_in'] ?? 7200) - 300));

        return $ticket;
    }

    private static function accessToken(): ?string
    {
        $cacheFile = self::cachePath('access_token');
        $cached = self::readCache($cacheFile);
        if ($cached !== null) {
            return $cached;
        }

        $response = self::getJson(self::TOKEN_URL . '?grant_type=client_credential'
            . '&appid=' . urlencode(self::appId())
            . '&secret=' . urlencode(self::appSecret()));
        if (!is_array($response) || !isset($response['access_token'])) {
            // Never log the secret; the error payload from WeChat has no secret.
            error_log('labor.crew: WeChat access_token failed: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
            return null;
        }
        $token = (string) $response['access_token'];
        self::writeCache($cacheFile, $token, max(60, (int) ($response['expires_in'] ?? 7200) - 300));

        return $token;
    }

    /** @return array<string, mixed>|null */
    private static function getJson(string $url): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $handle = curl_init($url);
        if ($handle === false) {
            return null;
        }
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($handle);
        curl_close($handle);
        if (!is_string($body) || $body === '') {
            return null;
        }
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function cachePath(string $key): string
    {
        // App-scoped so two installations on one host cannot share a ticket.
        return sys_get_temp_dir() . '/projecto-wechat-' . substr(sha1(self::appId()), 0, 12) . '-' . $key . '.json';
    }

    private static function readCache(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw)) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['value'], $data['expires_at'])) {
            return null;
        }
        if ((int) $data['expires_at'] <= time()) {
            return null;
        }

        return (string) $data['value'];
    }

    private static function writeCache(string $path, string $value, int $ttl): void
    {
        @file_put_contents($path, json_encode([
            'value' => $value,
            'expires_at' => time() + $ttl,
        ]), LOCK_EX);
        @chmod($path, 0600);
    }
}
