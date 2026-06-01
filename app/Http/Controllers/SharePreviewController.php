<?php

namespace App\Http\Controllers;

use App\Models\NewsEvent;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class SharePreviewController extends Controller
{
    public function event(NewsEvent $event): Response
    {
        $event->loadCount(['items', 'timelineEntries', 'entities', 'relationships']);

        $title = $event->name.' | TruthShield 真相護盾';
        $description = $this->eventDescription($event);
        $eventUrl = $this->frontendUrl("/events/{$event->id}");
        $imageUrl = $this->apiUrl("/share/events/{$event->id}/image.png?v=".($event->updated_at?->timestamp ?? time()));

        $html = '<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>'.$this->escape($title).'</title>
  <meta name="description" content="'.$this->escape($description).'">
  <link rel="canonical" href="'.$this->escape($eventUrl).'">
  <meta property="og:type" content="article">
  <meta property="og:site_name" content="TruthShield 真相護盾">
  <meta property="og:title" content="'.$this->escape($title).'">
  <meta property="og:description" content="'.$this->escape($description).'">
  <meta property="og:url" content="'.$this->escape($eventUrl).'">
  <meta property="og:image" content="'.$this->escape($imageUrl).'">
  <meta property="og:image:secure_url" content="'.$this->escape($imageUrl).'">
  <meta property="og:image:type" content="image/png">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="'.$this->escape($title).'">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="'.$this->escape($title).'">
  <meta name="twitter:description" content="'.$this->escape($description).'">
  <meta name="twitter:image" content="'.$this->escape($imageUrl).'">
</head>
<body style="margin:0;background:#09090b;color:#f4f4f5;font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif">
  <main style="max-width:760px;margin:64px auto;padding:0 24px">
    <p style="color:#67e8f9;font-weight:700">TruthShield 真相護盾</p>
    <h1 style="font-size:32px;line-height:1.25">'.$this->escape($event->name).'</h1>
    <p style="color:#d4d4d8;line-height:1.8">'.$this->escape($description).'</p>
    <p><a href="'.$this->escape($eventUrl).'" style="color:#67e8f9">開啟事件頁</a></p>
  </main>
</body>
</html>';

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function eventImage(NewsEvent $event): Response
    {
        $event->loadCount(['items', 'timelineEntries', 'entities', 'relationships']);

        $image = imagecreatetruecolor(1200, 630);
        imageantialias($image, true);

        $bg = $this->color($image, '#09090b');
        imagefill($image, 0, 0, $bg);

        for ($y = 0; $y < 630; $y++) {
            $blend = $y / 630;
            $r = (int) (9 + 10 * $blend);
            $g = (int) (9 + 28 * $blend);
            $b = (int) (11 + 34 * $blend);
            imageline($image, 0, $y, 1200, $y, imagecolorallocate($image, $r, $g, $b));
        }

        $cyan = $this->color($image, '#67e8f9');
        $cyanSoft = $this->color($image, '#155e75');
        $emerald = $this->color($image, '#6ee7b7');
        $white = $this->color($image, '#f8fafc');
        $muted = $this->color($image, '#a1a1aa');
        $panel = $this->color($image, '#111827');
        $line = $this->color($image, '#334155');

        imagefilledrectangle($image, 64, 64, 1136, 566, $panel);
        imagerectangle($image, 64, 64, 1136, 566, $line);
        imagefilledrectangle($image, 64, 64, 1136, 76, $cyan);
        imagefilledellipse($image, 1010, 118, 250, 250, $cyanSoft);
        imagefilledellipse($image, 1090, 520, 200, 200, $this->color($image, '#064e3b'));

        $font = $this->fontPath();
        $this->text($image, 'TruthShield 真相護盾', 92, 134, 31, $cyan, $font);
        $this->text($image, '事件脈絡', 92, 184, 24, $emerald, $font);

        $y = 280;
        foreach ($this->wrap($event->name, 15, 2) as $lineText) {
            $this->text($image, $lineText, 92, $y, 58, $white, $font);
            $y += 78;
        }

        $summary = $this->eventDescription($event);
        $y += 14;
        foreach ($this->wrap($summary, 32, 2) as $lineText) {
            $this->text($image, $lineText, 92, $y, 24, $muted, $font);
            $y += 34;
        }

        $stats = [
            ['時間線', (string) ($event->timeline_entries_count ?? 0)],
            ['資料', (string) ($event->items_count ?? 0)],
            ['關係', (string) ($event->relationships_count ?? 0)],
        ];
        $x = 92;
        foreach ($stats as [$label, $value]) {
            imagefilledrectangle($image, $x, 470, $x + 150, 530, $this->color($image, '#0f172a'));
            imagerectangle($image, $x, 470, $x + 150, 530, $this->color($image, '#164e63'));
            $this->text($image, $value, $x + 18, 506, 24, $white, $font);
            $this->text($image, $label, $x + 70, 506, 16, $muted, $font);
            $x += 172;
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return response($png, 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    private function eventDescription(NewsEvent $event): string
    {
        $summary = trim((string) $event->summary);
        if ($summary !== '') {
            return Str::limit(strip_tags($summary), 140);
        }

        return "這個事件目前整理了 {$event->items_count} 筆資料、{$event->timeline_entries_count} 個時間線節點與 {$event->relationships_count} 條人物/組織關係。";
    }

    private function frontendUrl(string $path): string
    {
        $base = rtrim((string) (config('app.frontend_url') ?: env('FRONTEND_URL') ?: 'https://truth-shield.otus.tw'), '/');

        return $base.$path;
    }

    private function apiUrl(string $path): string
    {
        $base = rtrim((string) (config('app.url') ?: env('APP_URL') ?: 'https://truth-shield-api.otus.tw'), '/');

        return $base.$path;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function color($image, string $hex): int
    {
        $hex = ltrim($hex, '#');

        return imagecolorallocate(
            $image,
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }

    private function fontPath(): ?string
    {
        $candidates = [
            '/usr/share/fonts/noto/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/noto/NotoSansCJK-Bold.ttc',
            '/usr/share/fonts/noto-cjk/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/noto-cjk/NotoSansCJKtc-Regular.otf',
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
            '/System/Library/Fonts/STHeiti Medium.ttc',
            '/System/Library/Fonts/PingFang.ttc',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function text($image, string $text, int $x, int $y, int $size, int $color, ?string $font): void
    {
        if (! $font || ! function_exists('imagettftext')) {
            throw new \RuntimeException('A TrueType CJK font is required to render share preview text.');
        }

        imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
    }

    /**
     * @return array<int, string>
     */
    private function wrap(string $text, int $length, int $maxLines): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?: '');
        if ($text === '') {
            return [];
        }

        $lines = [];
        while (mb_strlen($text) > 0 && count($lines) < $maxLines) {
            $line = mb_substr($text, 0, $length);
            $text = mb_substr($text, mb_strlen($line));

            if (count($lines) === $maxLines - 1 && mb_strlen($text) > 0) {
                $line = rtrim(mb_substr($line, 0, max(0, $length - 1))).'…';
            }

            $lines[] = $line;
        }

        return $lines;
    }
}
