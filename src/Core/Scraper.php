<?php

namespace Core;

class Scraper
{
    private Parser $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    public function scrape(string $url): array
    {
        $html = $this->fetchUrl($url);
        if ($html === null) {
            return ['soal' => [], 'errors' => ['Gagal mengambil URL: ' . $url]];
        }
        return $this->scrapeFromHtml($html);
    }

    public function scrapeFromHtml(string $html): array
    {
        return $this->parser->parse($html, ['source' => 'html']);
    }

    public function fetchAndParse(string $url): array
    {
        $html = $this->fetchUrl($url);
        if ($html === null) {
            throw new \Exception('Gagal mengambil URL: ' . $url);
        }

        $result = $this->parser->parse($html, ['source' => 'url', 'judul' => 'Imported dari URL']);

        if (empty($result['soal'])) {
            throw new \Exception('Tidak ditemukan soal di URL: ' . $url);
        }

        return $result;
    }

    public function processUploadedFile(array $file): array
    {
        $tmpPath = $file['tmp_name'] ?? '';
        $origName = $file['name'] ?? 'uploaded';

        if (empty($tmpPath) || !file_exists($tmpPath)) {
            throw new \Exception('File tidak valid atau tidak ditemukan');
        }

        $content = file_get_contents($tmpPath);
        if ($content === false || empty($content)) {
            throw new \Exception('File kosong atau tidak bisa dibaca');
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (in_array($ext, ['htm', 'html'])) {
            $result = $this->parser->parse($content, ['source' => 'file', 'judul' => 'Imported: ' . $origName]);
        } else {
            $result = $this->parser->parse($content, ['source' => 'file', 'judul' => 'Imported: ' . $origName]);
        }

        if (empty($result['soal'])) {
            throw new \Exception('Tidak ditemukan soal dalam file: ' . $origName);
        }

        return $result;
    }

    public function getContentStats(array $result): array
    {
        $soal = $result['soal'] ?? [];
        $total = count($soal);

        $withPembahasan = 0;
        foreach ($soal as $s) {
            if (!empty($s['pembahasan'])) {
                $withPembahasan++;
            }
        }

        return [
            'total_questions'    => $total,
            'with_explanation'   => $withPembahasan,
            'without_explanation' => $total - $withPembahasan,
            'has_errors'         => !empty($result['errors']),
            'error_count'        => count($result['errors'] ?? []),
        ];
    }

    private function fetchUrl(string $url): ?string
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout'    => 15,
                'user_agent' => 'Mozilla/5.0 (compatible; PortalSoal/1.0)',
                'follow_location' => 1,
                'max_redirects'   => 3,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $html = @file_get_contents($url, false, $ctx);
        if ($html === false) {
            $ch = curl_init($url);
            if ($ch === false) return null;
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; PortalSoal/1.0)',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $html = curl_exec($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            if ($errno !== 0) return null;
        }

        return $html !== false ? $html : null;
    }
}
