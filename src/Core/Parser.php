<?php

namespace Core;

class Parser
{
    public function parse(string $input, array $options = []): array
    {
        $input = trim($input);
        if (empty($input)) {
            return ['soal' => [], 'metadata' => []];
        }

        if (preg_match('/<\w[^>]*>/', $input)) {
            return $this->parseHtml($input, $options);
        }

        return $this->parseText($input, $options);
    }

    private function parseHtml(string $html, array $options = []): array
    {
        $result = [
            'metadata' => [
                'judul'         => $options['judul'] ?? 'Imported Soal',
                'mata_pelajaran' => $options['mata_pelajaran'] ?? 'Umum',
                'kelas_target'  => $options['kelas_target'] ?? '-',
                'status'        => 'draft',
                'created_at'    => gmdate('Y-m-d\TH:i:s\Z'),
            ],
            'soal'   => [],
            'errors' => [],
        ];

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $blocks = $xpath->query('//div[contains(@class,"soal") or contains(@class,"question")]');

        if ($blocks->length === 0) {
            $blocks = $xpath->query('//p[strong]');
        }

        if ($blocks->length > 0) {
            foreach ($blocks as $block) {
                $q = $this->parseHtmlBlock($block);
                if ($q) {
                    $q['id'] = count($result['soal']) + 1;
                    $q['tag'] = 'Soal ' . $q['id'];
                    $result['soal'][] = $q;
                }
            }
        } else {
            $text = strip_tags($html);
            $text = preg_replace('/\s+/', ' ', $text);
            return $this->parseText($text, $options);
        }

        return $result;
    }

    private function parseHtmlBlock(\DOMElement $block): ?array
    {
        $text = $block->textContent;
        $text = preg_replace('/\s+/', ' ', trim($text));

        return $this->extractFromText($text);
    }

    private function parseText(string $text, array $options = []): array
    {
        $pasteParser = new PasteTextParser();
        $result = $pasteParser->parse($text, $options);
        return $result;
    }

    private function extractFromText(string $text): ?array
    {
        if (preg_match('/^(.*?)(?:[A-D][.)]\s*)/s', $text, $m)) {
            $pertanyaan = trim($m[1]);
        } else {
            $pertanyaan = $text;
        }

        $opsi = ['A' => '', 'B' => '', 'C' => '', 'D' => ''];
        foreach (['A', 'B', 'C', 'D'] as $opt) {
            if (preg_match('/' . $opt . '[.)]\s*(.*?)(?=[A-D][.)]|Jawaban|Kunci|$)/si', $text, $m)) {
                $opsi[$opt] = trim($m[1]);
            }
        }

        $jawaban = '';
        if (preg_match('/(?:Jawaban|Kunci)\s*:\s*([A-D])/i', $text, $m)) {
            $jawaban = strtoupper($m[1]);
        }

        if (empty($pertanyaan) || empty($opsi['A']) || empty($opsi['B']) || empty($opsi['C']) || empty($opsi['D']) || empty($jawaban)) {
            return null;
        }

        return [
            'pertanyaan'    => $pertanyaan,
            'opsi'          => $opsi,
            'jawaban_benar' => $jawaban,
            'pembahasan'    => '',
        ];
    }
}
