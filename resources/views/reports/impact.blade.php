<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="UTF-8"/>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; background: #fff; }
  .header { background: #0f172a; color: #fff; padding: 28px 36px; }
  .header-logo { font-size: 26px; font-weight: 700; color: #f59e0b; letter-spacing: -1px; }
  .header-sub { font-size: 13px; color: #94a3b8; margin-top: 4px; }
  .header-meta { font-size: 11px; color: #64748b; margin-top: 10px; }
  .header-period { display: inline-block; margin-left: 10px; background: #1e293b; color: #f59e0b; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; }
  .body { padding: 28px 36px; }
  h2 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; margin: 24px 0 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
  h2:first-child { margin-top: 0; }
  .stats-row { display: flex; gap: 12px; margin-bottom: 8px; }
  .stat-box { flex: 1; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px; }
  .stat-label { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; }
  .stat-value { font-size: 22px; font-weight: 700; color: #0f172a; margin-top: 2px; }
  table { width: 100%; border-collapse: collapse; margin-top: 6px; }
  th { font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; text-align: left; padding: 6px 10px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
  td { font-size: 12px; padding: 7px 10px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 10px; font-weight: 600; }
  .badge-green  { background: #dcfce7; color: #166534; }
  .badge-yellow { background: #fef9c3; color: #854d0e; }
  .badge-red    { background: #fee2e2; color: #991b1b; }
  .footer { margin-top: 36px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 14px; }
</style>
</head>
<body>
  <div class="header">
    <div class="header-logo">Mnemos</div>
    <div class="header-sub">{{ $labels['title'] }} — {{ $labels['subtitle'] }}</div>
    <div class="header-meta">
      {{ $labels['generated'] }}: {{ $generated_at }}
      <span class="header-period">{{ $labels['period_label'] }}</span>
    </div>
  </div>
  <div class="body">

    <h2>{{ $labels['overview'] }}</h2>
    <div class="stats-row">
      <div class="stat-box">
        <div class="stat-label">{{ $labels['total'] }}</div>
        <div class="stat-value">{{ $assets['total'] }}</div>
      </div>
      <div class="stat-box">
        <div class="stat-label">{{ $labels['processed'] }}</div>
        <div class="stat-value">{{ $assets['processed'] }}</div>
      </div>
      <div class="stat-box">
        <div class="stat-label">{{ $labels['pending'] }}</div>
        <div class="stat-value">{{ $assets['pending'] }}</div>
      </div>
      <div class="stat-box">
        <div class="stat-label">{{ $labels['press_kit'] }}</div>
        <div class="stat-value">{{ $assets['press_kit'] }}</div>
      </div>
    </div>
    <div class="stats-row">
      <div class="stat-box" style="max-width: 25%;">
        <div class="stat-label">{{ $labels['emergency_kit'] }}</div>
        <div class="stat-value">{{ $assets['emergency_kit'] }}</div>
      </div>
    </div>

    <h2>{{ $labels['by_type'] }}</h2>
    <table>
      <thead>
        <tr><th>{{ $labels['type'] }}</th><th>{{ $labels['count'] }}</th></tr>
      </thead>
      <tbody>
        <tr><td>{{ $labels['images'] }}</td><td>{{ $assets['by_type']['images'] }}</td></tr>
        <tr><td>{{ $labels['videos'] }}</td><td>{{ $assets['by_type']['videos'] }}</td></tr>
        <tr><td>{{ $labels['documents'] }}</td><td>{{ $assets['by_type']['documents'] }}</td></tr>
        <tr><td>{{ $labels['audio'] }}</td><td>{{ $assets['by_type']['audio'] }}</td></tr>
        <tr><td>{{ $labels['other'] }}</td><td>{{ $assets['by_type']['other'] }}</td></tr>
      </tbody>
    </table>

    <h2>{{ $labels['consents'] }}</h2>
    <table>
      <thead>
        <tr><th>{{ $labels['status'] }}</th><th>{{ $labels['count'] }}</th></tr>
      </thead>
      <tbody>
        <tr>
          <td><span class="badge badge-green">{{ $labels['obtained'] }}</span></td>
          <td>{{ $consents['obtained'] }}</td>
        </tr>
        <tr>
          <td><span class="badge badge-yellow">{{ $labels['consent_pending'] }}</span></td>
          <td>{{ $consents['pending'] }}</td>
        </tr>
        <tr>
          <td><span class="badge badge-red">{{ $labels['denied'] }}</span></td>
          <td>{{ $consents['denied'] }}</td>
        </tr>
      </tbody>
    </table>

    <h2>{{ $labels['team'] }}</h2>
    <table>
      <thead>
        <tr><th>{{ $labels['role'] }}</th><th>{{ $labels['count'] }}</th></tr>
      </thead>
      <tbody>
        <tr><td>{{ $labels['admins'] }}</td><td>{{ $users['admins'] }}</td></tr>
        <tr><td>{{ $labels['editors'] }}</td><td>{{ $users['editors'] }}</td></tr>
        <tr><td>{{ $labels['viewers'] }}</td><td>{{ $users['viewers'] }}</td></tr>
        <tr><td>{{ $labels['volunteers'] }}</td><td>{{ $users['volunteers'] }}</td></tr>
      </tbody>
    </table>

    <div class="footer">{{ $labels['footer'] }}</div>
  </div>
</body>
</html>
