<?php

$OLLAMA_URL = getenv('OLLAMA_URL') ?: 'http://ollama:11434/api/generate';
$MODEL = 'gemma4:e2b';
$MYSQL_HOST = getenv('MYSQL_HOST') ?: 'mysql';
$MYSQL_DB = getenv('MYSQL_DB') ?: 'writer_ai';
$MYSQL_USER = getenv('MYSQL_USER') ?: 'writer';
$MYSQL_PASS = getenv('MYSQL_PASS') ?: 'writerpass';
$SITE_URL = getenv('SITE_URL') ?: 'https://terradorei.com.br/';
$DEFAULT_INSTAGRAM_HASHTAGS = '#noticias #terradorei #informacao';
$DEFAULT_JORNALISTICO_TAGS = 'noticias, atualidade, informacao';

function getDB(): PDO {
    $maxAttempts = 10;
    for ($i = 0; $i < $maxAttempts; $i++) {
        try {
            $pdo = new PDO(
                "mysql:host={$GLOBALS['MYSQL_HOST']};dbname={$GLOBALS['MYSQL_DB']};charset=utf8mb4",
                $GLOBALS['MYSQL_USER'],
                $GLOBALS['MYSQL_PASS']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            if ($i === $maxAttempts - 1) throw $e;
            sleep(2);
        }
    }
}

function slugify_text(string $text): string {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = preg_replace('/[^a-zA-Z0-9]+/', '_', strtolower($text));
    return trim($text, '_') ?: 'noticia';
}

function extract_news_title(string $noticia): string {
    $linhas = array_values(array_filter(array_map('trim', explode("\n", $noticia)), fn($l) => $l !== ''));
    if (empty($linhas)) return 'Noticia sem titulo';
    $titulo = $linhas[0];
    $palavras = explode(' ', $titulo);
    if (count($palavras) > 12) {
        $titulo = implode(' ', array_slice($palavras, 0, 12));
    }
    return mb_strlen($titulo) > 80 ? mb_substr($titulo, 0, 80) : $titulo;
}

function title_around_50_chars(string $text): string {
    $title = extract_news_title($text);
    if (mb_strlen($title) <= 55) return $title;
    $shortened = mb_substr($title, 0, 55);
    $lastSpace = mb_strrpos($shortened, ' ');
    if ($lastSpace !== false) {
        $shortened = mb_substr($shortened, 0, $lastSpace);
    }
    return trim($shortened) ?: trim(mb_substr($title, 0, 55));
}

function ensure_instagram_link_and_hashtags(string $text): string {
    $site_url = $GLOBALS['SITE_URL'];
    $default_hashtags = $GLOBALS['DEFAULT_INSTAGRAM_HASHTAGS'];
    $content = trim($text);
    $link_line = "Saiba mais em:  $site_url";
    $content = preg_replace('/Saiba mais em:\s*https?:\/\/terradorei\.com\.br\/?/i', '', $content);
    $content = trim($content);
    $hashtags = $default_hashtags;
    if (preg_match('/^\s*(?:HASHTAGS?:\s*)?(#\w+(?:\s+#\w+)*)\s*$/mi', $content, $matches, PREG_OFFSET_CAPTURE)) {
        $hashtags = trim($matches[1][0]);
        $body_start = substr($content, 0, $matches[0][1]);
        $body_end = substr($content, $matches[0][1] + strlen($matches[0][0]));
        $content = trim($body_start . $body_end);
    }
    return trim("$content\n\n$link_line\n\n$hashtags");
}

function ensure_jornalistico_structure(string $text, string $fallback_title): string {
    $content = trim($text);
    $titleMatch = null;
    $tagsMatch = null;
    preg_match('/^\s*T(?:itulo|.tulo)\s*:\s*(.+)$/im', $content, $titleMatch, PREG_OFFSET_CAPTURE);
    preg_match('/^\s*Tags?\s*:\s*(.+)$/im', $content, $tagsMatch, PREG_OFFSET_CAPTURE);
    $title = $titleMatch ? trim($titleMatch[1][0]) : $fallback_title;
    $title = title_around_50_chars($title);
    $tagStr = $GLOBALS['DEFAULT_JORNALISTICO_TAGS'];
    if ($tagsMatch) {
        $tagsRaw = trim($tagsMatch[1][0]);
        $tagsArr = array_map('trim', explode(',', $tagsRaw));
        $tagsArr = array_slice(array_filter($tagsArr, fn($t) => $t !== ''), 0, 3);
        $tagStr = implode(', ', $tagsArr) ?: $GLOBALS['DEFAULT_JORNALISTICO_TAGS'];
    }
    if ($tagsMatch) {
        $content = trim(
            substr($content, 0, $tagsMatch[0][1]) .
            substr($content, $tagsMatch[0][1] + strlen($tagsMatch[0][0]))
        );
    }
    if ($titleMatch) {
        $content = trim(
            substr($content, 0, $titleMatch[0][1]) .
            substr($content, $titleMatch[0][1] + strlen($titleMatch[0][0]))
        );
    }
    $content = preg_replace('/^\s*Texto\s*:\s*/im', '', $content);
    $content = trim($content);
    return "Titulo: $title\n\n$content\n\nTags: $tagStr";
}

function extract_structured_title(string $text, string $fallback_title): string {
    if (preg_match('/^\s*T(?:itulo|.tulo)\s*:\s*(.+)$/im', $text, $m)) {
        return title_around_50_chars(trim($m[1]));
    }
    return $fallback_title;
}

function build_unique_base_name(string $base_name): string {
    $candidate = $base_name;
    $counter = 2;
    $noticiasDir = __DIR__ . '/noticias';
    while (
        file_exists("$noticiasDir/{$candidate}_instagram.md") ||
        file_exists("$noticiasDir/{$candidate}_wordpress.md")
    ) {
        $candidate = "{$base_name}_{$counter}";
        $counter++;
    }
    return $candidate;
}

function format_instagram_text(string $titulo, string $conteudo): string {
    $conteudo = ensure_instagram_link_and_hashtags($conteudo);
    return "TITULO: $titulo\n\nLEGENDA:\n$conteudo";
}

function format_wordpress_text(string $titulo, string $conteudo, string $slug): string {
    $metaDesc = str_replace("\n", ' ', $conteudo);
    $metaDesc = mb_strlen($metaDesc) > 155 ? mb_substr($metaDesc, 0, 155) : $metaDesc;
    return "Titulo: $titulo\nSlug: $slug\nMeta descricao: $metaDesc\n\nConteudo:\n\n$conteudo";
}

function render_markdown(?string $texto): string {
    if ($texto === null || $texto === '') return '';
    $text = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $text);
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text);
    $paragraphs = preg_split('/\n\s*\n/', $text);
    $result = '';
    foreach ($paragraphs as $p) {
        $p = trim($p);
        if ($p !== '') {
            $result .= '<p>' . nl2br($p) . "</p>\n";
        }
    }
    return $result;
}
