# Gerador de Noticias IA

Aplicacao web para transformar uma noticia em dois formatos de texto com ajuda de um modelo local via `Ollama`, rodando tudo em containers Docker:

- **Instagram**: texto curto, chamativo e direto
- **Jornalistico**: texto formal em estilo jornal

## Tecnologias

- `PHP 8.3` + `Apache`
- `MySQL 8.0`
- `Ollama` (modelo `gemma4:e2b`)
- `HTML` + `CSS` + `JavaScript`
- `Docker Compose`

## Estrutura

```
projeto/
├── docker-compose.yml    # Orquestracao dos servicos
├── Dockerfile.php        # Imagem PHP com PDO MySQL
├── sql/
│   └── init.sql          # Schema da tabela noticias
├── src/
│   ├── config.php        # Configuracoes e funcoes auxiliares
│   ├── index.php         # Interface web
│   ├── gerar.php         # Endpoint que chama Ollama e salva no DB
│   └── static/
│       └── style.css     # Estilos
└── noticias/             # Arquivos .md gerados
```

## Requisitos

- Docker Desktop (Windows) ou Docker + Docker Compose (Linux)
- Pelo menos 8 GB de RAM livre (recomendado 16 GB)

## Como executar

```bash
docker compose up -d
```

Acessar: [http://localhost:8090](http://localhost:8090)

## Fluxo

1. Usuario cola uma noticia na interface
2. Frontend envia para `POST /gerar.php`
3. O PHP monta um prompt estruturado e chama o Ollama
4. A resposta e separada em `[INSTAGRAM]` e `[JORNALISTICO]`
5. Os textos sao exibidos na tela, salvos em arquivos `.md` e no MySQL

## Servicos

| Servico | Porta | Descricao |
|---------|-------|-----------|
| Ollama | 11434 | API do modelo de linguagem |
| MySQL | 3306 | Banco de dados |
| PHP App | 8090 | Interface web |

## API

**POST /gerar.php**

```json
{ "noticia": "Texto da noticia aqui" }
```

Retorno:

```json
{
  "instagram": "Versao curta para Instagram",
  "jornal": "Versao formal em estilo jornalistico",
  "instagram_html": "<p>... HTML formatado ...</p>",
  "jornal_html": "<p>... HTML formatado ...</p>",
  "arquivos": {
    "instagram": "slug_instagram.md",
    "wordpress": "slug_wordpress.md"
  }
}
```

## Acessar o MySQL (HeidiSQL, DBeaver, etc.)

O banco fica exposto na porta `3306` do host:

| Campo | Valor |
|-------|-------|
| Host | `localhost` |
| Porta | `3306` |
| Usuario | `writer` |
| Senha | `writerpass` |
| Database | `writer_ai` |

Credenciais de root: usuario `root`, senha `rootpass`.

Tabela `noticias` criada automaticamente na primeira inicializacao.

## Comandos uteis

```bash
# Acompanhar logs
docker compose logs -f

# Parar servicos
docker compose down

# Parar e remover volumes (dados do banco e modelos)
docker compose down -v

# Verificar status
docker compose ps
```
