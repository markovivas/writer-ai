<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Writer AI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/static/style.css">
</head>
<body>
    <main class="app-container">
        <header class="app-header">
            <h1>Writer AI</h1>
            <p>Simples. Direto. Inteligente.</p>
        </header>

        <section class="input-section">
            <textarea id="input" placeholder="Cole o texto ou link da notícia aqui..."></textarea>
            <button class="btn-main" onclick="gerar()">Processar Conteúdo</button>

            <div id="loader" class="loader hidden">
                <div class="spinner"></div>
                <span>Gerando...</span>
            </div>
        </section>

        <section id="results-area" class="results-area hidden">
            <div class="result-grid">
                <div class="result-card">
                    <header>Social</header>
                    <div id="out-social" class="content"></div>
                </div>
                <div class="result-card">
                    <header>Artigo</header>
                    <div id="out-article" class="content"></div>
                </div>
            </div>
            <div id="file-info" class="file-info"></div>
        </section>
    </main>

<script>
async function gerar() {
    const text = document.getElementById("input").value;
    const loader = document.getElementById("loader");
    const results = document.getElementById("results-area");

    if (!text) return;

    loader.classList.remove("hidden");
    results.classList.add("hidden");

    const res = await fetch("/gerar.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({ noticia: text })
    });

    const data = await res.json();
    loader.classList.add("hidden");

    if (data.error) return alert(data.error);

    document.getElementById("out-social").innerHTML = data.instagram_html;
    document.getElementById("out-article").innerHTML = data.jornal_html;
    results.classList.remove("hidden");

    if (data.arquivos) {
        document.getElementById("file-info").innerText = `Arquivos salvos: ${data.arquivos.instagram}`;
    }
}
</script>
</body>
</html>
