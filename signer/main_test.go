package main

import (
	"bytes"
	"crypto/x509"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"strings"
	"testing"
)

// тестовые материалы взяты из pkcs7_test.go в Theo730/pkcs7 — публичные.
const testCertPEM = `-----BEGIN CERTIFICATE-----
MIIDTDCCAjSgAwIBAgIIfE8EORzUmS0wDQYJKoZIhvcNAQEFBQAwRDELMAkGA1UE
BhMCVVMxFDASBgNVBAoMC0FmZmlybVRydXN0MR8wHQYDVQQDDBZBZmZpcm1UcnVz
dCBOZXR3b3JraW5nMB4XDTEwMDEyOTE0MDgyNFoXDTMwMTIzMTE0MDgyNFowRDEL
MAkGA1UEBhMCVVMxFDASBgNVBAoMC0FmZmlybVRydXN0MR8wHQYDVQQDDBZBZmZp
cm1UcnVzdCBOZXR3b3JraW5nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKC
AQEAtITMMxcua5Rsa2FSoOujz3mUTOWUgJnLVWREZY9nZOIG41w3SfYvm4SEHi3y
YJ0wTsyEheIszx6e/jarM3c1RNg1lho9Nuh6DtjVR6FqaYvZ/Ls6rnla1fTWcbua
kCNrmreIdIcMHl+5ni36q1Mr3Lt2PpNMCAiMHqIjHNRqrSK6mQEubWXLviRmVSRL
QESxG9fhwoXA3hA/Pe24/PHxI1Pcv2WXb9n5QHGNfb2V1M6+oF4nI979ptAmDgAp
6zxG8D1gvz9Q0twmQVGeFDdCBKNwV6gbh+0t+nvujArjqWaJGctB+d1ENmHP4ndG
yH329JKBNv3bNPFyfvMMFr20FQIDAQABo0IwQDAdBgNVHQ4EFgQUBx/S55zawm6i
QLSwelAQUHTEyL0wDwYDVR0TAQH/BAUwAwEB/zAOBgNVHQ8BAf8EBAMCAQYwDQYJ
KoZIhvcNAQEFBQADggEBAIlXshZ6qML91tmbmzTCnLQyFE2npN/svqe++EPbkTfO
tDIuUFUaNU52Q3Eg75N3ThVwLofDwR1t3Mu1J9QsVtFSUzpE0nPIxBsFZVpikpzu
QY0x2+c06lkh1QF612S4ZDnNye2v7UsDSKegmQGA3GWjNq5lWUhPgkvIZfFXHeVZ
Lgo/bNjR9eUJtGxUAArgFU2HdW23WJZa3W3SAKD0m0i+wzekujbgfIeFlxoVot4u
olu9rxj5kFDNcFn4J2dHy8egBzp90SxdbBk6ZrV9/ZFvgrG+CJPbFEfxojfHRZ48
x3evZKiT3/Zpg4Jg8klCNO1aAFSFHBY2kgxc+qatv9s=
-----END CERTIFICATE-----`

const testKeyPEM = `-----BEGIN PRIVATE KEY-----
MEMCAQAwHAYGKoUDAgITMBIGByqFAwICIwEGByqFAwICHgEEIOOb5GabRYCfBJmH
Egl7OfqChYELQexC7SFV8QLTiX4q
-----END PRIVATE KEY-----`

func mkTestCtx(t *testing.T) *signerCtx {
	t.Helper()

	certFile, err := os.CreateTemp(t.TempDir(), "cert-*.pem")
	if err != nil {
		t.Fatal(err)
	}
	if _, err := certFile.WriteString(testCertPEM); err != nil {
		t.Fatal(err)
	}
	certFile.Close()

	keyFile, err := os.CreateTemp(t.TempDir(), "key-*.pem")
	if err != nil {
		t.Fatal(err)
	}
	if _, err := keyFile.WriteString(testKeyPEM); err != nil {
		t.Fatal(err)
	}
	keyFile.Close()

	cert, err := loadCertificate(certFile.Name())
	if err != nil {
		t.Fatalf("loadCertificate: %v", err)
	}
	priv, err := loadPrivateKey(keyFile.Name())
	if err != nil {
		t.Fatalf("loadPrivateKey: %v", err)
	}
	return &signerCtx{apiKey: "test-key", cert: cert, privKey: priv}
}

func TestSignPayload(t *testing.T) {
	ctx := mkTestCtx(t)

	sig, err := ctx.signPayload([]byte("openid fullname snils2024-01-01T00:00:00 +0300CLIENT_ID9b2e35a4-bca8-4f3c-8d2c-7e3aab4cf001"))
	if err != nil {
		t.Fatalf("signPayload: %v", err)
	}
	if sig == "" {
		t.Fatalf("empty signature")
	}
	// Каждая ГОСТ-подпись недетерминирована (k randomized), поэтому проверяем
	// только размер и base64url-валидность.
	if len(sig) < 200 {
		t.Fatalf("signature too short, got %d bytes (b64url): %s", len(sig), sig)
	}
	if strings.ContainsAny(sig, "+/=") {
		t.Fatalf("signature contains non-url-safe characters: %s", sig)
	}
}

func TestHandleSignAuth(t *testing.T) {
	ctx := mkTestCtx(t)

	// Без X-API-Key — 401.
	req := httptest.NewRequest("POST", "/sign", bytes.NewBufferString(`{"payload":"abc"}`))
	w := httptest.NewRecorder()
	ctx.handleSign(w, req)
	if w.Code != http.StatusUnauthorized {
		t.Fatalf("want 401, got %d", w.Code)
	}

	// С неверным ключом — 401.
	req = httptest.NewRequest("POST", "/sign", bytes.NewBufferString(`{"payload":"abc"}`))
	req.Header.Set("X-API-Key", "nope")
	w = httptest.NewRecorder()
	ctx.handleSign(w, req)
	if w.Code != http.StatusUnauthorized {
		t.Fatalf("want 401 for bad key, got %d", w.Code)
	}

	// Корректный ключ + payload → 200 + JSON.
	req = httptest.NewRequest("POST", "/sign", bytes.NewBufferString(`{"payload":"abc"}`))
	req.Header.Set("X-API-Key", "test-key")
	w = httptest.NewRecorder()
	ctx.handleSign(w, req)
	if w.Code != http.StatusOK {
		t.Fatalf("want 200, got %d (body=%s)", w.Code, w.Body.String())
	}
	var resp signResponse
	if err := json.NewDecoder(w.Body).Decode(&resp); err != nil {
		t.Fatalf("decode: %v", err)
	}
	if resp.Signature == "" {
		t.Fatalf("empty signature in response")
	}
	if resp.Alg != algName {
		t.Fatalf("want alg=%s, got %s", algName, resp.Alg)
	}
}

func TestHandleHealth(t *testing.T) {
	w := httptest.NewRecorder()
	req := httptest.NewRequest("GET", "/health", nil)
	handleHealth(w, req)
	if w.Code != http.StatusOK {
		t.Fatalf("want 200, got %d", w.Code)
	}
	var resp map[string]any
	if err := json.NewDecoder(w.Body).Decode(&resp); err != nil {
		t.Fatalf("decode: %v", err)
	}
	if resp["status"] != "ok" {
		t.Fatalf("want status=ok, got %v", resp["status"])
	}
}

// убеждаемся, что parsed cert is X.509 v3 valid.
func TestLoadCertificateSubject(t *testing.T) {
	ctx := mkTestCtx(t)
	if ctx.cert.Subject.CommonName == "" {
		t.Fatalf("cert has empty CommonName")
	}
	if _, err := x509.ParseCertificate(ctx.cert.Raw); err != nil {
		t.Fatalf("re-parse: %v", err)
	}
}
