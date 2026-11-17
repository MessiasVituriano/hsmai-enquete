# Enquete ao vivo — Commercial Strategy Conference

Enquete de auditório para o painel: a plateia escaneia o QR code do telão,
faz um cadastro rápido (nome, e-mail ou WhatsApp, hotel), responde 4
perguntas no celular, e o facilitador
leva o resultado de cada pergunta ao telão logo depois dela.

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
| **Resultado da pergunta N** (*Mostrar resultado*) | as barras crescem do zero e os percentuais contam até o valor | o resultado, com a resposta da pessoa marcada |

A ordem do **Próxima ›** é convite → pergunta 1 → resultado 1 → pergunta 2 →
resultado 2 → … Cada pergunta fecha com o próprio resultado antes de a próxima
abrir. O painel
mostra os resultados de todas as perguntas ao vivo desde o primeiro voto (é a
única tela que vê isso, e ela não vai ao projetor). Voltar a **Perguntar
agora** uma pergunta cujo resultado já apareceu esconde o resultado de novo.

**Encerrar respostas** trava os botões nos celulares, e o telão avisa que as respostas foram encerradas.
**Zerar tudo** apaga cadastros e respostas e volta ao convite (para depois do
ensaio; baixe a planilha antes).

## Regras

- **O celular espelha o telão.** O servidor só manda ao celular (e ao telão)
  a pergunta selecionada no painel, e o resultado dela só na fase de
  resultado. A troca chega aos celulares em até 2s. Uma resposta é aceita para
  qualquer pergunta, não só para a da tela: se o painel troca no segundo em
  que alguém tocou, o voto não se perde.

- **Cadastro antes de responder.** Nome, **e-mail ou WhatsApp** (a pessoa
  escolhe; um dos dois basta) e hotel. Sem cadastro o servidor recusa a
  resposta (403). O ideal é a sala se cadastrar durante o convite, enquanto o
  QR ainda está no telão. Depois, "editar dados" no topo do celular corrige
  tudo.
- **Uma pessoa, um cadastro.** E-mail e WhatsApp são únicos. O WhatsApp é
  guardado só com os dígitos, sem o 55, então `+55 (11) 98888-7777` e
  `11988887777` são a mesma pessoa. Se alguém se cadastra num aparelho novo
  com um contato que já existe (trocou de celular, limpou o navegador), o
  aparelho **adota** aquele cadastro e as respostas dele em vez de duplicar a
  pessoa. Um cadastro existente que tenta usar o contato de outra pessoa
  recebe erro.
- **Um voto por pessoa e pergunta.** O celular guarda um UUID no
  `localStorage`, o mesmo do cadastro, e o servidor tem índice único
  `(respondent, question_id)`. Tocar em outra alternativa **troca** a resposta
  na mesma linha. Vale até o facilitador encerrar.
- **Percentuais somam 100.** Arredondamento pelo maior resto, para o telão
  não mostrar "33% · 66%".
- **Ordem fixa das alternativas** nos resultados: a sala reconhece a
  pergunta pela ordem em que leu as opções.

## Participantes e planilha

O painel tem a seção **Participantes**: a lista dos cadastros (nome,
contato, hotel e o que cada um respondeu), com busca, atualizada a cada 5
segundos. **⬇ Baixar planilha (CSV)** gera o arquivo com tudo isso e a data do
cadastro, pronto para o Excel. Baixe a planilha antes de **Zerar tudo**.

O contato só aparece no painel e no celular do próprio dono, nunca no telão.

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

## No mesmo servidor do ROC

O ROC já ocupa as portas 80/443 com o Caddy dele, e a enquete não mexe nele.
Há dois jeitos de rodar:

**Modo só IP (convive com o ROC no ar).** Sem Caddy próprio, o nginx atende
direto numa porta. Não tem HTTPS: o navegador marca "Não seguro", mas funciona.
No `.env` da enquete:

```ini
COMPOSE_FILE=docker-compose.yml:docker-compose.ip.yml
ENQUETE_PORT=8080
APP_URL=http://IP-DO-SERVIDOR:8080
```

Depois rode `docker compose up -d --build` e abra `http://IP-DO-SERVIDOR:8080/display`.
A porta precisa estar liberada no firewall (`ufw allow 8080/tcp`, se o ufw
estiver ativo).

**Com HTTPS, com o ROC parado.** Pare o ROC (`docker compose stop` na pasta
dele; os dados ficam), tire as duas linhas `COMPOSE_FILE`/`ENQUETE_PORT` do
`.env` da enquete, defina `APP_DOMAIN`/`APP_URL` com o endereço sslip.io e
rode `docker compose up -d`. Para devolver as portas ao ROC:
`docker compose stop caddy` na enquete e `docker compose start` no ROC.

## API

| Método | Rota | Uso |
|---|---|---|
| `GET` | `/api/poll` | poll do celular (2s): a pergunta selecionada, o que este aparelho marcou, o resultado na fase de resultado |
| `POST` | `/api/register` | `{name, email \| phone, hotel}`: cadastra ou corrige; devolve o `token` do aparelho |
| `POST` | `/api/answer` | `{question_id, option_id}`: marca ou troca (exige cadastro) |
| `GET` | `/api/display` | poll do telão (1s) |
| `GET` | `/api/admin/overview` | poll do painel (1s), com todos os resultados |
| `POST` | `/api/admin/screen` | `{screen: invite\|question\|results, question_id?}` |
| `POST` | `/api/admin/accepting` | `{accepting: bool}` |
| `POST` | `/api/admin/reset` | apaga cadastros, respostas e liberações |
| `GET` | `/api/admin/respondents` | os cadastros com as respostas (o painel busca a cada 5s) |
| `GET` | `/api/admin/respondents.csv` | a planilha: `;` e BOM UTF-8, abre direto no Excel |

O celular se identifica pelo header `X-Respondent` (UUID); o painel, por
`X-Master-Token`.
