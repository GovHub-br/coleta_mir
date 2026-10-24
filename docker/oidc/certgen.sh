#!/bin/bash
set -euo pipefail

CERT_DIR="${OUTPUT_DIR:-/output}"
CA_KEY="$CERT_DIR/ca.key"
CA_CRT="$CERT_DIR/ca.crt"
TLS_KEY="$CERT_DIR/tls.key"
TLS_CRT="$CERT_DIR/tls.crt"
DOMAIN="sinapir-homol.igualdaderacial.gov.br"

ca_key_exists=0; ca_crt_exists=0; tls_key_exists=0; tls_crt_exists=0
[ -f "$CA_KEY" ] && ca_key_exists=1
[ -f "$CA_CRT" ] && ca_crt_exists=1
[ -f "$TLS_KEY" ] && tls_key_exists=1
[ -f "$TLS_CRT" ] && tls_crt_exists=1

total=$((ca_key_exists + ca_crt_exists + tls_key_exists + tls_crt_exists))

if [ "$total" -eq 0 ]; then
    mkdir -p "$CERT_DIR"

    openssl genrsa -out "$CA_KEY" 4096
    chmod 0600 "$CA_KEY"

    openssl req -x509 -new -nodes -key "$CA_KEY" -sha256 -days 3650 \
        -subj "/CN=$DOMAIN CA" \
        -addext "basicConstraints=critical,CA:TRUE" \
        -addext "keyUsage=keyCertSign,cRLSign" \
        -out "$CA_CRT"
    chmod 0644 "$CA_CRT"

    openssl genrsa -out "$TLS_KEY" 4096
    chmod 0600 "$TLS_KEY"

    openssl req -new -key "$TLS_KEY" \
        -subj "/CN=$DOMAIN" \
        -addext "subjectAltName=DNS:$DOMAIN" \
        -addext "extendedKeyUsage=serverAuth" \
        -addext "basicConstraints=CA:FALSE" \
        -out /tmp/tls.csr

    SERIAL=0x$(openssl rand -hex 16)
    openssl x509 -req -in /tmp/tls.csr \
        -CA "$CA_CRT" -CAkey "$CA_KEY" -set_serial "$SERIAL" \
        -days 365 -sha256 \
        -copy_extensions copyall \
        -out "$TLS_CRT"
    chmod 0644 "$TLS_CRT"

    rm -f /tmp/tls.csr

elif [ "$total" -eq 4 ]; then
    err=0

    if ! openssl x509 -in "$CA_CRT" -noout 2>/dev/null; then
        echo "ERROR: ca.crt is not a valid certificate" >&2; err=1
    fi
    if ! openssl x509 -in "$TLS_CRT" -noout 2>/dev/null; then
        echo "ERROR: tls.crt is not a valid certificate" >&2; err=1
    fi

    if ! openssl x509 -in "$CA_CRT" -checkend 0 >/dev/null 2>&1; then
        echo "ERROR: CA certificate has expired" >&2; err=1
    fi
    if ! openssl x509 -in "$TLS_CRT" -checkend 0 >/dev/null 2>&1; then
        echo "ERROR: Leaf certificate has expired" >&2; err=1
    fi

    if ! openssl x509 -in "$CA_CRT" -noout -ext basicConstraints 2>/dev/null | grep -q "CA:TRUE"; then
        echo "ERROR: CA certificate missing CA:TRUE" >&2; err=1
    fi
    if ! openssl x509 -in "$TLS_CRT" -noout -ext basicConstraints 2>/dev/null | grep -q "CA:FALSE"; then
        echo "ERROR: Leaf certificate missing CA:FALSE" >&2; err=1
    fi

    if ! openssl x509 -in "$TLS_CRT" -noout -ext subjectAltName 2>/dev/null | grep -q "DNS:$DOMAIN"; then
        echo "ERROR: Leaf certificate missing DNS:$DOMAIN in SAN" >&2; err=1
    fi

    if ! openssl x509 -in "$TLS_CRT" -noout -ext extendedKeyUsage 2>/dev/null | grep -q "TLS Web Server Authentication"; then
        echo "ERROR: Leaf certificate missing serverAuth extendedKeyUsage" >&2; err=1
    fi

    if ! openssl verify -CAfile "$CA_CRT" "$TLS_CRT" >/dev/null 2>&1; then
        echo "ERROR: Leaf certificate not signed by CA" >&2; err=1
    fi

    ca_pubkey=$(openssl x509 -in "$CA_CRT" -noout -pubkey 2>/dev/null)
    ca_key_pub=$(openssl pkey -in "$CA_KEY" -pubout 2>/dev/null)
    if [ "$ca_pubkey" != "$ca_key_pub" ]; then
        echo "ERROR: CA key does not match CA certificate" >&2; err=1
    fi

    tls_pubkey=$(openssl x509 -in "$TLS_CRT" -noout -pubkey 2>/dev/null)
    tls_key_pub=$(openssl pkey -in "$TLS_KEY" -pubout 2>/dev/null)
    if [ "$tls_pubkey" != "$tls_key_pub" ]; then
        echo "ERROR: Leaf key does not match leaf certificate" >&2; err=1
    fi

    chmod 0600 "$CA_KEY" "$TLS_KEY"
    chmod 0644 "$CA_CRT" "$TLS_CRT"

    if [ "$err" -ne 0 ]; then exit 1; fi
else
    echo "ERROR: Incomplete certificate set at $CERT_DIR" >&2
    echo "  exists: ca.key=$([ -f "$CA_KEY" ] && echo yes || echo no)" >&2
    echo "  exists: ca.crt=$([ -f "$CA_CRT" ] && echo yes || echo no)" >&2
    echo "  exists: tls.key=$([ -f "$TLS_KEY" ] && echo yes || echo no)" >&2
    echo "  exists: tls.crt=$([ -f "$TLS_CRT" ] && echo yes || echo no)" >&2
    echo "Remove all four files and re-run to regenerate." >&2
    exit 1
fi
