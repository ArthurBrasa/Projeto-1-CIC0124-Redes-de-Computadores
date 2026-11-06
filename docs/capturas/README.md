# Capturas de tráfego

Capturas de 05/11/2026. Abra no Wireshark e aplique o filtro indicado. Contêm só as credenciais públicas de demonstração.

| Arquivo | Cenário | Filtro sugerido |
|---|---|---|
| `captura1-webhook.pcap` | Dois clientes enviam eventos ao mesmo tempo, depois os casos 401, 400 e 422. Rede Docker, capturado no cliente | `tcp.stream==0` ou `http` |
| `captura2-websocket.pcapng` | Navegador real com login, assinatura do canal privado e evento vindo do segundo cliente. Interface `lo0` do Mac, nas portas publicadas 8101 e 8181 | `websocket \|\| http.response.code==101` |
| `captura3-tcp-udp.pcap` | As mesmas 5 mensagens de 64 bytes em TCP e em UDP. Rede Docker | `tcp.flags.syn==1 \|\| tcp.flags.fin==1 \|\| udp` |
| `captura4-perda-cliente.pcap` | `delay 50ms 10ms loss 5%` na saída do servidor, vista do cliente | `tcp.analysis.retransmission \|\| tcp.analysis.lost_segment` |
| `captura4-perda-servidor.pcap` | O mesmo instante, visto do servidor, depois do netem | `udp` ou `tcp` |

`captura4-*.csv` e `captura4-*.json` são as medições feitas pela aplicação durante a captura 4, usadas para a correlação.

As capturas 1, 3 e 4 são refeitas com `scripts/capturas.sh`. A captura 2 precisa de um navegador: suba o `docker compose`, rode `tshark -i lo0 -f "tcp port 8000 or tcp port 8080" -w saida.pcapng`, faça login, abra o painel e envie um evento pelo Postman.

No Linux, o `tcpdump` vê a transmissão depois da fila do netem. Por isso a condição fica na saída do servidor e a análise de perda é feita no cliente.
