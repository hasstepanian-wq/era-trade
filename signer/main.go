// Signer — микросервис для ГОСТ-подписи запросов к ЕСИА.
//
// На InfinityFree (или любом другом шаред-PHP-хостинге) невозможно поднять
// CryptoPro CSP / нативное расширение GOST. Этот сервис закрывает это:
// один HTTP-эндпоинт принимает строку для подписи, возвращает
// PKCS#7-detached в base64url — точно в том виде, в котором ЕСИА требует
// `client_secret` для запроса /aas/oauth2/v3/te.
//
// Сервис задумывался как stateless и одноклассовый. Деплой:
//   • Render.com Free / Fly.io Free — для теста
//   • Beget VPS Start (≈200 ₽/мес) — для прода
//
// Endpoints:
//   POST /sign     — подписать строку (требует X-API-Key)
//   GET  /health   — статус (без авторизации)
//
// Контракт /sign:
//   Request:  {"payload": "<utf-8 строка ESIA>"}
//   Response: {"signature": "<base64url-PKCS7>", "alg": "GOST3410-2012-256"}
package main

import (
	"crypto/x509"
	"encoding/base64"
	"encoding/json"
	"encoding/pem"
	"errors"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"strings"
	"time"

	"github.com/Theo730/gogost/gost3410"
	"github.com/Theo730/gogost/gost34112012256"
	"github.com/Theo730/pkcs7"
)

const algName = "GOST3410-2012-256"

type signRequest struct {
	Payload string `json:"payload"`
}

type signResponse struct {
	Signature string `json:"signature"`
	Alg       string `json:"alg"`
}

type errorResponse struct {
	Error string `json:"error"`
}

type signerCtx struct {
	apiKey  string
	cert    *x509.Certificate
	privKey *gost3410.PrivateKey
}

func main() {
	port := getenv("PORT", "8080")
	apiKey := mustGetenv("SIGNER_API_KEY")
	certPath := mustGetenv("SIGNER_CERT_PATH")
	keyPath := mustGetenv("SIGNER_KEY_PATH")

	cert, err := loadCertificate(certPath)
	if err != nil {
		log.Fatalf("failed to load certificate from %s: %v", certPath, err)
	}
	priv, err := loadPrivateKey(keyPath)
	if err != nil {
		log.Fatalf("failed to load private key from %s: %v", keyPath, err)
	}

	ctx := &signerCtx{apiKey: apiKey, cert: cert, privKey: priv}

	mux := http.NewServeMux()
	mux.HandleFunc("/health", handleHealth)
	mux.HandleFunc("/sign", ctx.handleSign)
	mux.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/" {
			w.WriteHeader(http.StatusOK)
			fmt.Fprintln(w, "ESIA signer service. POST /sign or GET /health.")
			return
		}
		http.NotFound(w, r)
	})

	srv := &http.Server{
		Addr:              ":" + port,
		Handler:           withLogging(mux),
		ReadHeaderTimeout: 5 * time.Second,
		ReadTimeout:       15 * time.Second,
		WriteTimeout:      15 * time.Second,
		IdleTimeout:       30 * time.Second,
	}
	log.Printf("signer ready on :%s (alg=%s, cert=%s)", port, algName, cert.Subject.CommonName)
	if err := srv.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
		log.Fatal(err)
	}
}

func handleHealth(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(w).Encode(map[string]any{
		"status": "ok",
		"alg":    algName,
		"time":   time.Now().UTC().Format(time.RFC3339),
	})
}

func (s *signerCtx) handleSign(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeError(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}
	if r.Header.Get("X-API-Key") != s.apiKey {
		writeError(w, http.StatusUnauthorized, "invalid X-API-Key")
		return
	}
	body, err := io.ReadAll(io.LimitReader(r.Body, 1<<20))
	if err != nil {
		writeError(w, http.StatusBadRequest, "read body: "+err.Error())
		return
	}
	var req signRequest
	if err := json.Unmarshal(body, &req); err != nil {
		writeError(w, http.StatusBadRequest, "invalid JSON: "+err.Error())
		return
	}
	if req.Payload == "" {
		writeError(w, http.StatusBadRequest, "payload is required")
		return
	}

	sigB64, err := s.signPayload([]byte(req.Payload))
	if err != nil {
		log.Printf("sign error: %v", err)
		writeError(w, http.StatusInternalServerError, "sign failed: "+err.Error())
		return
	}
	w.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(w).Encode(signResponse{Signature: sigB64, Alg: algName})
}

// signPayload вычисляет Streebog-256 от payload, подписывает GOST 34.10-2012-256,
// упаковывает в PKCS#7 SignedData (detached, без encapContentInfo) и кодирует в
// base64url без паддинга — формат, который ЕСИА ждёт в client_secret.
func (s *signerCtx) signPayload(payload []byte) (string, error) {
	h := gost34112012256.New()
	if _, err := h.Write(payload); err != nil {
		return "", fmt.Errorf("hash: %w", err)
	}
	digest := h.Sum(nil)
	// gogost ожидает digest в обратном порядке байт (little-endian).
	reverseInPlace(digest)

	rawSig, err := s.privKey.SignDigest(digest, randSource{})
	if err != nil {
		return "", fmt.Errorf("gost3410 sign: %w", err)
	}

	sd, err := pkcs7.NewSignedData()
	if err != nil {
		return "", fmt.Errorf("new signed data: %w", err)
	}
	if err := sd.AddSigner(s.cert, s.privKey, rawSig); err != nil {
		return "", fmt.Errorf("add signer: %w", err)
	}
	out, err := sd.Finish()
	if err != nil {
		return "", fmt.Errorf("finish: %w", err)
	}
	return base64.RawURLEncoding.EncodeToString(out), nil
}

func reverseInPlace(b []byte) {
	for i, j := 0, len(b)-1; i < j; i, j = i+1, j-1 {
		b[i], b[j] = b[j], b[i]
	}
}

// randSource — оборачивает crypto/rand в io.Reader для gost3410.SignDigest.
type randSource struct{}

func (randSource) Read(p []byte) (int, error) {
	return cryptoRandRead(p)
}

func loadCertificate(path string) (*x509.Certificate, error) {
	raw, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}
	block, _ := pem.Decode(raw)
	if block == nil || !strings.Contains(block.Type, "CERTIFICATE") {
		return nil, errors.New("no CERTIFICATE PEM block found")
	}
	return x509.ParseCertificate(block.Bytes)
}

func loadPrivateKey(path string) (*gost3410.PrivateKey, error) {
	raw, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}
	block, _ := pem.Decode(raw)
	if block == nil {
		return nil, errors.New("no PEM block found")
	}
	if !strings.Contains(block.Type, "PRIVATE KEY") {
		return nil, fmt.Errorf("expected PRIVATE KEY PEM, got %q", block.Type)
	}
	key, err := pkcs7.ParsePKCS8PrivateKey(block.Bytes)
	if err != nil {
		return nil, fmt.Errorf("parse pkcs8: %w", err)
	}
	return key, nil
}

func withLogging(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		rw := &statusWriter{ResponseWriter: w, status: 200}
		next.ServeHTTP(rw, r)
		log.Printf("%s %s -> %d (%s)", r.Method, r.URL.Path, rw.status, time.Since(start))
	})
}

type statusWriter struct {
	http.ResponseWriter
	status int
}

func (s *statusWriter) WriteHeader(code int) {
	s.status = code
	s.ResponseWriter.WriteHeader(code)
}

func writeError(w http.ResponseWriter, code int, msg string) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(code)
	_ = json.NewEncoder(w).Encode(errorResponse{Error: msg})
}

func getenv(k, def string) string {
	if v := os.Getenv(k); v != "" {
		return v
	}
	return def
}

func mustGetenv(k string) string {
	v := os.Getenv(k)
	if v == "" {
		log.Fatalf("env %s is required", k)
	}
	return v
}
