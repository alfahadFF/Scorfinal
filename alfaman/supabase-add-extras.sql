-- =====================================================================
-- ALFAMAN — Supabase add-on (run once in Supabase → SQL Editor)
-- Your project already has 9 of the 10 tables (empty). This adds the
-- missing one (product_extras, added to the schema after you ran the
-- original install) with the same access pattern as the other tables:
-- public READ via the publishable key, writes blocked by RLS.
-- =====================================================================

CREATE TABLE IF NOT EXISTS public.product_extras (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  product_id BIGINT NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
  name VARCHAR(160) NOT NULL,
  description TEXT,
  image_path VARCHAR(255),
  sort_order INT NOT NULL DEFAULT 0
);

ALTER TABLE public.product_extras ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Public read" ON public.product_extras;
CREATE POLICY "Public read" ON public.product_extras
  FOR SELECT TO anon, authenticated USING (true);

GRANT SELECT ON public.product_extras TO anon, authenticated;

-- =====================================================================
-- RECOMMENDED hardening (same SQL run):
-- The app table "users" (admin accounts + password hashes) and
-- "activity_log" are currently readable through the public REST API
-- like the catalog tables. ALFAMAN's api.php connects directly as the
-- database owner, so it does NOT need REST access to them. Revoking
-- the public grants closes the leak without breaking the panel.
-- =====================================================================
REVOKE ALL ON public.users FROM anon, authenticated;
REVOKE ALL ON public.activity_log FROM anon, authenticated;
