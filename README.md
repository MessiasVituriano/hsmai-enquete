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

Uma coisa por vez, no telão e no celular ao mesmo tempo. O telão nunca mostra
a lista inteira de perguntas. **Próxima ›** percorre o roteiro em ordem, e os
botões de cada pergunta pulam para qualquer ponto dele.

| Passo | Telão | Celulares |
|---|---|---|
| **Convite + QR code** | só o QR code, a chamada e quantas pessoas já responderam | "Você está dentro!", aguardando |
| **Pergunta N** (*Perguntar agora*) | a pergunta N com as alternativas e o número de respostas subindo, sem resultado | a pergunta N para responder |
| **Resultado da pergunta N** (*Mostrar resultado*), no final | as barras com o percentual de cada alternativa | o resultado, com a resposta da pessoa marcada |

A ordem do **Próxima ›** é convite → pergunta 1…4 → resultado 1…4. O painel
mostra os resultados de todas as perguntas ao vivo desde o primeiro voto (é a
única tela que vê isso, e ela não vai ao projetor). Voltar a **Perguntar
agora** uma pergunta cujo resultado já apareceu esconde o resultado de novo.

**Encerrar respostas** trava os botões nos celulares e tira o QR do telão.
**Zerar tudo** apaga as respostas e volta ao convite (para depois do ensaio).

## Regras

- **O celular espelha o telão.** O servidor só manda ao celular (e ao telão)
  a pergunta selecionada no painel, e o resultado dela só na fase de
  resultado. A troca chega aos celulares em até 2s. Uma resposta é aceita para
  qualquer pergunta, não só para a da tela: se o painel troca no segundo em
  que alguém tocou, o voto não se perde.

- **Anônimo, um voto por aparelho.** O celular gera um UUID e guarda no
  `localStorage`; o servidor tem índice único `(respondent, question_id)`.
  Tocar em outra alternativa **troca** a resposta na mesma linha — vale até o
  facilitador encerrar. Quem limpa os dados do navegador vota de novo: é o
  preço de não ter cadastro, e numa enquete de sala isso é aceitável.
- **Percentuais somam 100.** Arredondamento pelo maior resto, para o telão
  não mostrar "33% · 66%".
- **Ordem fixa das alternativas** nos resultados: a sala reconhece a
  pergunta pela ordem em que leu as opções.

## Cores

Paleta da Sistema IO, tirada do símbolo: azul `#0b4c93` / `#1b86c4` e verde
`#8cc63f` / `#3f9a2a`. Os tokens ficam no `@theme` de
[`resources/css/app.css`](resources/css/app.css) (`brand-*` é o azul,
`accent-*` o verde); as barras de resultado usam a lista `PALETTE` de
`ResultBars.vue`.

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
| `GET` | `/api/poll` | poll do celular (2s): a pergunta selecionada, o que este aparelho marcou, o resultado na fase de resultado |
| `POST` | `/api/answer` | `{question_id, option_id}` — marca ou troca |
| `GET` | `/api/display` | poll do telão (1s) |
| `GET` | `/api/admin/overview` | poll do painel (1s), com todos os resultados |
| `POST` | `/api/admin/screen` | `{screen: invite\|question\|results, question_id?}` |
| `POST` | `/api/admin/accepting` | `{accepting: bool}` |
| `POST` | `/api/admin/reset` | apaga respostas e liberações |

O celular se identifica pelo header `X-Respondent` (UUID); o painel, por
`X-Master-Token`.
