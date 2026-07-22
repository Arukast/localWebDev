FROM alpine:latest

RUN apk add --no-cache dnsmasq

COPY dnsmasq.conf /etc/dnsmasq.conf

EXPOSE 53/tcp 53/udp

CMD ["dnsmasq", "-k", "--user=root"]
