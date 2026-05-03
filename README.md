# Gerador de Noticias IA

Aplicacao web simples em `Flask` para transformar uma noticia em dois formatos de texto com ajuda de um modelo local via `Ollama`:

- `Instagram`: texto curto, chamativo e direto
- `Jornalistico`: texto formal em estilo jornal

O sistema possui uma interface web onde o usuario cola uma noticia, envia o conteudo e recebe os dois resultados lado a lado.

## Como funciona

O fluxo da aplicacao e este:

1. O usuario acessa a pagina inicial.
2. Digita ou cola uma noticia no campo de texto.
3. O frontend envia o conteudo para a rota `POST /gerar`.
4. O backend monta um prompt estruturado.
5. O `Flask` envia esse prompt para o `Ollama`.
6. A resposta e separada em duas partes:
   - `[INSTAGRAM]`
   - `[JORNALISTICO]`
7. Os textos sao exibidos na interface.

## Tecnologias usadas

- `Python`
- `Flask`
- `Requests`
- `HTML`
- `CSS`
- `JavaScript`
- `Ollama`

## Estrutura do projeto

```text
projeto/
|-- app.py
|-- requirements.txt
|-- templates/
|   `-- index.html
|-- static/
|   `-- style.css
`-- README.md
```

## Requisitos

Antes de executar, voce precisa ter instalado:

- `Python 3.10+`
- `pip`
- `Ollama`
- Um modelo disponivel no Ollama com o nome configurado no codigo

No momento, o sistema esta configurado para usar:

```python
MODEL = "gemma4:e2b"
```

E espera o `Ollama` rodando localmente em:

```python
OLLAMA_URL = "http://localhost:11434/api/generate"
```

## Instalacao

Instale as dependencias Python com:

```bash
pip install -r requirements.txt
```

## Como executar

1. Inicie o `Ollama`.
2. Garanta que o modelo configurado exista localmente.
3. Execute a aplicacao:

```bash
python app.py
```

Depois, abra no navegador:

```text
http://127.0.0.1:5000
```

## Rotas da aplicacao

### `GET /`

Renderiza a interface principal.

### `POST /gerar`

Recebe um JSON no formato:

```json
{
  "noticia": "Texto da noticia aqui"
}
```

E retorna algo como:

```json
{
  "instagram": "Versao curta para Instagram",
  "jornal": "Versao formal em estilo jornalistico"
}
```

Se ocorrer erro, a API retorna:

```json
{
  "error": "mensagem de erro"
}
```

## Interface

A interface foi construida para ser simples:

- area de texto para entrada da noticia
- botao para gerar conteudo
- indicador de carregamento
- dois cards de saida
- layout responsivo para celular

## Observacoes

- A aplicacao depende do `Ollama` rodando localmente.
- Se o modelo nao existir ou o servico nao estiver ativo, a rota `/gerar` retornara erro.
- O parser da resposta espera que o modelo devolva os blocos `[INSTAGRAM]` e `[JORNALISTICO]`.
- A execucao esta com `debug=True`, ideal para desenvolvimento, nao para producao.

## Melhorias futuras

- tratar melhor falhas de conexao com o Ollama
- melhorar a validacao e separacao da resposta do modelo
- adicionar botao para copiar os textos gerados
- permitir troca de modelo por configuracao
- ajustar textos com problema de codificacao/acentuacao

## Autor

README criado para documentar o sistema atual do projeto.
