<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

class HtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    /**
     * Khởi tạo và lấy instance của HTMLPurifier với cấu hình allowlist bảo mật.
     */
    private static function getPurifier(): HTMLPurifier
    {
        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();

            // Allowlist thẻ HTML
            $config->set('HTML.Allowed', 'p[class],br,strong,b,em,i,u,s,strike,ul[class],ol[class],li[class],blockquote[class],h2[class],h3[class],h4[class],h5[class],h6[class],a[href|target|rel],img[src|alt|width|height],span[class],sub,sup');

            // Bảo mật liên kết target="_blank"
            $config->set('HTML.TargetBlank', true);

            // Cấm các scheme URI nguy hiểm như javascript:, data:, vbscript:
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);

            // Không cho phép thẻ style và CSS inline nguy hiểm
            $config->set('CSS.AllowedProperties', []);

            // Tự động dọn dẹp các thẻ rỗng hoặc malformed HTML
            $config->set('AutoFormat.RemoveEmpty', true);
            $config->set('Core.Encoding', 'UTF-8');
            $config->set('HTML.Doctype', 'XHTML 1.0 Transitional');

            self::$purifier = new HTMLPurifier($config);
        }

        return self::$purifier;
    }

    /**
     * Làm sạch nội dung HTML nhằm chống Stored XSS bằng thư viện ezyang/htmlpurifier.
     */
    public static function sanitize(?string $content): ?string
    {
        if (empty($content)) {
            return $content;
        }

        return self::getPurifier()->purify($content);
    }
}
