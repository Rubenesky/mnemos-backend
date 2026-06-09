<!DOCTYPE html>
<html lang="en" data-theme="{{ $theme }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $collection->name }} — {{ $orgName }}</title>
    <style>
        /* ── Reset ─────────────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ── Tokens ────────────────────────────────────────────────────── */
        :root {
            --navy:   #0f172a;
            --gold:   #f59e0b;
            --bg:     #f8fafc;
            --surface:#ffffff;
            --border: #e2e8f0;
            --text:   #0f172a;
            --muted:  #64748b;
            --radius: 0.75rem;
        }
        [data-theme="dark"] {
            --bg:     #0f172a;
            --surface:#1e293b;
            --border: #334155;
            --text:   #f1f5f9;
            --muted:  #94a3b8;
        }

        /* ── Layout ────────────────────────────────────────────────────── */
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 1.25rem;
        }

        .embed-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }

        .embed-brand {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
        }

        .embed-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--text);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ── Grid ──────────────────────────────────────────────────────── */
        .embed-grid {
            display: grid;
            grid-template-columns: repeat({{ $cols }}, 1fr);
            gap: 0.875rem;
        }

        @media (max-width: 480px) {
            .embed-grid { grid-template-columns: repeat(2, 1fr); }
        }

        /* ── Card ──────────────────────────────────────────────────────── */
        .embed-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            transition: box-shadow 0.15s;
        }

        .embed-card:hover {
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
        }

        .embed-thumb {
            aspect-ratio: 4/3;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .embed-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .embed-thumb-icon {
            font-size: 2rem;
            opacity: 0.5;
        }

        .embed-card-body {
            padding: 0.625rem 0.75rem;
        }

        .embed-card-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ── Footer ────────────────────────────────────────────────────── */
        .embed-footer {
            margin-top: 1rem;
            text-align: right;
        }

        .embed-footer a {
            font-size: 0.7rem;
            color: var(--muted);
            text-decoration: none;
            opacity: 0.6;
        }

        .embed-footer a:hover { opacity: 1; }

        /* ── Empty ─────────────────────────────────────────────────────── */
        .embed-empty {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--muted);
            font-size: 0.875rem;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="embed-header">
        <svg class="embed-brand" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="24" height="24" rx="5" fill="#0f172a"/>
            <path d="M5 17V7l4 5 3-4 3 4 4-5v10" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <h1 class="embed-title">{{ $collection->name }}</h1>
    </div>

    <!-- Grid -->
    @if($assets->isEmpty())
        <div class="embed-empty">No assets available.</div>
    @else
        <div class="embed-grid">
            @foreach($assets as $asset)
                <div class="embed-card">
                    <div class="embed-thumb">
                        @if(str_starts_with($asset->mime_type, 'image/') && $asset->cloudinary_url)
                            <img
                                src="{{ $asset->cloudinary_url }}"
                                alt="{{ $asset->alt_text ?? $asset->metadata?->title ?? $asset->original_name }}"
                                loading="lazy"
                            >
                        @else
                            <span class="embed-thumb-icon">
                                @if(str_starts_with($asset->mime_type, 'video/')) 🎬
                                @elseif(str_starts_with($asset->mime_type, 'audio/')) 🎵
                                @elseif($asset->mime_type === 'application/pdf') 📄
                                @else 📁
                                @endif
                            </span>
                        @endif
                    </div>
                    <div class="embed-card-body">
                        <p class="embed-card-title">
                            {{ $asset->metadata?->title ?? $asset->original_name }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Footer -->
    <footer class="embed-footer">
        <a href="{{ config('app.frontend_url', config('app.url')) }}/gallery" target="_blank" rel="noopener">
            Powered by Mnemos
        </a>
    </footer>

</body>
</html>
