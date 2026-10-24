CREATE TABLE IF NOT EXISTS "#__oidc_identities" (
    "id" SERIAL PRIMARY KEY,
    "provider_key" VARCHAR(64) NOT NULL,
    "issuer" VARCHAR(255) NOT NULL,
    "subject" VARCHAR(255) NOT NULL,
    "user_id" INT NOT NULL,
    "created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "last_login_at" TIMESTAMP DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_oidc_issuer_subject" ON "#__oidc_identities" ("issuer", "subject");
CREATE UNIQUE INDEX IF NOT EXISTS "idx_oidc_provider_user" ON "#__oidc_identities" ("provider_key", "user_id");
