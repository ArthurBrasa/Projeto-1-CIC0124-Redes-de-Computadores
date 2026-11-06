# PULSEMONITOR

Aplicação distribuída de monitoramento em tempo real, feita para o Projeto 1 de Redes de Computadores (CIC0124, UnB, 2026).

Clientes enviam eventos por HTTP, monitores verificam a disponibilidade de serviços, o servidor grava tudo e os painéis web recebem atualizações por WebSocket. Alertas são abertos e resolvidos automaticamente por limiares.

- Protocolo de aplicação: [docs/PROTOCOLO.md](docs/PROTOCOLO.md)
- Resultados dos experimentos: [docs/experimentos/](docs/experimentos/)
- Capturas do Wireshark: [docs/capturas/](docs/capturas/)
- Coleção do Postman com dois clientes: [docs/postman/](docs/postman/)

## Dependências

Com Docker, basta o Docker com Compose. Sem Docker:

| Ferramenta | Versão |
|---|---|
| PHP, com `pdo_sqlite` e `pcntl` | 8.3 ou superior |
| Composer | 2 |
| Node.js e npm | 22 |

Principais bibliotecas: Laravel 13, Livewire 4, Laravel Reverb, Laravel Echo e pusher-js. A lista completa está em `composer.json` e `package.json`.

## Executar com Docker

```
docker compose up --build
```

Abra http://localhost:8000. O banco é criado e preenchido na primeira execução.

| Serviço | Função | Porta |
|---|---|---|
| `web` | Site e API HTTP | 8000 |
| `reverb` | Servidor WebSocket | 8080 |
| `queue` | Fila: notificações e experimentos | |
| `scheduler` | Verificações dos monitores e regras de alerta | |
| `sockets` | Servidor de eco TCP e UDP do Experimento D | 9601 |

Para outra máquina da rede acessar, use `http://IP-DO-SERVIDOR:8000`. O navegador conecta o WebSocket no mesmo endereço da página, na porta 8080.

## Executar sem Docker

```
composer setup
php artisan db:seed
php artisan dev            # site, fila, Reverb, Vite e logs
php artisan schedule:work  # em outro terminal: verificações dos monitores
```

Para aceitar conexões de outras máquinas, rode `php artisan serve --host=0.0.0.0`, gere os arquivos do front-end com `npm run build` e não deixe o Vite rodando.

## Dados de teste

Os dados abaixo são públicos e servem só para teste local.

| Item | Valor |
|---|---|
| Login | `demo@pulsemonitor.test` / `password` |
| Cliente A | `app_sensorlaba`, token `pm_demo_sensor_a_0000000000000000000000000` |
| Cliente B | `app_sensorlabb`, token `pm_demo_sensor_b_0000000000000000000000000` |

Enviar um evento:

```
curl -X POST http://localhost:8000/api/v1/hooks/app_sensorlaba \
  -H "Authorization: Bearer pm_demo_sensor_a_0000000000000000000000000" \
  -H "Content-Type: application/json" \
  -d '{"level":"error","message":"Sensor offline","source":"sensor-a"}'
```

Importe `docs/postman/pulsemonitor.postman_collection.json` no Postman para ter os dois clientes e todos os casos de erro prontos.

## Telas

| Rota | Conteúdo |
|---|---|
| `/` | Status público, sem login |
| `/dashboard` | Visão geral em tempo real |
| `/applications` | Clientes cadastrados, tokens e eventos |
| `/monitors` | Monitores de disponibilidade |
| `/events` | Histórico de eventos com filtros |
| `/alerts` | Alertas: ativos, reconhecidos e resolvidos |
| `/network-lab` | Experimentos de RTT, throughput e concorrência |
| `/settings` | Perfil e regras de alerta |

## Reproduzir os experimentos

Tudo de uma vez, entre dois containers, com atraso e perda simulados por `tc netem`. Leva cerca de 6 minutos:

```
docker build -t pulsemonitor .
scripts/experimentos.sh
```

Experimentos avulsos:

```
php artisan lab:run rtt --target=http://localhost:8000/up --samples=30 --label=local
php artisan lab:run throughput --target=http://localhost:8000 --size=all --clients=5 --duration=10 --label=local
php artisan lab:run concurrency --target=http://localhost:8000/up --levels=1,2,5 --duration=10 --label=local

php artisan lab:socket-server --port=9601
php artisan lab:socket-client --proto=both --mode=pingpong --count=200 --size=64
php artisan lab:socket-client --proto=both --mode=burst --count=2000 --size=512
```

Os arquivos saem em `storage/app/lab/`. Os testes de tamanho usam `/api/v1/lab/payload`, então o alvo precisa rodar este projeto.

## Testes e logs

```
php artisan test
```

O log do protocolo fica em `storage/logs/protocol-AAAA-MM-DD.log`, com horários em UTC. No Docker, ele fica no volume `logs`.
