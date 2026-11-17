# Enquete ao vivo — Commercial Strategy Conference

Enquete de auditório para o painel: a plateia escaneia o QR code do telão,
responde 4 perguntas no celular em ~30 segundos, sem cadastro, e o facilitador
leva os resultados ao telão ao longo do painel.

Feita a partir da base do projeto ROC (mesma stack, mesmo deploy), sem
missões, fases, mesas nem pontuação.

| Tela | URL | Quem usa |
|---|---|---|
| Celular | `/` (destino do QR code) | a plateia |
| **Telão** | **`/display`** | o projetor (duplo clique alterna tela cheia) |
| Painel | `/master` (ou `/master?token=SEU_TOKEN` uma vez) | quem conduz |

## As perguntas

Estão em [`config/enquete.php`](config/enquete.php), junto com título,
subtítulo e chamada do telão. Trechos entre `**asteriscos**` saem em negrito.

1. Quantos de vocês, nos últimos anos, foram **abordados por TMCs ou Travel
   Techs** pedindo uma condição de negociação **diferente do "comum"**
   (comissão ou NET)? — *Sim / Não*
2. Quantos ficaram **preocupados ou confusos** sobre quais critérios analisar
   e quais condições aplicar nesses novos modelos? — *Sim / Não*
3. Quantos **revisaram os critérios** dessas parcerias nos últimos 12 meses e
   fazem **análises recorrentes**? — *Sim / Não*
4. Qual é hoje o seu **principal critério** para fechar com uma Travel Tech? —
   *Comissão · Mark-up · Volume de pernoites · Facilidade de integração e
   distribuição* (escolha única)

O seeder sincroniza o config com o banco **pela posição** e é idempotente:
mudou um texto, rode `php artisan db:seed --force` (em produção o container
faz isso sozinho a cada boot). Nada duplica e nenhuma resposta se perde — só
some o voto de uma alternativa que você tirar do config.

## O roteiro no painel

| Momento | Botão no `/master` | Telão | Celulares |
|---|---|---|---|
| Abertura | **Convite + QR** | o slide com as 4 perguntas, o QR e quantas pessoas já responderam | respondem |
| Ao longo do painel | **Pergunta N** (ou *Mostrar no telão* no card) | o resultado daquela pergunta, ao vivo, com um QR menor para quem chegou atrasado | o resultado dela aparece abaixo da pergunta |
| Fecho | **Todas** | as 4 perguntas lado a lado | todos os resultados |
| Quando quiser | **Encerrar respostas** | o convite passa a dizer "Enquete encerrada" | os botões travam |

O painel mostra os resultados de **todas** as perguntas ao vivo, desde o
primeiro voto — ele nunca vai ao projetor. Telão e celulares só recebem o
resultado de uma pergunta depois que ela foi ao telão; **Recolher** desfaz
isso. **Zerar tudo** apaga as respostas e volta ao convite (para depois do
ensaio).

## Regras

- **Anônimo, um voto por aparelho.** O celular gera um UUID e guarda no
  `localStorage`; o servidor tem índice único `(respondent, question_id)`.
  Tocar em outra alternativa **troca** a resposta na mesma linha — vale até o
  facilitador encerrar. Quem limpa os dados do navegador vota de novo: é o
  preço de não ter cadastro, e numa enquete de sala isso é aceitável.
- **Percentuais somam 100.** Arredondamento pelo maior resto, para o telão
  não mostrar "33% · 66%".
- **Ordem fixa das alternativas** nos resultados: a sala reconhece a
  pergunta pela ordem em que leu as opções.

## Logos

O telão tem dois espaços opcionais, como no slide original: canto superior
direito e rodapé à esquerda. Coloque os arquivos oficiais em `public/brand/`:

```
public/brand/logo-conferencia.png   # canto superior direito
public/brand/logo-hsmai.png         # rodapé (fundo roxo: use a versão branca)
```

Outros nomes ou formatos: `LOGO_HEADER` / `LOGO_FOOTER` no `.env`. Sem o
arquivo, o espaço simplesmente some.

O crédito **"desenvolvido por: Sistema IO"** com o símbolo da marca está no
rodapé de todas as telas (componente `DevelopedBy`): no telão, à direita da
faixa roxa, num selo branco para o azul do símbolo não sumir no roxo; no
celular, no fim da lista de perguntas; no painel, no rodapé e no login.

## Rodando localmente

```bash
cp .env.example .env     # para testar rápido: DB_CONNECTION=sqlite
php artisan key:generate
composer install && npm install
php artisan migrate --seed
composer run dev         # servidor + vite
```

Para testar no celular pela rede local, suba com
`php artisan serve --host=0.0.0.0` e abra o `/display` pelo IP da máquina —
o QR code aponta para o mesmo host em que o telão foi aberto.

Testes: `php artisan test`.

## Subindo em produção

Igual ao ROC: `docker-compose.yml` com Caddy (TLS automático) → nginx →
php-fpm → Postgres.

```bash
git clone <repo> enquete && cd enquete
cp .env.example .env   # APP_ENV=production, APP_DEBUG=false, APP_URL, APP_DOMAIN,
                       # DB_PASSWORD forte, MASTER_TOKEN forte, APP_KEY
docker compose up -d --build
```

O [entrypoint](docker/php/entrypoint.sh) roda `migrate` e `db:seed` a cada
boot — não há passo manual. Sem domínio próprio, `APP_DOMAIN=203-0-113-10.sslip.io`
(o IP do servidor com hífens) emite certificado normalmente. Não vá para o
evento em HTTP: o aviso de "site não seguro" aparece justamente no celular de
quem escaneou o QR.

Atualizações: `./scripts/deploy.sh` (faz backup do banco antes e recusa
rodar com a enquete aberta e já recebendo respostas, a não ser com `--force`).

## API

| Método | Rota | Uso |
|---|---|---|
| `GET` | `/api/poll` | poll do celular (3s): perguntas, o que este aparelho marcou, resultados liberados |
| `POST` | `/api/answer` | `{question_id, option_id}` — marca ou troca |
| `GET` | `/api/display` | poll do telão (1s) |
| `GET` | `/api/admin/overview` | poll do painel (1s), com todos os resultados |
| `POST` | `/api/admin/screen` | `{screen: invite\|question\|all, question_id?}` |
| `POST` | `/api/admin/questions/{id}/hide` | recolhe o resultado de uma pergunta |
| `POST` | `/api/admin/accepting` | `{accepting: bool}` |
| `POST` | `/api/admin/reset` | apaga respostas e liberações |

O celular se identifica pelo header `X-Respondent` (UUID); o painel, por
`X-Master-Token`.
