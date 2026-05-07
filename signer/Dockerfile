# Сборочный образ
FROM golang:1.22-alpine AS build
WORKDIR /src
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 go build -trimpath -ldflags="-s -w" -o /signer ./...

# Минимальный рантайм. Сертификат и ключ монтируются как secrets/volumes.
FROM alpine:3.19
RUN apk add --no-cache ca-certificates
WORKDIR /app
COPY --from=build /signer /app/signer

# Где ожидаем cert/key. На Render/Fly используется секрет-volume.
# Локально:
#   docker run -p 8080:8080 \
#     -v /path/to/cert.pem:/secrets/cert.pem:ro \
#     -v /path/to/key.pem:/secrets/key.pem:ro \
#     -e SIGNER_API_KEY=changeme \
#     -e SIGNER_CERT_PATH=/secrets/cert.pem \
#     -e SIGNER_KEY_PATH=/secrets/key.pem \
#     era-signer
ENV SIGNER_CERT_PATH=/secrets/cert.pem
ENV SIGNER_KEY_PATH=/secrets/key.pem
ENV PORT=8080
EXPOSE 8080
ENTRYPOINT ["/app/signer"]
