SELECT
  u.name as usuario,
  COUNT(a.id) as total_assets,
  COUNT(CASE WHEN a.mime_type LIKE 'image/%' THEN 1 END) as imagenes,
  COUNT(CASE WHEN a.mime_type = 'application/pdf' THEN 1 END) as pdfs,
  DATE_TRUNC('month', a.created_at) as mes
FROM "mnemos"."public"."assets" a
LEFT JOIN "mnemos"."public"."users" u ON a.user_id = u.id
GROUP BY u.name, DATE_TRUNC('month', a.created_at)