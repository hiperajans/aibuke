<?php
/**
 * Aibuke - Web Searcher
 * İnternetten sınav soruları arar ve içerik çeker.
 * Yerel SearXNG instance kullanır (127.0.0.1:8888).
 *
 * Kullanım:
 *   $ws = new WebSearcher();
 *   $results = $ws->search("2024 TYT Matematik soruları");
 *   $content = $ws->fetchPage($results[0]['url']);
 */

class WebSearcher {

    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private $searxUrl = 'http://127.0.0.1:8888';

    /** Atlanan domain'ler */
    private $skipDomains = [
        'youtube.com', 'youtu.be', 'instagram.com', 'facebook.com',
        'twitter.com', 'x.com', 'tiktok.com', 'pinterest.com',
    ];

    /**
     * SearXNG ile web araması
     * @return array [{title, url, snippet}, ...]
     */
    public function search($query, $maxResults = 10) {
        $url = $this->searxUrl . '/search?q=' . urlencode($query) . '&format=json&language=tr-TR';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($resp)) {
            error_log("WebSearcher: SearXNG HTTP $httpCode");
            return [];
        }

        $data = json_decode($resp, true);
        if (empty($data['results'])) return [];

        $results = [];
        foreach ($data['results'] as $r) {
            $rUrl = $r['url'] ?? '';

            // Skip domain kontrolü
            $skip = false;
            foreach ($this->skipDomains as $domain) {
                if (strpos($rUrl, $domain) !== false) { $skip = true; break; }
            }
            if ($skip) continue;

            $results[] = [
                'title' => $r['title'] ?? '',
                'url' => $rUrl,
                'snippet' => $r['content'] ?? '',
            ];

            if (count($results) >= $maxResults) break;
        }

        return $results;
    }

    /**
     * Sayfa içeriğini çek ve temiz metin döndür
     * @return string|null
     */
    public function fetchPage($url, $maxLength = 100000) {
        // PDF → pdftotext
        if (preg_match('/\.pdf(\?|$)/i', $url)) {
            return $this->fetchPDF($url);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml',
                'Accept-Language: tr-TR,tr;q=0.9',
            ],
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING => 'gzip, deflate',
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($html)) return null;

        return $this->htmlToText($html, $maxLength);
    }

    /**
     * PDF indir ve text'e çevir
     */
    private function fetchPDF($url) {
        $tmpFile = '/tmp/aibuke_search_' . md5($url) . '.pdf';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($content)) return null;

        file_put_contents($tmpFile, $content);
        $text = shell_exec("pdftotext -layout " . escapeshellarg($tmpFile) . " - 2>/dev/null");
        @unlink($tmpFile);

        return $text ?: null;
    }

    /**
     * HTML'den temiz metin çıkar (soru içeriğine odaklı)
     */
    private function htmlToText($html, $maxLength = 50000) {
        // Encoding düzelt
        if (preg_match('/charset=["\']?([^"\';\s]+)/i', $html, $enc)) {
            $charset = strtolower($enc[1]);
            if ($charset !== 'utf-8' && $charset !== 'utf8') {
                $html = @mb_convert_encoding($html, 'UTF-8', $charset);
            }
        }

        // Gereksiz blokları kaldır
        $html = preg_replace('/<(script|style|nav|header|footer|aside|iframe|noscript|svg)[^>]*>.*?<\/\1>/si', '', $html);
        // Yorum blokları
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // Ana içerik bul — geniş capture
        $content = $html;

        // article veya main varsa onu al
        if (preg_match('/<(?:article|main)[^>]*>([\s\S]*?)<\/(?:article|main)>/i', $html, $main)) {
            $content = $main[1];
        }
        // Yoksa body'nin tamamını al
        elseif (preg_match('/<body[^>]*>([\s\S]*)<\/body>/i', $html, $body)) {
            $content = $body[1];
        }

        // Block element'leri satır sonuna çevir
        $content = preg_replace('/<\/(p|div|li|tr|h[1-6]|br\s*\/?)>/i', "\n", $content);
        $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $content = preg_replace('/<\/td>/i', "\t", $content);

        // HTML taglerini kaldır
        $text = strip_tags($content);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Temizlik
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        return mb_substr($text, 0, $maxLength);
    }
}
