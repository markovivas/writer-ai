<?php

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$noticia = isset($input['noticia']) ? trim($input['noticia']) : '';

if (!$noticia) {
    http_response_code(400);
    echo json_encode(['error' => 'Digite uma noticia antes de gerar.']);
    exit;
}

$noticiasDir = __DIR__ . '/noticias';
if (!is_dir($noticiasDir)) {
    @mkdir($noticiasDir, 0777, true);
}

$prompt = "
Voce e um gerador de conteudo profissional.
Responda seguindo ESTRITAMENTE o formato abaixo, sem adicionar introducoes, cumprimentos ou conclusoes.
Use exatamente as etiquetas indicadas:

[INSTAGRAM]
Texto curto para Instagram, com linguagem clara e chamativa.
Depois do texto da noticia, em uma linha separada, escreva exatamente:
Saiba mais em:  https://terradorei.com.br/
Depois disso, deixe uma linha em branco e escreva as hashtags do Instagram em uma unica linha separada.

[JORNALISTICO]
Titulo: crie um titulo jornalistico com aproximadamente 50 caracteres.

Texto: escreva um artigo estruturado para blog/web.

Tags: sugira exatamente 3 tags relacionadas ao texto, na mesma linha, separadas por virgula.

NOTICIA:
$noticia
";

try {
    $ch = curl_init($OLLAMA_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $MODEL,
            'prompt' => $prompt,
            'stream' => false,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $GLOBALS['OLLAMA_CONNECT_TIMEOUT'] ?? 10,
        CURLOPT_TIMEOUT => $GLOBALS['OLLAMA_READ_TIMEOUT'] ?? 600,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new RuntimeException($curlError);
    }

    if ($httpCode === 0) {
        http_response_code(503);
        echo json_encode(['error' => 'Nao foi possivel conectar ao Ollama em http://ollama:11434. Verifique se o servico esta em execucao.']);
        exit;
    }

    if ($httpCode !== 200) {
        http_response_code(502);
        echo json_encode(['error' => "Ollama retornou HTTP $httpCode"]);
        exit;
    }

    $result = json_decode($response, true);

    if (!$result || !isset($result['response'])) {
        http_response_code(500);
        echo json_encode(['error' => 'Resposta invalida do Ollama', 'raw' => $response]);
        exit;
    }

    $texto = $result['response'];

    preg_match('/INSTAGRAM\]?[:\s\*\*]*(.*?)(?=\s*\[?JORNALISTICO\]?|$)/si', $texto, $instaMatch);
    preg_match('/JORNALISTICO\]?[:\s\*\*]*(.*?)$/si', $texto, $jornalMatch);

    $insta = isset($instaMatch[1]) ? trim($instaMatch[1]) : 'Texto Instagram nao identificado';
    $jornal = isset($jornalMatch[1]) ? trim($jornalMatch[1]) : 'Texto Jornalistico nao identificado';

    $titulo = title_around_50_chars($noticia);
    $insta = ensure_instagram_link_and_hashtags($insta);
    $jornal = ensure_jornalistico_structure($jornal, $titulo);
    $titulo = extract_structured_title($jornal, $titulo);

    $baseName = build_unique_base_name(slugify_text($titulo));

    $instagramFile = "$noticiasDir/{$baseName}_instagram.md";
    $wordpressFile = "$noticiasDir/{$baseName}_wordpress.md";

    file_put_contents($instagramFile, format_instagram_text($titulo, $insta), LOCK_EX);
    file_put_contents($wordpressFile, format_wordpress_text($titulo, $jornal, $baseName), LOCK_EX);

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'INSERT INTO noticias (titulo, noticia_original, texto_instagram, texto_jornalistico, slug) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$titulo, $noticia, $insta, $jornal, $baseName]);
    } catch (Exception $e) {
        // DB save is optional — files were already saved
    }

    echo json_encode([
        'instagram' => $insta,
        'jornal' => $jornal,
        'instagram_html' => render_markdown($insta),
        'jornal_html' => render_markdown($jornal),
        'arquivos' => [
            'pasta' => $noticiasDir,
            'instagram' => "{$baseName}_instagram.md",
            'wordpress' => "{$baseName}_wordpress.md",
        ],
    ]);

} catch (Exception $e) {
    $msg = $e->getMessage();

    if (str_contains($msg, 'Operation timed out') || str_contains($msg, 'timeout')) {
        http_response_code(504);
        echo json_encode([
            'error' => 'O Ollama demorou mais do que o esperado para responder. Isso costuma acontecer quando o modelo esta carregando, e pesado, ou recebeu um texto muito grande. Tente novamente em alguns segundos ou use uma noticia menor.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => "Erro ao chamar o Ollama: $msg"]);
    }
}
