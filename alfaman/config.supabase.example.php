<?php
/* =====================================================================
   ALFAMAN — Supabase (PostgreSQL) connection settings.
   1) Copy this file to  config.php  in the same folder as api.php.
   2) Fill the four values marked  <--  from your Supabase dashboard:
      Project → Connect (top bar) → "Session pooler" connection string.
      It looks like:
        postgresql://postgres.gvbogfyfmkohepmukqmw:YOUR_DB_PASSWORD@aws-1-XX.pooler.supabase.com:5432/postgres
   3) The database password is the one chosen when the Supabase project
      was created (resettable under Settings → Database). It is NOT an
      API key (anon / publishable / secret cannot open a DB connection).
   Keep config.php private: never commit it, never paste it in chat,
   and keep it out of any publicly listable directory.
   ===================================================================== */
return [
    'driver' => 'pgsql',
    'host'   => 'aws-1-XX.pooler.supabase.com',   // <-- pooler host from Connect
    'port'   => 5432,                             // session pooler (5432). Direct: db.gvbogfyfmkohepmukqmw.supabase.co
    'name'   => 'postgres',                       // Supabase database name is always "postgres"
    'user'   => 'postgres.gvbogfyfmkohepmukqmw',  // <-- pooler user (direct connection: just "postgres")
    'pass'   => 'YOUR_DB_PASSWORD',               // <-- database password (NOT an API key)
    'site'   => __DIR__ . '/../scor1',            // path to the scor1 site folder (adjust if needed)
];
