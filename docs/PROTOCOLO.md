# Protocolo de aplicação do PULSEMONITOR (versão 1)

O protocolo tem dois canais:

- **Ingestão por HTTP.** Cada cliente envia eventos em JSON ao servidor. É um canal cliente para servidor, com confirmação por requisição.
- **Notificação por WebSocket.** O servidor avisa os painéis conectados de que algo mudou. É um canal servidor para cliente, usado para evitar polling.

Os dados completos ficam no servidor. As mensagens de WebSocket só carregam identificadores e um estado curto. Ao receber um aviso, o painel busca os dados por HTTP.

## 1. Identificação

| Papel | Como se identifica | Onde é validado |
|---|---|---|
| Cliente emissor (dispositivo, serviço) | `application_id` na URL e token `Bearer` próprio | Webhook: compara o SHA-256 do token com o hash salvo |
| Usuário do painel | E-mail e senha, com sessão por cookie | Login, com limite de 5 tentativas por e-mail e IP |
| Assinante de canal privado | Sessão do usuário | `POST /broadcasting/auth` só autoriza o canal do próprio usuário |

O token é mostrado uma única vez, ao ser gerado. O banco guarda só o hash.

## 2. Envio de evento (HTTP)

```
POST /api/v1/hooks/{application_id} HTTP/1.1
Host: servidor:8000
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

Corpo:

| Campo | Tipo | Obrigatório | Regras |
|---|---|---|---|
| `level` | texto | sim | `info`, `warning`, `warn` ou `error`. `warn` é gravado como `warning` |
| `message` | texto | sim | Até 2000 caracteres |
| `source` | texto | não | Até 255 caracteres. Identifica a origem dentro do cliente |
| `payload` | objeto JSON | não | Dados livres do evento |

Exemplo de requisição:

```json
{
  "level": "error",
  "message": "Sensor offline",
  "source": "sensor-b",
  "payload": { "code": "E42", "temperature_c": 23.4 }
}
```

Resposta de sucesso, `202 Accepted`:

```json
{ "id": 41, "received_at": "2026-11-05T22:41:15.000000Z" }
```

### Erros

Todos os erros do webhook são JSON. O campo `error` é um código estável para programas, e `message` é para pessoas.

| Status | `error` | Quando | Exemplo de resposta |
|---|---|---|---|
| 400 | `invalid_json` | Corpo declarado como JSON, mas malformado | `{"error":"invalid_json","message":"Body is not valid JSON: Syntax error."}` |
| 401 | `unauthorized` | Token ausente, errado ou de outro cliente | `{"error":"unauthorized","message":"Invalid or missing token."}` |
| 404 | sem código | `application_id` inexistente | `{"message":"..."}` |
| 422 | `validation_failed` | Campos ausentes ou inválidos | `{"error":"validation_failed","message":"The message has invalid fields.","errors":{"level":["..."]}}` |
| 429 | sem código | Mais de 600 requisições por minuto | `{"message":"Too Many Attempts."}` |

Toda rejeição é registrada com horário em `storage/logs/protocol-AAAA-MM-DD.log`. O conteúdo da mensagem rejeitada e o token não são gravados, só o tamanho, o IP e o motivo.

## 3. Sequência de operações

```
Cliente emissor            Servidor                Reverb            Painel (navegador)
      |                       |                       |                     |
      |                       |                       |<-- WS upgrade (101) |
      |                       |                       |--- connection_est ->|
      |                       |<------ POST /broadcasting/auth (sessão) ----|
      |                       |------- 200 {auth: assinatura} ------------->|
      |                       |                       |<-- pusher:subscribe |
      |                       |                       |--- subscription_ok->|
      | POST /hooks/{id}      |                       |                     |
      |---------------------->| valida e grava        |                     |
      |<--- 202 {id,...} -----|                       |                     |
      |                       |-- HTTP: publica ----->|                     |
      |                       |                       |--- evento (frame) ->|
      |                       |<----------- HTTP: busca dados atualizados --|
      |                       |------------ dados (HTML do componente) ---->|
```

## 4. Notificações (WebSocket)

A conexão fica em `ws://servidor:8080/app/{chave}`. O enquadramento segue o protocolo Pusher, implementado pelo Laravel Reverb. O conteúdo de cada evento é definido por este projeto.

| Canal | Quem assina | Autorização |
|---|---|---|
| `private-App.Models.User.{id}` | Painel do usuário | Assinatura obtida em `/broadcasting/auth` |
| `status` | Página pública | Nenhuma |

| Evento | Canais | Campos de `data` | Quando |
|---|---|---|---|
| `application-event.received` | privado | `id`, `application_id`, `level` | Evento aceito no webhook |
| `monitor.checked` | privado, e `status` se o monitor for público | `monitor_id`, `is_up` | Fim de uma verificação de monitor |
| `alert.changed` | privado, e `status` se for queda de monitor público | `id`, `status`, `type` | Alerta aberto, reconhecido ou resolvido |
| `experiment.updated` | privado | `id`, `status`, `progress` | Progresso de um experimento do Network Lab |

Exemplo de frame recebido pelo painel:

```json
{
  "event": "application-event.received",
  "channel": "private-App.Models.User.1",
  "data": "{\"id\":41,\"application_id\":1,\"level\":\"error\"}"
}
```

Mensagens de controle usadas:

| Mensagem | Sentido | Função |
|---|---|---|
| `pusher:connection_established` | servidor para cliente | Entrega o `socket_id` |
| `pusher:subscribe` | cliente para servidor | Assina um canal |
| `pusher_internal:subscription_succeeded` | servidor para cliente | Confirma a assinatura |
| `pusher:ping` e `pusher:pong` | ambos | Mantêm a conexão viva e detectam quedas |

## 5. Desconexões e indisponibilidade

| Situação | Comportamento |
|---|---|
| WebSocket cai | O cliente reconecta sozinho e o indicador `WS` do topo mostra o estado. Cada mudança é informada a `POST /telemetry/ws` e registrada no log como `ws.state` |
| WebSocket volta | O painel recarrega os dados de todos os componentes, porque avisos enviados durante a queda se perdem |
| Reverb fora do ar | O webhook e as verificações continuam funcionando. O erro de publicação vai para o log, e só a atualização em tempo real para |
| Monitor sem resposta | A falha é gravada com a mensagem de erro. Duas falhas seguidas abrem um alerta |
| Cliente sem resposta do servidor | Sem `202`, o evento não foi aceito e cabe ao cliente reenviar. Não há deduplicação, então um reenvio pode gravar o evento duas vezes |

## 6. Protocolo do microteste de sockets

Usado só no Experimento D, sem relação com o webhook. Os dois transportes usam a mesma mensagem:

```
<seq>|<instante de envio em µs>|<preenchimento com x até o tamanho pedido>\n
```

- **TCP:** o `\n` delimita as mensagens, porque TCP é um fluxo de bytes sem fronteiras. O servidor devolve cada linha.
- **UDP:** cada datagrama é uma mensagem e o servidor devolve o datagrama ao remetente.
- **Perda:** uma mensagem sem eco dentro do tempo limite conta como perdida.
- **Ordem:** um eco com `seq` menor que o maior já recebido conta como fora de ordem.
