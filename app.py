from pathlib import Path
import html
import re
import unicodedata

from flask import Flask, jsonify, render_template, request
import markdown
import requests

app = Flask(__name__)

OLLAMA_URL = "http://localhost:11434/api/generate"
MODEL = "gemma4:e2b"
NOTICIAS_DIR = Path(app.root_path) / "noticias"
OLLAMA_CONNECT_TIMEOUT = 10
OLLAMA_READ_TIMEOUT = 600
SITE_URL = "https://terradorei.com.br/"
DEFAULT_INSTAGRAM_HASHTAGS = "#noticias #terradorei #informacao"
DEFAULT_JORNALISTICO_TAGS = "noticias, atualidade, informacao"


def slugify_text(text):
    normalized = unicodedata.normalize("NFKD", text)
    ascii_text = normalized.encode("ascii", "ignore").decode("ascii")
    cleaned = re.sub(r"[^a-zA-Z0-9]+", "_", ascii_text.lower()).strip("_")
    return cleaned or "noticia"


def extract_news_title(noticia):
    linhas = [linha.strip() for linha in noticia.splitlines() if linha.strip()]
    if not linhas:
        return "Noticia sem titulo"

    titulo = linhas[0]
    palavras = titulo.split()
    if len(palavras) > 12:
        titulo = " ".join(palavras[:12])

    return titulo[:80].strip() or "Noticia sem titulo"


def title_around_50_chars(text):
    title = extract_news_title(text)

    if len(title) <= 55:
        return title

    shortened = title[:55].rsplit(" ", 1)[0].strip()
    return shortened or title[:55].strip()


def ensure_instagram_link_and_hashtags(text):
    content = text.strip()
    link_line = f"Saiba mais em:  {SITE_URL}"

    content = re.sub(
        r"Saiba mais em:\s*https?://terradorei\.com\.br/?",
        "",
        content,
        flags=re.IGNORECASE,
    ).strip()

    hashtag_line_match = re.search(
        r"(?m)^\s*(?:HASHTAGS?:\s*)?(#\w+(?:\s+#\w+)*)\s*$",
        content,
        flags=re.IGNORECASE,
    )

    if hashtag_line_match:
        hashtags = hashtag_line_match.group(1).strip()
        body = (
            content[: hashtag_line_match.start()]
            + content[hashtag_line_match.end() :]
        ).strip()
    else:
        hashtags = DEFAULT_INSTAGRAM_HASHTAGS
        body = content

    return f"{body}\n\n{link_line}\n\n{hashtags}".strip()


def ensure_jornalistico_structure(text, fallback_title):
    content = text.strip()
    title_match = re.search(r"(?im)^\s*T(?:itulo|.tulo)\s*:\s*(.+)$", content)
    tags_match = re.search(r"(?im)^\s*Tags?\s*:\s*(.+)$", content)

    title = title_match.group(1).strip() if title_match else fallback_title
    title = title_around_50_chars(title)
    tags = tags_match.group(1).strip() if tags_match else DEFAULT_JORNALISTICO_TAGS
    tags = ", ".join([tag.strip() for tag in tags.split(",") if tag.strip()][:3])
    tags = tags or DEFAULT_JORNALISTICO_TAGS

    if tags_match:
        content = (
            content[: tags_match.start()]
            + content[tags_match.end() :]
        ).strip()

    if title_match:
        content = (
            content[: title_match.start()]
            + content[title_match.end() :]
        ).strip()

    content = re.sub(r"(?im)^\s*Texto\s*:\s*", "", content).strip()

    return (
        f"Titulo: {title}\n\n"
        f"{content}\n\n"
        f"Tags: {tags}"
    ).strip()


def extract_structured_title(text, fallback_title):
    title_match = re.search(r"(?im)^\s*T(?:itulo|.tulo)\s*:\s*(.+)$", text)
    if not title_match:
        return fallback_title

    return title_around_50_chars(title_match.group(1).strip())


def build_unique_base_name(base_name):
    candidate = base_name
    counter = 2

    while (
        (NOTICIAS_DIR / f"{candidate}_instagram.md").exists()
        or (NOTICIAS_DIR / f"{candidate}_wordpress.md").exists()
    ):
        candidate = f"{base_name}_{counter}"
        counter += 1

    return candidate


def format_instagram_text(titulo, conteudo):
    conteudo = ensure_instagram_link_and_hashtags(conteudo)

    return (
        f"TITULO: {titulo}\n\n"
        "LEGENDA:\n"
        f"{conteudo}"
    )


def format_wordpress_text(titulo, conteudo, slug):
    meta_descricao = conteudo.strip().replace("\n", " ")
    meta_descricao = meta_descricao[:155].strip()

    return (
        f"Titulo: {titulo}\n"
        f"Slug: {slug}\n"
        f"Meta descricao: {meta_descricao}\n\n"
        "Conteudo:\n\n"
        f"{conteudo.strip()}"
    )


def render_markdown(texto):
    safe_text = html.escape(texto or "")
    return markdown.markdown(
        safe_text,
        extensions=["extra", "nl2br", "sane_lists"],
    )


@app.route("/")
def home():
    return render_template("index.html")


@app.route("/gerar", methods=["POST"])
def gerar():
    data = request.json or {}
    noticia = data.get("noticia", "").strip()

    if not noticia:
        return jsonify({"error": "Digite uma noticia antes de gerar."}), 400

    NOTICIAS_DIR.mkdir(exist_ok=True)

    prompt = f"""
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
{noticia}
"""

    try:
        response = requests.post(
            OLLAMA_URL,
            json={
                "model": MODEL,
                "prompt": prompt,
                "stream": False,
            },
            timeout=(OLLAMA_CONNECT_TIMEOUT, OLLAMA_READ_TIMEOUT),
        )
        response.raise_for_status()

        result = response.json()

        if "response" not in result:
            return jsonify({"error": result}), 500

        texto = result["response"]

        # Parser mais robusto usando Regex ou busca flexÃ­vel
        insta_match = re.search(r"INSTAGRAM\]?[:\s\*\*]*(.*?)(?=\s*\[?JORNALISTICO\]?|$)", texto, re.DOTALL | re.IGNORECASE)
        jornal_match = re.search(r"JORNALISTICO\]?[:\s\*\*]*(.*?)$", texto, re.DOTALL | re.IGNORECASE)

        insta = insta_match.group(1).strip() if insta_match else "Texto Instagram nÃ£o identificado"
        jornal = jornal_match.group(1).strip() if jornal_match else "Texto JornalÃ­stico nÃ£o identificado"

        titulo = title_around_50_chars(noticia)
        insta = ensure_instagram_link_and_hashtags(insta)
        jornal = ensure_jornalistico_structure(jornal, titulo)
        titulo = extract_structured_title(jornal, titulo)
        
        base_name = build_unique_base_name(slugify_text(titulo))

        instagram_file = NOTICIAS_DIR / f"{base_name}_instagram.md"
        wordpress_file = NOTICIAS_DIR / f"{base_name}_wordpress.md"

        instagram_file.write_text(
            format_instagram_text(titulo, insta),
            encoding="utf-8",
        )
        wordpress_file.write_text(
            format_wordpress_text(titulo, jornal, base_name),
            encoding="utf-8",
        )

        return jsonify(
            {
                "instagram": insta,
                "jornal": jornal,
                "instagram_html": render_markdown(insta),
                "jornal_html": render_markdown(jornal),
                "arquivos": {
                    "pasta": str(NOTICIAS_DIR),
                    "instagram": instagram_file.name,
                    "wordpress": wordpress_file.name,
                },
            }
        )

    except requests.exceptions.ReadTimeout:
        return jsonify(
            {
                "error": (
                    "O Ollama demorou mais do que o esperado para responder. "
                    "Isso costuma acontecer quando o modelo esta carregando, e pesado, "
                    "ou recebeu um texto muito grande. Tente novamente em alguns segundos "
                    "ou use uma noticia menor."
                )
            }
        ), 504
    except requests.exceptions.ConnectionError:
        return jsonify(
            {
                "error": (
                    "Nao foi possivel conectar ao Ollama em http://localhost:11434. "
                    "Verifique se o servico esta em execucao."
                )
            }
        ), 503
    except requests.exceptions.RequestException as e:
        return jsonify({"error": f"Erro ao chamar o Ollama: {str(e)}"}), 502
    except Exception as e:
        return jsonify({"error": str(e)}), 500


if __name__ == "__main__":
    app.run(debug=True)

