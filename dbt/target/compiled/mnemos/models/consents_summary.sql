SELECT
  COUNT(*) as total_consentimientos,
  COUNT(CASE WHEN status = 'obtained' THEN 1 END) as aprobados,
  COUNT(CASE WHEN status = 'denied' THEN 1 END) as rechazados,
  COUNT(CASE WHEN status = 'pending' THEN 1 END) as pendientes,
  DATE_TRUNC('month', created_at) as mes
FROM "mnemos"."public"."consents"
GROUP BY DATE_TRUNC('month', created_at)