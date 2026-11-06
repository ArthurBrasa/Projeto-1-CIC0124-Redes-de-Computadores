# Resultados dos experimentos

Gerados em 05/11/2026 por `scripts/experimentos.sh`, exceto `loopback_mac` e `internet`, medidos com `php artisan lab:run` no próprio Mac. O ambiente exato está em `dados/ambiente.txt`.

| Arquivo em `dados/` | Conteúdo |
|---|---|
| `<condição>-rtt.csv` e `.json` | Experimentos A e E: uma linha por amostra, e o resumo |
| `<condição>-throughput.csv` e `.json` | Experimento B: um passo por tamanho de mensagem |
| `<condição>-concurrency.csv` e `.json` | Experimento C: um passo por número de clientes |
| `<condição>-tcp-*.csv`, `<condição>-udp-*.csv` | Experimento D: uma linha por mensagem |
| `<condição>-pingpong-64b.json`, `<condição>-burst-512b.json` | Experimento D: resumo de TCP e UDP |

| Condição | Significado |
|---|---|
| `loopback_mac` | Cliente e servidor no mesmo Mac, na interface `lo0` |
| `internet` | Do Mac até `https://apps.dataverso.net/up` |
| `base` | Dois containers em rede Docker, sem netem |
| `atraso50ms` | `tc netem delay 50ms 10ms` na saída do cliente |
| `perda5pct` | `tc netem loss 5%` na saída do cliente |
| `atraso50ms_perda5pct` | Os dois juntos |

Unidades: tempos em ms, `goodput` em bytes por segundo e taxas em mensagens por segundo. Os resultados vêm de uma execução por condição.
