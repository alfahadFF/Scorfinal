<?php
/* =====================================================================
   ALFAMAN → Supabase — PRODUCTION config (keep this file private).
   Connection: PostgreSQL via Supabase Session pooler (IPv4).
   Direct host db.gvbogfyfmkohepmukqmw.supabase.co is IPv6-only — the
   pooler below works on standard hostings. Verified end-to-end
   2026-10-17 (import 33 products + publish EN/AR against this DB).
   ===================================================================== */
return [
    'driver' => 'pgsql',
    'host'   => 'aws-1-us-west-2.pooler.supabase.com',
    'port'   => 5432,
    'name'   => 'postgres',
    'user'   => 'postgres.gvbogfyfmkohepmukqmw',
    'pass'   => '#0797792433@Fm',
    'site'   => __DIR__ . '/../scor1',
];
